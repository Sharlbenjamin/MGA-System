<?php

return [
    /*
    |--------------------------------------------------------------------------
    | File fee tiers by country (cost of items, excluding fees)
    |--------------------------------------------------------------------------
    |
    | Level 1 (simple): total cost does not exceed 350
    | Level 2 (middle): total cost exceeds 350
    | Level 3 (complex): total cost exceeds 1000 (no further cap)
    |
    */
    'file_fee_thresholds' => [
        'middle' => 350,
        'complex' => 1000,
    ],

    'file_fee_amounts' => [
        'uk' => [
            'simple' => 85,
            'middle' => 200,
            'complex' => 350,
        ],
        'greece' => [
            'simple' => 70,
            'middle' => 180,
            'complex' => 320,
        ],
        'default' => [
            'simple' => 50,
            'middle' => 150,
            'complex' => 300,
        ],
    ],

    'country_names' => [
        'uk' => [
            'United Kingdom',
            'UK',
            'Great Britain',
            'UNITED KINGDOM',
        ],
        'greece' => [
            'Greece',
            'GREECE',
            'Hellas',
            'GR',
        ],
    ],

    'country_iso' => [
        'uk' => ['GB', 'GBR', 'UK'],
        'greece' => ['GR', 'GRC'],
    ],

    'selling_cost_multiplier' => 2,

    'house_visit' => [
        'merged_label' => 'Cost & GOP',
        'service_type_names' => [
            'House Call',
            'House Visit',
            'House visit',
        ],
    ],

    'telemedicine' => [
        'uk' => 85,
        'default' => 75,
        'service_type_names' => [
            'Telemedicine',
        ],
    ],

    'offer_sections' => [
        'mga_reference' => 'MGA reference',
        'patient_name' => 'Patient name',
        'address' => 'Address',
        'provider' => 'Provider',
        'provider_address' => 'Provider address',
        'service_type' => 'Service type',
        'date_time' => 'Date & time',
        'items' => 'Items',
        'total' => 'Total',
    ],

    'default_offer_sections' => [
        'mga_reference',
        'patient_name',
        'address',
        'provider',
        'provider_address',
        'service_type',
        'date_time',
        'items',
        'total',
    ],
];
