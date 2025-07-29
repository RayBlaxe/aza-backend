<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class FixSuperadminRole extends Command
{
    protected $signature = 'fix:superadmin-role';
    protected $description = 'Fix the superadmin user role';

    public function handle()
    {
        $user = User::where('email', 'superadmin@26store.com')->first();
        
        if ($user) {
            $this->info('Found user: ' . $user->email);
            $this->info('Current role: ' . $user->role);
            
            $user->role = 'superadmin';
            $user->save();
            
            $this->info('Updated role to: ' . $user->role);
            $this->info('Superadmin role fixed successfully!');
        } else {
            $this->error('Superadmin user not found!');
        }
    }
}
