<?php

namespace App\Filament\Resources\ProviderBranchResource\Pages;

use App\Filament\Resources\ProviderBranchResource;
use App\Filament\Resources\ProviderBranchResource\RelationManagers\InvoiceRelationManager;
use App\Models\Invoice;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

class BranchOverView extends ViewRecord
{

    protected static string $resource = ProviderBranchResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('name')->label('Provider Name')->weight('bold')->color('success'),
                // Operation Data
                TextEntry::make('filesCount')->label('Total Files')->color('info'),
                TextEntry::make('filesCancelledCount')->label('Total Cancelled Files')->color('info'),
                TextEntry::make('filesAssistedCount')->label('Total Assisted')->color('info'),
                //  Invoices Data
                TextEntry::make('billsTotalNumber')->label('Number of Bills')->color('warning'),
                TextEntry::make('billsTotal')->label('Total Bills')->color('warning')->money('eur'),
                TextEntry::make('billsTotalNumberPaid')->label('Number of Bills Paid')->color('warning'),
                TextEntry::make('billsTotalPaid')->label('Total Bills Paid')->color('warning')->money('eur'),
                TextEntry::make('billsTotalNumberOutstanding')->label('Number of Bills Outstanding')->color('warning'),
                TextEntry::make('billsTotalOutstanding')->label('Total Bills Outstanding')->color('warning')->money('eur'),
                // Transactions Data
                TextEntry::make('transactionsLastDate')->label('Last Transaction Date')->date('d-m-Y')->color('success'),
                TextEntry::make('transactionLastAmount')->label('Last Transaction Amount')->color('success')->money('eur'),

            ]);
    }



}
