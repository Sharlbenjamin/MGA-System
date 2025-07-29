<?php

namespace App\Filament\Resources\ProviderBranchResource\Pages;

use App\Filament\Resources\ProviderBranchResource;
use App\Models\Provider;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateProviderBranch extends CreateRecord
{
    protected static string $resource = ProviderBranchResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Handle new provider creation
        if (isset($data['create_new_provider']) && $data['create_new_provider']) {
            $provider = Provider::create([
                'name' => $data['new_provider_name'],
                'type' => $data['new_provider_type'],
                'country_id' => $data['new_provider_country_id'],
                'status' => $data['new_provider_status'],
                'email' => $data['new_provider_email'] ?? null,
                'phone' => $data['new_provider_phone'] ?? null,
                'payment_due' => $data['new_provider_payment_due'] ?? null,
                'payment_method' => $data['new_provider_payment_method'] ?? null,
                'comment' => $data['new_provider_comment'] ?? null,
            ]);

            $data['provider_id'] = $provider->id;

            // Remove the new provider fields from the data
            unset($data['create_new_provider']);
            unset($data['new_provider_name']);
            unset($data['new_provider_type']);
            unset($data['new_provider_country_id']);
            unset($data['new_provider_status']);
            unset($data['new_provider_email']);
            unset($data['new_provider_phone']);
            unset($data['new_provider_payment_due']);
            unset($data['new_provider_payment_method']);
            unset($data['new_provider_comment']);

            Notification::make()
                ->title('Provider Created')
                ->body("New provider '{$provider->name}' has been created successfully.")
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
