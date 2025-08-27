<?php

namespace App\Services;

use App\Models\File;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\ConferenceData;
use Google\Service\Calendar\CreateConferenceRequest;
use Google\Service\Calendar\ConferenceSolutionKey;
use Google\Service\Calendar\EventAttendee;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\MeetingLinkCreated;
use App\Models\Contact;
use App\Services\GoogleCalendar;

class GoogleMeetService
{
    public function generateMeetLink(File $file)
    {
        if (!$file->service_date || !$file->service_time) {
            Log::warning('Telemedicine meeting creation failed: Missing service date or time', [
                'file_id' => $file->id,
                'service_date' => $file->service_date,
                'service_time' => $file->service_time
            ]);
            return null;
        }

        // Convert service_time to string if it's an array
        $serviceTime = is_array($file->service_time) ? $file->service_time['time'] ?? '' : $file->service_time;

        // Create the start datetime by combining the date and time properly
        // service_date is already a Carbon instance (cast as 'date')
        // service_time is a string in format like "14:30:00"
        $startDateTime = $file->service_date->copy()->setTimeFromTimeString($serviceTime);
        $endDateTime = $startDateTime->copy()->addMinutes(30);

        Log::info('Creating telemedicine meeting', [
            'file_id' => $file->id,
            'patient_name' => $file->patient->name,
            'service_date' => $file->service_date->toDateString(),
            'service_time' => $serviceTime,
            'start_time' => $startDateTime->toDateTimeString(),
            'end_time' => $endDateTime->toDateTimeString(),
            'timezone' => $startDateTime->timezone->getName(),
            'file_email' => $file->email
        ]);

        $meetLink = $this->createGoogleCalendarEvent($file, $startDateTime, $endDateTime);
        if ($meetLink) {
            $this->sendNotifications($file, $meetLink);
            Log::info('Telemedicine meeting created successfully', [
                'file_id' => $file->id,
                'meet_link' => $meetLink
            ]);
        } else {
            Log::error('Failed to create telemedicine meeting', [
                'file_id' => $file->id
            ]);
        }

        return $meetLink;
    }

