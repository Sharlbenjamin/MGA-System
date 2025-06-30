<?php

return [
    'temporary_file_upload' => [
        'disk' => 'public',
        'directory' => 'livewire-temp',
        'rules' => ['file', 'mimes:png,jpg,jpeg,pdf', 'max:10240'],
        'preview_mimes' => ['image/png', 'image/jpeg', 'image/jpg'],
        'preserve_filenames' => false,
        'max_upload_time' => 5,
        'middleware' => null,
        'cleanup_interval' => 60 * 24, // 24 hours
        'preview_mimes' => [], // Disable preview to avoid tmpfile() calls
    ],
];