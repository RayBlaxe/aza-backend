<?php

require_once __DIR__ . '/vendor/autoload.php';

// Load Laravel app
$app = require_once __DIR__ . '/bootstrap/app.php';

// Bootstrap the application
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Hash;

// Find and update the superadmin user
$user = App\Models\User::where('email', 'superadmin@26store.com')->first();

if ($user) {
    echo "Found user: " . $user->email . "\n";
    echo "Current role: " . $user->role . "\n";
    
    $user->role = 'superadmin';
    $user->save();
    
    echo "Updated role to: " . $user->role . "\n";
    echo "Superadmin role fixed successfully!\n";
} else {
    echo "User not found. Creating new superadmin user...\n";
    
    $user = App\Models\User::create([
        'name' => 'Super Admin 26Store',
        'email' => 'superadmin@26store.com',
        'password' => Hash::make('superadmin123'),
        'role' => 'superadmin',
        'email_verified_at' => now(),
    ]);
    
    echo "Created new superadmin user: " . $user->email . "\n";
    echo "Role: " . $user->role . "\n";
}
