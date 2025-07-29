<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For MySQL, we can modify the enum directly
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('customer', 'admin', 'superadmin') DEFAULT 'customer'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert superadmin users to admin first
        DB::statement("UPDATE users SET role = 'admin' WHERE role = 'superadmin'");
        
        // Then modify the enum back to original values
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('customer', 'admin') DEFAULT 'customer'");
    }
};
