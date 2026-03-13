<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'aulaayoune@gmail.com'],
            [
                'name'     => 'System Administrator',
                'password' => Hash::make('aulaayoune@gmail.com'),
                'role'     => 'admin',
            ]
        );
    }
}
