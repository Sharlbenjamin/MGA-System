<?php

return [
    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_DISK', 'local'),
        'rules' => ['file', 'mimes:png,jpg,jpeg,pdf', 'max:2048'],
        'directory' => storage_path('app/livewire-temp'), // ✅ Force Livewire to use this directory
        'preview_mimes' => ['image/png', 'image/jpeg'],
        'preserve_filenames' => true,
    ],
];