    private function createGoogleCalendarEvent(File $file, $startDateTime, $endDateTime)
    {
        try {
            $conferenceRequest = new CreateConferenceRequest();
            $conferenceRequest->setRequestId(uniqid());
            $conferenceRequest->setConferenceSolutionKey(new ConferenceSolutionKey(['type' => 'hangoutsMeet']));

            $conferenceData = new ConferenceData();
            $conferenceData->setCreateRequest($conferenceRequest);

            // Prepare attendees array
            $attendees = [];
            
            // Add doctor's email (from provider branch operation contact)
            if ($file->providerBranch) {
                $branchOperationContact = $file->providerBranch->operationContact;
                if ($branchOperationContact) {
                    $doctorEmail = $this->getPreferredEmail($branchOperationContact);
                    if ($doctorEmail) {
                        $attendees[] = new EventAttendee([
                            'email' => $doctorEmail,
                            'displayName' => $file->providerBranch->branch_name . ' Doctor'
                        ]);
                        Log::info('Added doctor as attendee', [
                            'file_id' => $file->id,
                            'doctor_email' => $doctorEmail
                        ]);
                    }
                }
            }

            // Client notifications are disabled as per new flow – DO NOT ENABLE
            // Add patient's email (from file->email)
            // if ($file->email) {
            //     $attendees[] = new EventAttendee([
            //         'email' => $file->email,
            //         'displayName' => $file->patient->name . ' (Patient)'
            //     ]);
            //     Log::info('Added patient as attendee', [
            //         'file_id' => $file->id,
            //         'patient_email' => $file->email
            //     ]);
            // } else {
            //     Log::warning('No patient email available for calendar event', [
            //         'file_id' => $file->id
            //     ]);
            // }

            // Add provider's email directly (priority) or from provider operation contact (fallback)
            if ($file->providerBranch && $file->providerBranch->provider) {
                $providerEmail = null;
                
                // First, try to use branch's direct email
                if ($file->providerBranch->email) {
                    $providerEmail = $file->providerBranch->email;
                    Log::info('Using branch direct email', [
                        'file_id' => $file->id,
                        'provider_email' => $providerEmail
                    ]);
                } else {
                    // Fallback to provider's email directly
                    if ($file->providerBranch->provider->email) {
                        $providerEmail = $file->providerBranch->provider->email;
                        Log::info('Using provider email directly', [
                            'file_id' => $file->id,
                            'provider_email' => $providerEmail
                        ]);
                    } else {
                        // Fallback to provider operation contact
                        $providerOperationContact = $file->providerBranch->provider->operationContact;
                        if ($providerOperationContact) {
                            $providerEmail = $this->getPreferredEmail($providerOperationContact);
                            Log::info('Using provider operation contact email', [
                                'file_id' => $file->id,
                                'provider_email' => $providerEmail
                            ]);
                        }
                    }
                }
                
                if ($providerEmail) {
                    $attendees[] = new EventAttendee([
                        'email' => $providerEmail,
                        'displayName' => $file->providerBranch->provider->name . ' (Provider)'
                    ]);
                    Log::info('Added provider as attendee', [
                        'file_id' => $file->id,
                        'provider_email' => $providerEmail
                    ]);
                } else {
                    Log::warning('No provider email available for calendar event', [
                        'file_id' => $file->id,
                        'provider_id' => $file->providerBranch->provider->id
                    ]);
                }
            }

            // Build description with phone numbers
            $description = "Medical consultation for file reference: {$file->mga_reference}\n\nPatient: {$file->patient->name}";
            
            // Add patient phone if available
            if ($file->phone) {
                $description .= "\nPatient Phone: {$file->phone}";
            }
            
            $description .= "\nSymptoms: {$file->symptoms}\nProvider: {$file->providerBranch->branch_name}";
            
            // Add provider phone if available
            if ($file->providerBranch && $file->providerBranch->provider && $file->providerBranch->provider->phone) {
                $description .= "\nProvider Phone: {$file->providerBranch->provider->phone}";
            }

            $event = new Event([
                'summary' => "Telemedicine Consultation - {$file->patient->name}",
                'description' => $description,
                'start' => ['dateTime' => $startDateTime->toRfc3339String()],
                'end' => ['dateTime' => $endDateTime->toRfc3339String()],
                'conferenceData' => $conferenceData,
                'attendees' => $attendees
            ]);

            $client = GoogleCalendar::getClient();
            $calendarService = new Calendar($client);

            Log::info('Creating Google Calendar event', [
                'file_id' => $file->id,
                'attendees_count' => count($attendees),
                'calendar_id' => 'mga.operation@medguarda.com'
            ]);

            $createdEvent = $calendarService->events->insert(
                'mga.operation@medguarda.com',
                $event,
                ['conferenceDataVersion' => 1, 'sendUpdates' => 'all']
            );

            if (!$createdEvent->getHangoutLink()) {
                Log::error('Google Calendar event created but no Hangout link generated', [
                    'file_id' => $file->id,
                    'event_id' => $createdEvent->getId()
                ]);
                return null;
            }

            Log::info('Google Calendar event created successfully', [
                'file_id' => $file->id,
                'event_id' => $createdEvent->getId(),
                'hangout_link' => $createdEvent->getHangoutLink()
            ]);

            return $createdEvent->getHangoutLink();

        } catch (\Exception $e) {
            Log::error('Failed to create Google Calendar event', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    private function getPreferredEmail(Contact $contact): ?string
    {
        if (!$contact) {
            return null;
        }

        switch ($contact->preferred_contact) {
            case 'First Email':
                return $contact->email;
            case 'Second Email':
                return $contact->second_email;
            default:
                return $contact->email ?? $contact->second_email;
        }
    }

    private function sendNotifications(File $file, string $meetLink)
    {
        $recipients = collect();
        $hasOperationContacts = false;

        // Client notifications are disabled as per new flow – DO NOT ENABLE
        // Add file email (patient's email) as primary recipient
        // if ($file->email) {
        //     $recipients->push($file->email);
        //     $hasOperationContacts = true;
        //     Log::info('Added patient email to recipients', [
        //         'file_id' => $file->id,
        //         'patient_email' => $file->email
        //     ]);
        // } else {
        //     Log::warning('No patient email found in file', [
        //         'file_id' => $file->id
        //     ]);
        // }

        // Get provider email directly (priority) or from provider operation contact (fallback)
        if ($file->providerBranch && $file->providerBranch->provider) {
            $providerEmail = null;
            
            // First, try to use branch's direct email
            if ($file->providerBranch->email) {
                $providerEmail = $file->providerBranch->email;
                Log::info('Using branch direct email for notifications', [
                    'file_id' => $file->id,
                    'provider_email' => $providerEmail
                ]);
            } else {
                // Fallback to provider's email directly
                if ($file->providerBranch->provider->email) {
                    $providerEmail = $file->providerBranch->provider->email;
                    Log::info('Using provider email directly for notifications', [
                        'file_id' => $file->id,
                        'provider_email' => $providerEmail
                    ]);
                } else {
                    // Fallback to provider operation contact
                    $providerOperationContact = $file->providerBranch->provider->operationContact;
                    if ($providerOperationContact) {
                        $providerEmail = $this->getPreferredEmail($providerOperationContact);
                        Log::info('Using provider operation contact email for notifications', [
                            'file_id' => $file->id,
                            'provider_email' => $providerEmail
                        ]);
                    }
                }
            }
            
            if ($providerEmail) {
                $recipients->push($providerEmail);
                $hasOperationContacts = true;
                Log::info('Added provider email to recipients', [
                    'file_id' => $file->id,
                    'provider_email' => $providerEmail
                ]);
            } else {
                Log::warning('No provider email available for notifications', [
                    'file_id' => $file->id,
                    'provider_id' => $file->providerBranch->provider->id
                ]);
            }
        }

        // Get branch operation contact (doctor's email) - only if provider email is not already added
        if ($file->providerBranch && !$hasOperationContacts) {
            $branchOperationContact = $file->providerBranch->operationContact;
            if ($branchOperationContact) {
                $email = $this->getPreferredEmail($branchOperationContact);
                if ($email) {
                    $recipients->push($email);
                    $hasOperationContacts = true;
                    Log::info('Added doctor email to recipients (fallback)', [
                        'file_id' => $file->id,
                        'doctor_email' => $email
                    ]);
                }
            }
        }

        // If no operation contacts found, use default
        if (!$hasOperationContacts) {
            $recipients->push('mga.operation@medguarda.com');
            Log::info('No contacts found, using default email', [
                'file_id' => $file->id
            ]);
        }

        // Remove duplicates and send email
        $recipients = $recipients->unique();

        Log::info('Sending telemedicine meeting notifications', [
            'file_id' => $file->id,
            'recipients' => $recipients->toArray(),
            'total_recipients' => $recipients->count()
        ]);

        if ($recipients->isNotEmpty()) {
            try {
                Mail::to($recipients->first())
                    ->cc($recipients->slice(1)->all())
                    ->send(new MeetingLinkCreated($file, $meetLink));
                
                Log::info('Telemedicine meeting notifications sent successfully', [
                    'file_id' => $file->id,
                    'recipients_count' => $recipients->count()
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send telemedicine meeting notifications', [
                    'file_id' => $file->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}

