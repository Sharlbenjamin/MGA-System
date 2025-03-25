<?php

namespace App\Services;

use Google\Client as Google_Client;
use Google\Service\Calendar;
use Illuminate\Support\Facades\Log;

class GoogleCalendar
{
    public static function getClient(): Google_Client
    {
        try {
            $client = new Google_Client();

            // Load credentials from the service account file
            $credentialsPath = storage_path('app/google-calendar/credentials.json');
            if (!file_exists($credentialsPath)) {
                throw new \Exception('Service account credentials file not found');
            }

            // Set credentials directly
            $client->setAuthConfig($credentialsPath);

            // Set application name
            $client->setApplicationName('MGA TM System');

            // Set service account to act as specific user
            $client->setSubject('mga.operation@medguarda.com');

            // Set scopes
            $client->setScopes([
                'https://www.googleapis.com/auth/calendar',
                'https://www.googleapis.com/auth/calendar.events'
            ]);

            Log::info('Google Calendar client authenticated successfully');
            return $client;

        } catch (\Exception $e) {
            Log::error('Google Calendar authentication failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
