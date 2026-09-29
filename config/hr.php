<?php

return [

    'employer' => [
        'legal_name' => env('HR_EMPLOYER_LEGAL_NAME'),
        'address' => env('HR_EMPLOYER_ADDRESS'),
        'registration' => env('HR_EMPLOYER_REGISTRATION'),
        'signatory_name' => env('HR_SIGNATORY_NAME'),
        'signatory_title' => env('HR_SIGNATORY_TITLE'),
    ],

    'salary_currency' => env('HR_SALARY_CURRENCY'),

    'logo_path' => env('HR_LOGO_PATH', 'siglogo.png'),

];
