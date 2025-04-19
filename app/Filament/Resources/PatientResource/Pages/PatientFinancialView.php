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
use App\Mail\AppointmentRequestMail;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\Heading;

class PatientFinancialView extends ViewRecord
{
    protected static string $resource = PatientResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make()
                    ->schema([
                        Section::make()->schema([
                            Card::make()
                                ->schema([
                                    TextEntry::make('files.mga_reference')->label('MGA Reference')->color('warning')->weight('bold')->size('lg'),
                                    TextEntry::make('files.service_date')->label('File Date')->color('warning'),
                                    TextEntry::make('client.company_name')->label('Client Name')->weight('bold')->color('success')->url(fn ($record) => ClientResource::getUrl('overview', ['record' => $record->client_id]))->weight('underline'),
                                    TextEntry::make('filesCount')->label('Files')->weight('bold')->color('info'),
                                ])->columns(4)->columnSpan(3),
                            Card::make()
                                ->schema([
                                    TextEntry::make('Invocie Details')->label(false)->state('Invoices')->size('xl')->weight('bold')->color('success')->alignment('center')->columnSpanFull(),
                                    TextEntry::make('invoices_total')->label('Total')->weight('bold')->color('danger')->money('eur'),
                                    TextEntry::make('invoices_total_paid')->label('Paid')->weight('bold')->color('success')->money('eur'),
                                    TextEntry::make('invoices_total_outstanding')->label('Outstanding')->weight('bold')->color('warning')->money('eur'),
                                ])
                                ->columns(3)
                                ->columnSpan(1),
                            Card::make()
                            ->schema([
                                TextEntry::make('Invocie Details')->label(false)->state('Bills')->size('xl')->weight('bold')->color('warning')->alignment('center')->columnSpanFull(),
                                TextEntry::make('bills_total')->label('Total')->weight('bold')->color('danger')->money('eur'),
                                TextEntry::make('bills_total_paid')->label('Paid')->weight('bold')->color('success')->money('eur'),
                                TextEntry::make('bills_total_outstanding')->label('Outstanding')->weight('bold')->color('warning')->money('eur'),
                                ])->columns(3)->columnSpan(1),
                            Card::make()
                            ->schema([
                                TextEntry::make('Invocie Details')->label(false)->state('Profit & Loss')->size('xl')->weight('bold')->color('info')->alignment('center')->columnSpanFull(),
                                TextEntry::make('profit_total')->label('Total')->weight('bold')->color('danger')->money('eur'),
                                TextEntry::make('profit_total_paid')->label('Paid')->weight('bold')->color('success')->money('eur'),
                                TextEntry::make('profit_total_outstanding')->label('Outstanding')->weight('bold')->color('warning')->money('eur'),
                            ])->columns(3)->columnSpan(1),
                        ])->columns(3)
                    ])
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ViewFile')
                ->label('View File')
                ->icon('heroicon-o-document-text')
                ->url(fn () => FileResource::getUrl('view', [
                    'record' => request()->query('file_id')
                ]))
                ->visible(fn() => request()->has('file_id'))
                ->openUrlInNewTab(false)->color('primary'),
        ];
    }
}
