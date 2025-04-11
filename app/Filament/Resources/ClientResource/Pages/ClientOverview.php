<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Filament\Resources\ClientResource\RelationManagers\InvoiceRelationManager;
use App\Models\Invoice;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

class ClientOverview extends ViewRecord
{

    protected static string $resource = ClientResource::class;

    public function getTitle(): string
    {
        return $this->record->company_name;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('company_name')->label('Company Name')->weight('bold')->color('success'),
                // Operation Data
                TextEntry::make('filesCount')->label('Total Files')->color('info'),
                TextEntry::make('filesCancelledCount')->label('Total Cancelled Files')->color('info'),
                TextEntry::make('filesAssistedCount')->label('Total Assisted')->color('info'),
                //  Invoices Data
                TextEntry::make('invoicesTotalNumber')->label('Number of Invoices')->color('warning'),
                TextEntry::make('invoicesTotal')->label('Total Invoices')->color('warning')->money('eur'),
                TextEntry::make('invoicesTotalNumberPaid')->label('Number of Invoices Paid')->color('warning'),
                TextEntry::make('invoicesTotalPaid')->label('Total Invoices Paid')->color('warning')->money('eur'),
                TextEntry::make('invoicesTotalNumberOutstanding')->label('Number of Invoices Outstanding')->color('warning'),
                TextEntry::make('invoicesTotalOutstanding')->label('Total Invoices Outstanding')->color('warning')->money('eur'),
                // Transactions Data
                TextEntry::make('transactionsLastDate')->label('Last Transaction Date')->color('success'),
                TextEntry::make('transactionLastAmount')->label('Last Transaction Amount')->color('success'),

            ]);
    }



}
