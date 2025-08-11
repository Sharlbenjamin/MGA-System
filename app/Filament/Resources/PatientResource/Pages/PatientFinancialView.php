<?php

namespace App\Filament\Resources\PatientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Filament\Resources\FileResource;
use Illuminate\Support\Facades\Log;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\BadgeEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Infolist;
use App\Filament\Resources\PatientResource;
use Filament\Infolists\Components\Card;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Facades\View;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\Heading;
use Filament\Infolists\Components\Actions\Action as InfolistAction;

class PatientFinancialView extends ViewRecord
{
    protected static string $resource = PatientResource::class;

    public function getTitle(): string
    {
        return "Financial Overview - {$this->record->name}";
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Patient Information')
                    ->schema([
                        Card::make()
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Patient Name')
                                    ->size('lg')
                                    ->weight('bold')
                                    ->color('primary'),
                                TextEntry::make('client.company_name')
                                    ->label('Client')
                                    ->weight('bold')
                                    ->color('success')
                                    ->url(fn ($record) => ClientResource::getUrl('overview', ['record' => $record->client_id])),
                                TextEntry::make('age_formatted')
                                    ->label('Age')
                                    ->color('info'),
                                TextEntry::make('gender')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'Male' => 'info',
                                        'Female' => 'warning',
                                        default => 'gray',
                                    }),
                                TextEntry::make('country.name')
                                    ->label('Country')
                                    ->color('gray'),
                                TextEntry::make('files_count')
                                    ->label('Total Files')
                                    ->badge()
                                    ->color('success'),
                            ])
                            ->columns(3),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Financial Summary')
                    ->schema([
                        Card::make()
                            ->schema([
                                TextEntry::make('invoices_total')
                                    ->label('Total Invoices')
                                    ->weight('bold')
                                    ->color('danger')
                                    ->money('USD'),
                                TextEntry::make('invoices_total_paid')
                                    ->label('Paid Invoices')
                                    ->weight('bold')
                                    ->color('success')
                                    ->money('USD'),
                                TextEntry::make('invoices_total_outstanding')
                                    ->label('Outstanding Invoices')
                                    ->weight('bold')
                                    ->color('warning')
                                    ->money('USD'),
                            ])
                            ->columns(3)
                            ->columnSpan(1),

                        Card::make()
                            ->schema([
                                TextEntry::make('bills_total')
                                    ->label('Total Bills')
                                    ->weight('bold')
                                    ->color('danger')
                                    ->money('USD'),
                                TextEntry::make('bills_total_paid')
                                    ->label('Paid Bills')
                                    ->weight('bold')
                                    ->color('success')
                                    ->money('USD'),
                                TextEntry::make('bills_total_outstanding')
                                    ->label('Outstanding Bills')
                                    ->weight('bold')
                                    ->color('warning')
                                    ->money('USD'),
                            ])
                            ->columns(3)
                            ->columnSpan(1),

                        Card::make()
                            ->schema([
                                TextEntry::make('profit_total')
                                    ->label('Total Profit')
                                    ->weight('bold')
                                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                                    ->money('USD'),
                                TextEntry::make('profit_total_paid')
                                    ->label('Paid Profit')
                                    ->weight('bold')
                                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                                    ->money('USD'),
                                TextEntry::make('profit_total_outstanding')
                                    ->label('Outstanding Profit')
                                    ->weight('bold')
                                    ->color(fn ($state) => $state >= 0 ? 'warning' : 'danger')
                                    ->money('USD'),
                            ])
                            ->columns(3)
                            ->columnSpan(1),
                    ])
                    ->columns(3),

                Section::make('Recent Activity')
                    ->schema([
                        Card::make()
                            ->schema([
                                TextEntry::make('recent_files')
                                    ->label('Recent Files')
                                    ->listWithLineBreaks()
                                    ->getStateUsing(function ($record) {
                                        $recentFiles = $record->getRecentFiles(5);
                                        return $recentFiles->map(function ($file) {
                                            return "{$file->mga_reference} - {$file->status} ({$file->created_at->format('M j, Y')})";
                                        })->toArray();
                                    })
                                    ->color('info'),
                            ])
                            ->columnSpan(1),

                        Card::make()
                            ->schema([
                                TextEntry::make('financial_status')
                                    ->label('Financial Status')
                                    ->badge()
                                    ->getStateUsing(function ($record) {
                                        if ($record->hasOutstandingFinancials()) {
                                            return 'Has Outstanding Amounts';
                                        }
                                        return 'All Paid';
                                    })
                                    ->color(fn ($state) => $state === 'Has Outstanding Amounts' ? 'warning' : 'success'),
                            ])
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit_patient')
                ->label('Edit Patient')
                ->icon('heroicon-o-pencil')
                ->url(fn () => PatientResource::getUrl('edit', ['record' => $this->record]))
                ->color('primary'),
            Action::make('view_files')
                ->label('View All Files')
                ->icon('heroicon-o-clipboard-document-list')
                ->url(fn () => PatientResource::getUrl('index', ['tableFilters[client_id][value]' => $this->record->client_id]))
                ->openUrlInNewTab()
                ->color('success'),
            Action::make('export_financial_report')
                ->label('Export Report')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    // Export logic would go here
                    Notification::make()
                        ->success()
                        ->title('Export Started')
                        ->body('Financial report export has been initiated.')
                        ->send();
                })
                ->color('info'),
            Action::make('ViewFile')
                ->label('View File')
                ->icon('heroicon-o-document-text')
                ->url(fn () => FileResource::getUrl('view', [
                    'record' => request()->query('file_id')
                ]))
                ->visible(fn() => request()->has('file_id'))
                ->openUrlInNewTab(false)
                ->color('warning'),
        ];
    }
}
