<?php

return [

    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', true),
        'default_country_code' => env('WHATSAPP_DEFAULT_COUNTRY_CODE', '353'),
    ],

    'email' => [
        'enabled' => env('COMMUNICATIONS_EMAIL_ENABLED', false),
    ],

];
