<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@coffeeshop.test'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'manager@coffeeshop.test'],
            [
                'name' => 'Manager User',
                'password' => Hash::make('password'),
                'role' => 'manager',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'cashier@coffeeshop.test'],
            [
                'name' => 'Cashier User',
                'password' => Hash::make('password'),
                'role' => 'cashier',
                'email_verified_at' => now(),
            ]
        );
    }
}

//Guidelines for activating the seeder
//Bash : php artisan db:seed
//Bash : php artisan db:seed --class=UserSeeder