<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use App\Models\Lead;
use Filament\Forms;
use Filament\Forms\ComponentContainer;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LeadsRelationManager extends RelationManager
{
    protected static string $relationship = 'leads'; // The relation name in the Client model

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                TextInput::make('email')
                    ->email()
                    ->unique()
                    ->required(),

                TextInput::make('first_name')
                    ->required(),

                Select::make('status')
                    ->options([
                        'Introduction' => 'Introduction',
                        'Introduction Sent' => 'Introduction Sent',
                        'Reminder' => 'Reminder',
                        'Reminder Sent' => 'Reminder Sent',
                        'Presentation' => 'Presentation',
                        'Presentation Sent' => 'Presentation Sent',
                        'Price List' => 'Price List',
                        'Price List Sent' => 'Price List Sent',
                        'Contract' => 'Contract',
                        'Contract Sent' => 'Contract Sent',
                        'Interested' => 'Interested',
                        'Error' => 'Error',
                        'Partner' => 'Partner',
                        'Rejected' => 'Rejected',
                    ])
                    ->required(),

                DatePicker::make('last_contact_date')
                    ->nullable(),
            ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->sortable()->searchable(),
                TextColumn::make('first_name')->sortable()->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('last_contact_date')->date(),
            ])->bulkActions([
                BulkAction::make('send_tailored_mail')
                ->label('Send Tailored Mail')
                ->form([
                    TextInput::make('subject')
                        ->label('Email Subject')
                        ->required(),
                    Textarea::make('body')
                        ->label('Email Body')
                        ->required(),
                ])
                ->action(function (ComponentContainer $form, $records) {
                    // Get form data
                    $subject = $form->getState()['subject'];
                    $body = $form->getState()['body'];

                    // Collect lead emails
                    $emails = $records->pluck('email')->toArray();

                    // Call method in the Lead model
                    Lead::sendTailoredMail($emails, $subject, $body);
                })
                ->modalHeading('Send Tailored Mail')
                ->modalButton('Send')
                ->icon('heroicon-o-paper-airplane'),
            ]);
    }
}