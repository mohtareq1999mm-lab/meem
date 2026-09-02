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
        // Add unique partial index to prevent duplicate pending orders per user
        // This provides database-level race condition protection
        DB::statement("
            CREATE UNIQUE INDEX idx_orders_user_pending_unique
            ON orders(user_id)
            WHERE status = 'pending'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS idx_orders_user_pending_unique");
    }
};
