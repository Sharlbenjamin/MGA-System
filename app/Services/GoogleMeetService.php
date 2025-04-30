<?php

namespace App\Services;

use App\Models\File;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\ConferenceData;
use Google\Service\Calendar\CreateConferenceRequest;
use Google\Service\Calendar\ConferenceSolutionKey;
use Illuminate\Support\Facades\Mail;
use App\Mail\MeetingLinkCreated;
use App\Models\Contact;

class GoogleMeetService
{
    public function generateMeetLink(File $file)
    {
        if (!$file->service_date || !$file->service_time) {
            return null;
        }

        // Convert service_time to string if it's an array
        $serviceTime = is_array($file->service_time) ? $file->service_time['time'] ?? '' : $file->service_time;

        $startDateTime = \Carbon\Carbon::parse($file->service_date)
            ->setTimeFromTimeString($serviceTime);
        $endDateTime = $startDateTime->copy()->addMinutes(30);

        $meetLink = $this->createGoogleCalendarEvent($file, $startDateTime, $endDateTime);
        if ($meetLink) {
            $this->sendNotifications($file, $meetLink);
        }

        return $meetLink;
    }

    private function createGoogleCalendarEvent(File $file, $startDateTime, $endDateTime)
    {
        $conferenceRequest = new CreateConferenceRequest();
        $conferenceRequest->setRequestId(uniqid());
        $conferenceRequest->setConferenceSolutionKey(new ConferenceSolutionKey(['type' => 'hangoutsMeet']));

        $conferenceData = new ConferenceData();
        $conferenceData->setCreateRequest($conferenceRequest);

        $event = new Event([
            'summary' => "Telemedicine Consultation - {$file->patient->name}",
            'description' => "Medical consultation for file reference: {$file->mga_reference}",
            'start' => ['dateTime' => $startDateTime->toRfc3339String()],
            'end' => ['dateTime' => $endDateTime->toRfc3339String()],
            'conferenceData' => $conferenceData
        ]);

        try {
            $client = GoogleCalendar::getClient();
            $calendarService = new Calendar($client);

            $createdEvent = $calendarService->events->insert(
                'mga.operation@medguarda.com',
                $event,
                ['conferenceDataVersion' => 1]
            );

            if (!$createdEvent->getHangoutLink()) {
                return null;
            }

            return $createdEvent->getHangoutLink();
        } catch (\Exception $e) {
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

        // Get provider operation contact
        if ($file->providerBranch && $file->providerBranch->provider) {
            $providerOperationContact = $file->providerBranch->provider->operationContact;
            if ($providerOperationContact) {
                $email = $this->getPreferredEmail($providerOperationContact);
                if ($email) {
                    $recipients->push($email);
                    $hasOperationContacts = true;
                }
            }
        }

        // Get branch operation contact
        if ($file->providerBranch) {
            $branchOperationContact = $file->providerBranch->operationContact;
            if ($branchOperationContact) {
                $email = $this->getPreferredEmail($branchOperationContact);
                if ($email) {
                    $recipients->push($email);
                    $hasOperationContacts = true;
                }
            }
        }

        // Get patient operation contact
        if ($file->patient) {
            $patientOperationContact = $file->patient->operationContact;
            if ($patientOperationContact) {
                $email = $this->getPreferredEmail($patientOperationContact);
                if ($email) {
                    $recipients->push($email);
                    $hasOperationContacts = true;
                }
            }
        }

        // If no operation contacts found, use default
        if (!$hasOperationContacts) {
            $recipients->push('mga.operation@medguarda.com');
        }

        // Remove duplicates and send email
        $recipients = $recipients->unique();

        if ($recipients->isNotEmpty()) {
            try {
                Mail::to($recipients->first())
                    ->cc($recipients->slice(1)->all())
                    ->send(new MeetingLinkCreated($file, $meetLink));
            } catch (\Exception $e) {
                // Silent fail on email sending
            }
        }
    }
}

