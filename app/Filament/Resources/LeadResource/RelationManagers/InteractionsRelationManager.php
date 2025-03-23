<?php

namespace App\Filament\Resources\LeadResource\RelationManagers;

use App\Models\Interaction;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;

class InteractionsRelationManager extends RelationManager
{
    protected static string $relationship = 'interactions';


    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Select::make('method')->label('Contact Method')
                    ->options([
                        'Email' => 'Email',
                        'Phone' => 'Phone',
                        'WhatsApp' => 'WhatsApp',
                    ])->required(),
                Select::make('user_id')->label('User')->options(User::pluck('name', 'id'))->default(Auth::id()),
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

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('method')->label('Method')->sortable(),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
                TextColumn::make('content')->label('Interaction Notes')->limit(50),
                TextColumn::make('positive')->label('Positive?')->formatStateUsing(fn ($state) => $state ? '✅ Yes' : '❌ No')->sortable(),
                TextColumn::make('interaction_date')->label('Date')->date()->sortable(),
            ]) ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]) ->headerActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

}
