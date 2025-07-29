<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create sample customers
        $customers = [
            [
                'name' => 'Achmad Setiawan',
                'email' => 'achmad@example.com',
                'phone' => '+62 812-3456-7890',
                'password' => Hash::make('password123'),
                'role' => 'customer',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Wahyu Pratama',
                'email' => 'wahyu@example.com', 
                'phone' => '+62 813-4567-8901',
                'password' => Hash::make('password123'),
                'role' => 'customer',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Fauzan Abdullah',
                'email' => 'fauzan@example.com',
                'phone' => '+62 814-5678-9012', 
                'password' => Hash::make('password123'),
                'role' => 'customer',
                'email_verified_at' => null, // Inactive user
            ],
            [
                'name' => 'Siti Rahayu',
                'email' => 'siti@example.com',
                'phone' => '+62 815-7890-1234',
                'password' => Hash::make('password123'),
                'role' => 'customer',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Budi Santoso',
                'email' => 'budi@example.com',
                'phone' => '+62 816-8901-2345',
                'password' => Hash::make('password123'),
                'role' => 'customer',
                'email_verified_at' => now(),
            ],
        ];

        foreach ($customers as $customer) {
            User::updateOrCreate(
                ['email' => $customer['email']], 
                $customer
            );
        }
    }
}
