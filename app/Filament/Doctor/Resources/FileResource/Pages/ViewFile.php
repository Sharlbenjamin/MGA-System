<?php

namespace App\Filament\Doctor\Resources\FileResource\Pages;

use App\Filament\Doctor\Resources\FileResource;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;

use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Infolist;

use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

use Filament\Forms;

class ViewFile extends ViewRecord
{
    protected static string $resource = FileResource::class;

    public function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\TextInput::make('mga_reference')->label('MGA Reference')->disabled(),
            Forms\Components\TextInput::make('patient.name')->label('Patient Name')->disabled(),
            Forms\Components\TextInput::make('providerBranch.branch_name')->label('Provider Branch')->disabled(),
            Forms\Components\DatePicker::make('service_date')->label('Service Date')->disabled(),
        ]);
}
}
