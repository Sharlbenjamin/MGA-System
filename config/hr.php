<?php

return [

    'employer' => [
        'legal_name' => env('HR_EMPLOYER_LEGAL_NAME', 'The Mid Gard for Project Management'),
        'address' => env('HR_EMPLOYER_ADDRESS'),
        'registration' => env('HR_EMPLOYER_REGISTRATION'),
        'signatory_name' => env('HR_SIGNATORY_NAME', 'Sharl Hany Moner'),
        'signatory_title' => env('HR_SIGNATORY_TITLE', 'CEO'),
    ],

    'salary_currency' => env('HR_SALARY_CURRENCY', 'EGP'),

    'logo_path' => env('HR_LOGO_PATH', 'siglogo.png'),

];
