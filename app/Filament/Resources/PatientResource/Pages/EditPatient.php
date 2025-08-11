<?php

namespace App\Filament\Resources\PatientResource\Pages;

use App\Filament\Resources\PatientResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditPatient extends EditRecord
{
    protected static string $resource = PatientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_files')
                ->label('View Files')
                ->icon('heroicon-o-clipboard-document-list')
                ->url(fn () => PatientResource::getUrl('index', ['tableFilters[client_id][value]' => $this->record->client_id]))
                ->openUrlInNewTab(),
            Actions\Action::make('financial_view')
                ->label('Financial View')
                ->icon('heroicon-o-currency-dollar')
                ->url(fn () => PatientResource::getUrl('financial', ['record' => $this->record]))
                ->openUrlInNewTab(),
            Actions\Action::make('duplicate_patient')
                ->label('Duplicate Patient')
                ->icon('heroicon-o-document-duplicate')
                ->action(function () {
                    $newPatient = $this->record->replicate();
                    $newPatient->name = $this->record->name . ' (Copy)';
                    $newPatient->save();
                    
                    Notification::make()
                        ->success()
                        ->title('Patient Duplicated')
                        ->body("Patient '{$newPatient->name}' has been created successfully.")
                        ->send();
                        
                    return redirect()->to(PatientResource::getUrl('edit', ['record' => $newPatient]));
                })
                ->requiresConfirmation()
                ->modalHeading('Duplicate Patient')
                ->modalDescription('This will create a copy of this patient with "(Copy)" appended to the name. Continue?')
                ->modalSubmitActionLabel('Duplicate'),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
