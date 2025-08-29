<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\File;

class RequestAppointmentsFileInfo extends Widget
{
    protected static string $view = 'filament.widgets.request-appointments-file-info';

    public File $file;

    public function mount(File $file): void
    {
        $this->file = $file->load(['patient', 'serviceType', 'city', 'country']);
    }
}
