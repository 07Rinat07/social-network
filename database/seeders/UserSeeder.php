<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin',
                'email' => 'admin@example.com',
            ],
            [
                'name' => 'Test User 1',
                'email' => 'user1@example.com',
            ],
            [
                'name' => 'Test User 2',
                'email' => 'user2@example.com',
            ],
            [
                'name' => 'Test User 3',
                'email' => 'user3@example.com',
            ],
            [
                'name' => 'Test User 4',
                'email' => 'user4@example.com',
            ],
            [
                'name' => 'Test User 5',
                'email' => 'user5@example.com',
            ],
        ];

        foreach ($users as $userData) {
            User::query()->updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'email_verified_at' => now(),
                    'is_admin' => $userData['email'] === 'admin@example.com',
                    'password' => Hash::make('password'),
                ]
            );
        }
    }
}
