<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Add payment_response if it doesn't exist
            if (!Schema::hasColumn('orders', 'payment_response')) {
                $table->json('payment_response')->nullable()->after('shipping_address');
            }
            
            // Add courier_service if it doesn't exist
            if (!Schema::hasColumn('orders', 'courier_service')) {
                $table->string('courier_service')->default('regular')->after('shipping_cost');
            }
            
            // Add tracking_number if it doesn't exist
            if (!Schema::hasColumn('orders', 'tracking_number')) {
                $table->string('tracking_number')->nullable()->after('courier_service');
            }
            
            // Add tracking_history if it doesn't exist
            if (!Schema::hasColumn('orders', 'tracking_history')) {
                $table->json('tracking_history')->nullable()->after('tracking_number');
            }
            
            // Add shipped_at if it doesn't exist
            if (!Schema::hasColumn('orders', 'shipped_at')) {
                $table->timestamp('shipped_at')->nullable()->after('tracking_history');
            }
            
            // Add delivered_at if it doesn't exist
            if (!Schema::hasColumn('orders', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $columns = ['payment_response', 'courier_service', 'tracking_number', 'tracking_history', 'shipped_at', 'delivered_at'];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
