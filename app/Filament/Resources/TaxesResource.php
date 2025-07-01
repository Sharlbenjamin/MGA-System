<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaxesResource\Pages;
use Filament\Resources\Resource;

class TaxesResource extends Resource
{
    protected static ?string $model = null;

    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 7;
    protected static ?string $navigationIcon = 'heroicon-o-document';
    protected static ?string $navigationLabel = 'Taxes';

    public static function getRelations(): array
    {
        return [
            // No relations needed for this resource
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTaxes::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return null;
    }
} 