<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Resources\LeadResource;
use App\Filament\Resources\LeadResource\Widgets\ClientLeadsOverviewWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('Send Email')
            ->action(fn ($record) => $this->sendEmailToLead($record))
            ->requiresConfirmation(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ClientLeadsOverviewWidget::class,
        ];
    }
}
