<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Card;
use Filament\Infolists\Infolist;

class CommentsWidget extends Widget
{
    protected static string $view = 'filament.widgets.comments-widget';

    protected int | string | array $columnSpan = 'full';

    public $record;

    public function mount($record)
    {
        $this->record = $record;
    }
}
