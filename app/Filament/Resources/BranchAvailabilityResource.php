<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BranchAvailabilityResource\Pages;
use App\Models\ProviderBranch;
use Filament\Resources\Resource;

class BranchAvailabilityResource extends Resource
{
    protected static ?string $model = ProviderBranch::class;

    protected static ?string $navigationGroup = 'Ops';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationLabel = 'Branch Availability';
    protected static ?string $slug = 'branch-availability';
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\BranchAvailabilityIndex::route('/'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }
}
