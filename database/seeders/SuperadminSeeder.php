<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperadminSeeder extends Seeder
{
    public function run(): void
    {
        // Create or update superadmin user
        $superadminUser = User::where('email', 'superadmin@26store.com')->first();

        if (! $superadminUser) {
            User::create([
                'name' => 'Super Admin 26Store',
                'email' => 'superadmin@26store.com',
                'password' => Hash::make('superadmin123'),
                'role' => 'superadmin',
                'email_verified_at' => now(),
            ]);

            $this->command->info('Default superadmin user created:');
            $this->command->info('Email: superadmin@26store.com');
            $this->command->info('Password: superadmin123');
        } else {
            // Update existing user to ensure correct role
            $superadminUser->update([
                'role' => 'superadmin',
            ]);
            $this->command->info('Superadmin user role updated to: superadmin');
        }
    }
}
