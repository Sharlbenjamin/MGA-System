<?php

namespace Database\Seeders;

use App\Models\JobTitle;
use Illuminate\Database\Seeder;

class JobTitleSeeder extends Seeder
{
    public function run(): void
    {
        $titles = [
            [
                'name' => 'Operation Team Member',
                'code' => 'OPS_TM',
                'level' => 1,
                'department' => 'Operation',
                'bonus_multiplier' => 1.0,
            ],
            [
                'name' => 'Operation Team Leader',
                'code' => 'OPS_TL',
                'level' => 2,
                'department' => 'Operation',
                'bonus_multiplier' => 1.5,
            ],
            [
                'name' => 'Operation Country Manager',
                'code' => 'OPS_CM',
                'level' => 2,
                'department' => 'Operation',
                'bonus_multiplier' => 1.5,
            ],
            [
                'name' => 'Operation Manager',
                'code' => 'OPS_MGR',
                'level' => 3,
                'department' => 'Operation',
                'bonus_multiplier' => 2.0,
            ],
        ];

        foreach ($titles as $title) {
            JobTitle::firstOrCreate(
                ['code' => $title['code']],
                $title
            );
        }
    }
}
