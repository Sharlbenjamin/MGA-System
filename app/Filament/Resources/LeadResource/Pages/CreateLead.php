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
            $companyName = (string) ($data['new_client_company_name'] ?? '');
            $initials = trim((string) ($data['new_client_initials'] ?? ''));

            if ($initials === '') {
                $initials = LeadResource::initialsFromCompanyName($companyName);
            }

            $client = Client::create([
                'company_name' => $companyName,
                'type' => $data['new_client_type'] ?? 'Assistance',
                'status' => $data['new_client_status'] ?? LeadResource::DEFAULT_CLIENT_STATUS,
                'initials' => $initials,
                'number_requests' => 0,
            ]);

            $data['client_id'] = $client->id;

            // Remove the new client fields from the data
            unset($data['create_new_client']);
            unset($data['new_client_company_name']);
            unset($data['new_client_type']);
            unset($data['new_client_status']);
            unset($data['new_client_initials']);

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
