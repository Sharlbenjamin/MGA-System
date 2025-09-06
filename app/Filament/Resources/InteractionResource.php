<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InteractionResource\Pages;
use App\Models\Interaction;
use App\Models\Lead;
use App\Models\ProviderLead;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;

class InteractionResource extends Resource
{
    protected static ?string $model = Interaction::class;
    protected static ?string $navigationGroup = 'CRM';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Select::make('lead_id')->label('Lead')->options(Lead::pluck('first_name', 'id'))->searchable()->nullable(),
                Select::make('provider_lead_id')->label('Provider Lead')->options(ProviderLead::pluck('name', 'id'))->searchable()->nullable(),
                Select::make('user_id')->label('User')->options(User::pluck('name', 'id'))->default(Auth::id())->disabled(),
                Select::make('method')->label('Contact Method')
                    ->options([
                        'Email' => 'Email',
                        'Phone' => 'Phone',
                        'WhatsApp' => 'WhatsApp',
                    ])->required(),
                Select::make('status')->label('Status at Contact')
                    ->options([
                        'Pending information' => 'Pending Information',
                        'Step one' => 'Step One',
                        'Step one sent' => 'Step One Sent',
                        'Reminder' => 'Reminder',
                        'Reminder sent' => 'Reminder Sent',
                        'Discount' => 'Discount',
                        'Discount sent' => 'Discount Sent',
                        'Step two' => 'Step Two',
                        'Step two sent' => 'Step Two Sent',
                        'Presentation' => 'Presentation',
                        'Presentation sent' => 'Presentation Sent',
                        'Contract' => 'Contract',
                        'Contract sent' => 'Contract Sent',
                    ])->required(),
                Textarea::make('content')->label('Interaction Notes')->nullable(),
                Toggle::make('positive')->label('Was the interaction positive?')->inline(false),
                DatePicker::make('interaction_date')->label('Interaction Date')->default(now()),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('lead.first_name')->label('Lead')->sortable()->searchable(),
                TextColumn::make('providerLead.name')->label('Provider Lead')->sortable()->searchable(),
                TextColumn::make('user.name')->label('User')->sortable()->searchable(),
                TextColumn::make('method')->label('Method')->sortable(),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
                TextColumn::make('content')->label('Interaction Notes')->limit(50),
                TextColumn::make('positive')->label('Positive?')->formatStateUsing(fn ($state) => $state ? '✅ Yes' : '❌ No')->sortable(),
                TextColumn::make('interaction_date')->label('Date')->date()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('method')
                    ->label('Contact Method')
                    ->options([
                        'Email' => 'Email',
                        'Phone' => 'Phone',
                        'WhatsApp' => 'WhatsApp',
                    ]),

                Tables\Filters\SelectFilter::make('positive')
                    ->label('Positive Interaction?')
                    ->options([
                        true => 'Yes',
                        false => 'No',
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInteractions::route('/'),
            'create' => Pages\CreateInteraction::route('/create'),
            'edit' => Pages\EditInteraction::route('/{record}/edit'),
        ];
    }
}
