<?php

return [
    /*
    |--------------------------------------------------------------------------
    | UK country for file fee pricing (file service country)
    |--------------------------------------------------------------------------
    */
    'uk_country_names' => [
        'United Kingdom',
        'UK',
    ],

    /*
    |--------------------------------------------------------------------------
    | Multiplier strategy cap (€ per Simple file fee unit)
    |--------------------------------------------------------------------------
    */
    'multiplier_cap' => 350,

    /*
    |--------------------------------------------------------------------------
    | Combined invoice line description (PDF / email display only)
    |--------------------------------------------------------------------------
    */
    'combined_line_description' => 'Medical assistance services',

    'file_fee_tiers' => [
        'simple' => [
            'service_type_names' => [
                'Simple',
                'Simple File Fee',
            ],
            'max_total' => 350,
        ],
        'middle' => [
            'service_type_names' => [
                'Middle',
                'Middle File Fee',
                'Inpatient / Multiple File Fees Cases',
            ],
            'min_total' => 350,
            'max_total' => 1000,
        ],
        'complex' => [
            'service_type_names' => [
                'Complex',
                'Complex File Fee',
            ],
            'min_total' => 1000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Accepted GOP In offer vs invoice variance warnings
    |--------------------------------------------------------------------------
    */
    'internal_offer_variance' => [
        'amount_eur' => 25,
        'percent' => 10,
    ],
];
