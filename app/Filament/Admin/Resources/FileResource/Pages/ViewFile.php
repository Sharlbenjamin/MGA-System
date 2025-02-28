<?php

namespace App\Filament\Admin\Resources\FileResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\BadgeEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Infolist;
use App\Filament\Admin\Resources\FileResource;

class ViewFile extends ViewRecord
{
    protected static string $resource = FileResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('File Details')
                    ->schema([
                        Grid::make(2) // Create a two-column layout
                            ->schema([
                                TextEntry::make('mga_reference')
                                    ->label('MGA Reference')
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