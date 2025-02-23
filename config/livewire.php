<?php

return [
    'temporary_file_upload' => [
        'disk' => 'local', // ✅ Use local disk instead of tmpfile()
        'directory' => storage_path('app/livewire-temp'), // ✅ Set custom temp folder
        'rules' => ['file', 'mimes:png,jpg,jpeg,pdf', 'max:2048'],
        'preview_mimes' => ['image/png', 'image/jpeg'],
        'preserve_filenames' => true,
    ],
];