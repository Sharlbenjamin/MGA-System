<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\File;
use Illuminate\Support\Facades\Auth;

class RequestAppointmentsFileInfo extends Widget
{
    public static function shouldLoad(): bool
    {
        return Auth::user()?->roles->contains('name', 'admin') ?? false;
    }
    protected static string $view = 'filament.widgets.request-appointments-file-info';

    public File $file;

    public function mount(File $file): void
    {
        $this->file = $file->load(['patient', 'serviceType', 'city', 'country']);
    }
}
