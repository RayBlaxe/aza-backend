<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create default admin user if it doesn't exist
        $adminUser = User::where('email', 'admin@26store.com')->first();

        if (! $adminUser) {
            User::create([
                'name' => 'Admin 26Store',
                'email' => 'admin@26store.com',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]);

            $this->command->info('Default admin user created:');
            $this->command->info('Email: admin@26store.com');
            $this->command->info('Password: admin123');
        } else {
            $this->command->info('Admin user already exists.');
        }

        // Create a few more sample admin users for testing
        $sampleAdmins = [
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@26store.com',
                'password' => Hash::make('superadmin123'),
                'role' => 'admin',
            ],
            [
                'name' => 'Store Manager',
                'email' => 'manager@26store.com',
                'password' => Hash::make('manager123'),
                'role' => 'admin',
            ],
        ];

        foreach ($sampleAdmins as $adminData) {
            $existingUser = User::where('email', $adminData['email'])->first();

            if (! $existingUser) {
                User::create(array_merge($adminData, [
                    'email_verified_at' => now(),
                ]));

                $this->command->info("Created admin user: {$adminData['email']}");
            }
        }
    }
}
