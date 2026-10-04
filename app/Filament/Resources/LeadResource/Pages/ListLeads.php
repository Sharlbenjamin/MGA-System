<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Resources\LeadResource;
use App\Models\Client;
use App\Models\Lead;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('createLead')
                ->label('Create lead')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->modalHeading('Create lead')
                ->modalSubmitActionLabel('Create')
                ->modalWidth('lg')
                ->authorize(fn (): bool => LeadResource::canCreate())
                ->form([
                    Select::make('client_id')
                        ->label('Client')
                        ->options(fn (): array => Client::query()->orderBy('company_name')->pluck('company_name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('first_name')
                        ->label('First name')
                        ->required(),
                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->unique('leads', 'email'),
                    Select::make('status')
                        ->label('Status')
                        ->options(\App\Filament\Resources\DraftMailResource::getAvailableStatuses('Client'))
                        ->default('Introduction')
                        ->searchable()
                        ->preload()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    Lead::create($data);

                    Notification::make()
                        ->title('Lead created')
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make()
                ->label('Full form'),
            Actions\Action::make('Send Email')
            ->action(fn ($record) => $this->sendEmailToLead($record))
            ->requiresConfirmation(),
        ];
    }
}
