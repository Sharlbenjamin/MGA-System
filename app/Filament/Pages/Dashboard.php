<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 1;
    use HasFiltersForm;

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Select::make('monthYearFilter')
                            ->label('Filter Stats per ')
                            ->options([
                                'Month' => 'Month',
                                'Year' => 'Year'
                            ])
                            ->required()
                            ->default('Month')
                            ->searchable(),
                            Select::make('monthFilter')
                            ->label('Select Month')
                            ->options([
                                'Jan' => 'Jan' ,
                                'Feb' => 'Feb' ,
                                'Mar' => 'Mar' ,
                                'Apr' => 'Apr' ,
                                'May' => 'May' ,
                                'Jun' => 'Jun' ,
                                'Jul' => 'Jul' ,
                                'Aug' => 'Aug' ,
                                'Sep' => 'Sep' ,
                                'Oct' => 'Oct' ,
                                'Nov' => 'Nov' ,
                                'Dec' => 'Dec' ,
                            ])
                            ->required()
                            ->default('Month')
                            ->searchable()
                            ->visible(fn ($get) => $get('monthYearFilter') === 'Month')
                    ])
                    ->columns(3),
                    
            ]);
    }

}
