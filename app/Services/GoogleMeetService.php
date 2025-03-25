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
use Filament\Notifications\Notification;

class GoogleMeetService
{
    public function generateMeetLink(File $file)
    {
        if (!$file->service_date || !$file->service_time) {
            Notification::make()
                ->warning()
                ->title('Missing Information')
                ->body('Service date and time are required.')
                ->send();
            return null;
        }

        $startDateTime = \Carbon\Carbon::parse($file->service_date)
            ->setTimeFromTimeString($file->service_time);
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
                Notification::make()->danger()->title('Meet Link Generation Failed')->body('Failed to generate Google Meet link.')->send();
                return null;
            }

            return $createdEvent->getHangoutLink();
        } catch (\Exception $e) {
            Notification::make()->danger()->title('Calendar Event Creation Failed')->body($e->getMessage())->send();
            return null;
        }
    }

    private function sendNotifications(File $file, string $meetLink)
    {
        $recipients = collect();
        $hasOperationContacts = false;

        // Check provider primary contact
        if (!$file->providerBranch) {
            Notification::make()->warning()->title('Provider Not Found')->body('No provider found for this file.')->send();
        } else {
            $providerOperationContact = $file->providerBranch->primaryContact('Appointment');
            if ($providerOperationContact) {
                $recipients->push($providerOperationContact->email);
                $hasOperationContacts = true;
            }
        }

        // Check branch primary contact
        if (!$file->providerBranch) {
            Notification::make()->warning()->title('Provider Branch Not Found')->body('No provider branch found for this file.')->send();
        } else {
            $branchOperationContact = $file->providerBranch->primaryContact('Appointment');
            if ($branchOperationContact) {
                $recipients->push($branchOperationContact->email);
                $hasOperationContacts = true;
            }
        }

        // If no operation contacts found, notify and use default
        if (!$hasOperationContacts) {
            Notification::make()->warning()->title('No primary operation contacts found')->body('Meeting link will be sent to MGA operations.')->send();
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
                Notification::make()->danger()->title('Email Sending Failed')->body('Failed to send meeting link notifications.')->send();
                //Notification::make()->danger()->title('Email Sending Failed')->body($e->getMessage())->send();
            }
        } else {
            Notification::make()->danger()->title('No Recipients')->body('No recipients found for meeting link notification.')->send();
        }
    }
}
