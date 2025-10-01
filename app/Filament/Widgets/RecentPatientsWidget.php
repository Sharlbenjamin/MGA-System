<?php

namespace App\Filament\Widgets;

use App\Models\Patient;
use App\Filament\Resources\PatientResource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Auth;

class RecentPatientsWidget extends BaseWidget
{
    public static function shouldLoad(): bool
    {
        return Auth::user()?->roles->contains('name', 'admin') ?? false;
    }
    protected static ?string $heading = 'Recent Patients';

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Patient::query()
                    ->with(['client', 'country'])
                    ->withCount('files')
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Patient Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('client.company_name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('age_formatted')
                    ->label('Age')
                    ->sortable(),
                TextColumn::make('gender')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Male' => 'info',
                        'Female' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('files_count')
                    ->label('Files')
                    ->badge()
                    ->color('success')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->actions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->url(fn (Patient $record): string => PatientResource::getUrl('edit', ['record' => $record]))
                    ->color('primary'),
                Action::make('view_files')
                    ->label('Files')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->url(fn (Patient $record): string => PatientResource::getUrl('index', ['tableFilters[client_id][value]' => $record->client_id]))
                    ->openUrlInNewTab()
                    ->color('success'),
                Action::make('financial')
                    ->label('Financial')
                    ->icon('heroicon-o-currency-dollar')
                    ->url(fn (Patient $record): string => PatientResource::getUrl('financial', ['record' => $record]))
                    ->openUrlInNewTab()
                    ->color('warning'),
            ])
            ->paginated(false);
    }
} 