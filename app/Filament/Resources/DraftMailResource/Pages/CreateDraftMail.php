<?php

namespace App\Filament\Resources\DraftMailResource\Pages;

use App\Filament\Resources\DraftMailResource;
use App\Models\DraftMail;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDraftMail extends CreateRecord
{
    protected static string $resource = DraftMailResource::class;

    protected function afterCreate(): void
    {
        $record = $this->record;
        
        // If this is a custom status, create a reference draft mail to make it available
        if ($record->status && !in_array($record->status, $this->getDefaultStatuses($record->type))) {
            // Check if this custom status already exists as a reference
            $existingReference = DraftMail::where('type', $record->type)
                ->where('status', $record->status)
                ->where('mail_name', 'like', 'Reference - %')
                ->first();
                
            if (!$existingReference) {
                DraftMail::create([
                    'mail_name' => 'Reference - ' . $record->status,
                    'body_mail' => 'Reference draft for custom status: ' . $record->status,
                    'status' => $record->status,
                    'type' => $record->type,
                    'new_status' => $record->status,
                ]);
            }
        }
        
        if ($record->new_status && !in_array($record->new_status, $this->getDefaultStatuses($record->type))) {
            // Check if this custom status already exists as a reference
            $existingReference = DraftMail::where('type', $record->type)
                ->where('new_status', $record->new_status)
                ->where('mail_name', 'like', 'Reference - %')
                ->first();
                
            if (!$existingReference) {
                DraftMail::create([
                    'mail_name' => 'Reference - ' . $record->new_status,
                    'body_mail' => 'Reference draft for custom status: ' . $record->new_status,
                    'status' => $record->new_status,
                    'type' => $record->type,
                    'new_status' => $record->new_status,
                ]);
            }
        }
    }

    private function getDefaultStatuses($type)
    {
        if ($type === 'Provider') {
            return [
                'Pending information', 'Step one', 'Step one sent', 'Reminder', 'Reminder sent',
                'Discount', 'Discount sent', 'Step two', 'Step two sent', 'Presentation',
                'Presentation sent', 'Contract', 'Contract sent', 'Fake Case', 'Fake Case sent',
                'Cancel Case', 'Cancel Case sent'
            ];
        } elseif ($type === 'File') {
            return ['New', 'Handling', 'Available', 'Confirmed', 'Requesting GOP', 'Assisted', 'Hold', 'Void'];
        } else { // Client
            return [
                'Introduction', 'Introduction Sent', 'Reminder', 'Reminder Sent', 'Presentation',
                'Presentation Sent', 'Price List', 'Price List Sent', 'Contract', 'Contract Sent',
                'Interested', 'Error', 'Partner', 'Rejected'
            ];
        }
    }
}
