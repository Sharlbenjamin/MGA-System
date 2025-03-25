<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\File;

class CommentsWidget extends Widget
{
    public ?File $record = null;

    protected static string $view = 'filament.widgets.comments-widget';

    public static function canView(): bool
    {
        return true;
    }

    public function getComments()
    {
        return $this->record?->comments ?? collect();
    }
}
