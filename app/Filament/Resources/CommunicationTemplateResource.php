<?php

namespace App\Filament\Resources;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationContextType;
use App\Filament\Resources\CommunicationTemplateResource\Pages;
use App\Models\CommunicationTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CommunicationTemplateResource extends Resource
{
    protected static ?string $model = CommunicationTemplate::class;

    protected static ?string $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Communication Templates';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('key')
                ->required()
                ->maxLength(255)
                ->helperText('Stable identifier, e.g. appointment_request'),
            Forms\Components\Select::make('context_type')
                ->options(collect(CommunicationContextType::cases())->mapWithKeys(
                    fn (CommunicationContextType $type) => [$type->value => $type->label()]
                ))
                ->required(),
            Forms\Components\Select::make('channel')
                ->options(collect(CommunicationChannel::cases())->mapWithKeys(
                    fn (CommunicationChannel $channel) => [$channel->value => $channel->label()]
                ))
                ->required(),
            Forms\Components\Toggle::make('is_active')
                ->default(true),
            Forms\Components\TextInput::make('subject_template')
                ->maxLength(255)
                ->helperText('Optional. Reserved for a future email channel; unused while WhatsApp-only.'),
            Forms\Components\Textarea::make('body_template')
                ->required()
                ->rows(16)
                ->columnSpanFull()
                ->helperText('Use {{ provider.name }}, {{ case.reference }}, {{ missing_files_table }}, etc.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('key')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('context_type')
                    ->label('Context')
                    ->formatStateUsing(fn ($state) => $state instanceof CommunicationContextType ? $state->label() : (string) $state),
                Tables\Columns\TextColumn::make('channel')
                    ->formatStateUsing(fn ($state) => $state instanceof CommunicationChannel ? $state->label() : (string) $state),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommunicationTemplates::route('/'),
            'create' => Pages\CreateCommunicationTemplate::route('/create'),
            'edit' => Pages\EditCommunicationTemplate::route('/{record}/edit'),
        ];
    }
}
