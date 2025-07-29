<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CreateNewSuperadminSeeder extends Seeder
{
    public function run(): void
    {
        // Create a new superadmin user with a different email
        $superadminUser = User::where('email', 'superadmin123@26store.com')->first();

        if (! $superadminUser) {
            User::create([
                'name' => 'Super Admin 26Store',
                'email' => 'superadmin123@26store.com',
                'password' => Hash::make('superadmin123'),
                'role' => 'superadmin',
                'email_verified_at' => now(),
            ]);

            $this->command->info('New superadmin user created:');
            $this->command->info('Email: superadmin123@26store.com');
            $this->command->info('Password: superadmin123');
        } else {
            $this->command->info('New superadmin user already exists.');
        }
    }
}
