<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Resources\LeadResource;
use App\Models\Client;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateLead extends CreateRecord
{
    protected static string $resource = LeadResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Handle new client creation
        if (isset($data['create_new_client']) && $data['create_new_client']) {
            $client = Client::create([
                'company_name' => $data['new_client_company_name'],
                'type' => $data['new_client_type'],
                'status' => $data['new_client_status'],
                'initials' => $data['new_client_initials'],
                'number_requests' => $data['new_client_number_requests'] ?? 0,
                'email' => $data['new_client_email'] ?? null,
                'phone' => $data['new_client_phone'] ?? null,
            ]);

            $data['client_id'] = $client->id;

            // Remove the new client fields from the data
            unset($data['create_new_client']);
            unset($data['new_client_company_name']);
            unset($data['new_client_type']);
            unset($data['new_client_status']);
            unset($data['new_client_initials']);
            unset($data['new_client_number_requests']);
            unset($data['new_client_email']);
            unset($data['new_client_phone']);

            Notification::make()
                ->title('Client Created')
                ->body("New client '{$client->company_name}' has been created successfully.")
                ->success()
                ->send();
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
