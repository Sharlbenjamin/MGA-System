<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'id' => 1,
            'name' => 'sharlhany',
            'email' => 'sharlhany@gmail.com',
            'password' => Hash::make('Sharl@1234'),
            'smtp_username' => 'c.benjamin@medguarda.com',
            'smtp_password' => 'omgythxfewapbiit'
        ]);
    }
}
