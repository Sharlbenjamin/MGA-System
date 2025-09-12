<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ServiceType;

class ServiceTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $serviceTypes = [
            'General Practice',
            'Emergency',
            'Pediatrician Emergency',
            'Dental',
            'Pediatrician',
            'Gynecology',
            'Urology',
            'Cardiology',
            'Ophthalmology',
            'Trauma / Orthopedics',
            'Surgery',
            'Intensive Care',
            'Obstetrics / Delivery',
            'Hyperbaric Chamber',
            'Dermatology',
            'Neurology',
            'Psychiatry',
            'Radiology',
            'Laboratory',
            'Pharmacy',
        ];

        foreach ($serviceTypes as $serviceType) {
            ServiceType::firstOrCreate(['name' => $serviceType]);
        }
    }
}
