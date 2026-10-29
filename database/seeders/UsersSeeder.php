<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'id' => 1,
            'name' => env('SEED_ADMIN_NAME', 'Admin'),
            'email' => env('SEED_ADMIN_EMAIL', 'admin@example.com'),
            'password' => Hash::make(env('SEED_ADMIN_PASSWORD', 'password')),
            'smtp_username' => env('SEED_ADMIN_SMTP_USERNAME'),
            'smtp_password' => env('SEED_ADMIN_SMTP_PASSWORD'),
        ]);
    }
}
