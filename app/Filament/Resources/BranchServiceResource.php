<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BranchServiceResource\Pages;
use App\Filament\Resources\BranchServiceResource\RelationManagers;
use App\Models\BranchService;
use App\Models\ProviderBranch;
use App\Models\ServiceType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;

class BranchServiceResource extends Resource
{
    protected static ?string $model = BranchService::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    
    protected static ?string $navigationLabel = 'Branch Services';
    
    protected static ?string $modelLabel = 'Branch Service';
    
    protected static ?string $pluralModelLabel = 'Branch Services';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Branch Service Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('provider_branch_id')
                                    ->label('Provider Branch')
                                    ->options(ProviderBranch::with('provider')->get()->mapWithKeys(function ($branch) {
                                        return [$branch->id => $branch->provider->name . ' - ' . $branch->branch_name];
                                    }))
                                    ->searchable()
                                    ->required()
                                    ->reactive(),

                                Select::make('service_type_id')
                                    ->label('Service Type')
                                    ->options(ServiceType::pluck('name', 'id'))
                                    ->searchable()
                                    ->required(),
                            ]),

                        Section::make('Cost Information')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('min_cost')
                                            ->label('Minimum Cost')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->nullable(),

                                        TextInput::make('max_cost')
                                            ->label('Maximum Cost')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->nullable(),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('providerBranch.provider.name')
                    ->label('Provider')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('providerBranch.branch_name')
                    ->label('Branch')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('serviceType.name')
                    ->label('Service Type')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('min_cost')
                    ->label('Minimum Cost')
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('max_cost')
                    ->label('Maximum Cost')
                    ->money('USD')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('provider')
                    ->label('Provider')
                    ->relationship('providerBranch.provider', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('service_type')
                    ->label('Service Type')
                    ->relationship('serviceType', 'name')
                    ->searchable()
                    ->preload(),

            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBranchServices::route('/'),
            'create' => Pages\CreateBranchService::route('/create'),
            'edit' => Pages\EditBranchService::route('/{record}/edit'),
        ];
    }
}
