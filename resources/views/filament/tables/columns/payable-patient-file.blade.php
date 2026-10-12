@php
    use App\Filament\Resources\FileResource;
    use App\Filament\Resources\PatientResource;

    $record = $getRecord();
    $patient = $record->file?->patient;
    $file = $record->file;
    $patientUrl = $patient ? PatientResource::getUrl('edit', ['record' => $patient]) : null;
    $driveUrl = filled($file?->google_drive_link) && filter_var($file->google_drive_link, FILTER_VALIDATE_URL)
        ? $file->google_drive_link
        : null;
    $fileUrl = $file
        ? ($driveUrl ?: FileResource::getUrl('view', ['record' => $file]))
        : null;
@endphp

<div class="grid w-full gap-y-0.5 px-3 py-4">
    @if ($patient && $patientUrl)
        <a
            {{ \Filament\Support\generate_href_html($patientUrl) }}
            class="text-sm font-medium leading-6 text-primary-600 hover:underline dark:text-primary-400"
        >
            {{ $patient->name }}
        </a>
    @else
        <span class="text-sm leading-6 text-gray-400 dark:text-gray-500">—</span>
    @endif

    @if ($file && filled($file->mga_reference) && $fileUrl)
        <a
            {{ \Filament\Support\generate_href_html($fileUrl, filled($driveUrl)) }}
            @if (filled($driveUrl)) rel="noopener noreferrer" @endif
            class="text-sm leading-5 text-primary-600 hover:underline dark:text-primary-400"
        >
            {{ $file->mga_reference }}
        </a>
    @else
        <span class="text-sm leading-5 text-gray-400 dark:text-gray-500">—</span>
    @endif
</div>
