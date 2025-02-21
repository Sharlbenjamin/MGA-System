<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
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
            ]);
    }
}