<?php

namespace App\Filament\Resources\PrescriptionResource\Pages;

use App\Filament\Resources\PrescriptionResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Components\Card;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;

use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;

class ViewPrescription extends ViewRecord
{
    protected static string $resource = PrescriptionResource::class;

    
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Prescription Details')
                    ->schema([
                        Grid::make(2) // Create a two-column layout
                            ->schema([
                                TextEntry::make('serial')
                                    ->label('Serial')
                                    ->weight('bold')
                                    ->size('lg'),

                                
                            ]),

                        TextEntry::make('created_at')
                            ->label('Created On')
                            ->dateTime('F j, Y'),
                    ])
                    ->columns(2),
            ]);
    }
}