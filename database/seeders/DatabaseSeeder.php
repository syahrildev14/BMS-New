<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('12341234'),
            ]
        );

        User::updateOrCreate(
            ['email' => 'user@deevatech.my.id'],
            [
                'name' => 'User',
                'password' => Hash::make('User123!'),
            ]
        );
    }
}
