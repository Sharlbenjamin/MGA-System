<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Resources\LeadResource;
use App\Models\Client;
use App\Models\Lead;
use Filament\Actions;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
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
                ->modalWidth('2xl')
                ->authorize(fn (): bool => LeadResource::canCreate())
                ->form([
                    Toggle::make('create_new_client')
                        ->label('Create new client')
                        ->default(false)
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?bool $state): void {
                            if (! $state) {
                                return;
                            }

                            $set('client_id', null);
                            $set('new_client_type', 'Assistance');
                            $set('new_client_status', 'Interested');
                        }),
                    Select::make('client_id')
                        ->label('Client')
                        ->options(fn (): array => Client::query()->orderBy('company_name')->pluck('company_name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get): bool => ! $get('create_new_client'))
                        ->visible(fn (Get $get): bool => ! $get('create_new_client')),
                    Grid::make(2)
                        ->schema([
                            TextInput::make('new_client_company_name')
                                ->label('Company name')
                                ->required(fn (Get $get): bool => (bool) $get('create_new_client'))
                                ->unique('clients', 'company_name')
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Set $set, ?string $state): void {
                                    $set('new_client_initials', self::initialsFromCompanyName($state));
                                }),
                            Select::make('new_client_type')
                                ->label('Client type')
                                ->options([
                                    'Assistance' => 'Assistance',
                                    'Insurance' => 'Insurance',
                                    'Agency' => 'Agency',
                                ])
                                ->default('Assistance')
                                ->required(fn (Get $get): bool => (bool) $get('create_new_client')),
                            Select::make('new_client_status')
                                ->label('Client status')
                                ->options([
                                    'Searching' => 'Searching',
                                    'Interested' => 'Interested',
                                    'Sent' => 'Sent',
                                    'Rejected' => 'Rejected',
                                    'Active' => 'Active',
                                    'On Hold' => 'On Hold',
                                    'Closed' => 'Closed',
                                    'Broker' => 'Broker',
                                    'No Reply' => 'No Reply',
                                ])
                                ->default('Interested')
                                ->required(fn (Get $get): bool => (bool) $get('create_new_client')),
                            TextInput::make('new_client_initials')
                                ->label('Initials')
                                ->maxLength(10)
                                ->required(fn (Get $get): bool => (bool) $get('create_new_client')),
                        ])
                        ->visible(fn (Get $get): bool => (bool) $get('create_new_client')),
                    TextInput::make('first_name')
                        ->label('First name')
                        ->required(),
                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->unique('leads', 'email'),
                    Select::make('status')
                        ->label('Lead status')
                        ->options(\App\Filament\Resources\DraftMailResource::getAvailableStatuses('Client'))
                        ->default('Introduction')
                        ->searchable()
                        ->preload()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    if (! empty($data['create_new_client'])) {
                        $companyName = (string) ($data['new_client_company_name'] ?? '');
                        $initials = trim((string) ($data['new_client_initials'] ?? ''));

                        if ($initials === '') {
                            $initials = self::initialsFromCompanyName($companyName);
                        }

                        $client = Client::create([
                            'company_name' => $companyName,
                            'type' => $data['new_client_type'] ?? 'Assistance',
                            'status' => $data['new_client_status'] ?? 'Interested',
                            'initials' => $initials,
                            'number_requests' => 0,
                        ]);

                        $data['client_id'] = $client->id;
                    }

                    Lead::create([
                        'client_id' => $data['client_id'],
                        'first_name' => $data['first_name'],
                        'email' => $data['email'],
                        'status' => $data['status'] ?? 'Introduction',
                    ]);

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

    public static function initialsFromCompanyName(?string $name): string
    {
        $words = preg_split('/\s+/', trim((string) $name)) ?: [];
        $letters = [];

        foreach ($words as $word) {
            if (preg_match('/\p{L}|\p{N}/u', $word, $match) !== 1) {
                continue;
            }

            $letters[] = mb_strtoupper($match[0]);
        }

        return mb_substr(implode('', $letters), 0, 10);
    }
}
