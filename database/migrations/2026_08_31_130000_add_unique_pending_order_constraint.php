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
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            // MySQL does not support partial indexes (WHERE ...). Emulate with a virtual
            // generated column that is user_id only when status='pending', otherwise NULL.
            // UNIQUE allows multiple NULLs, so only pending rows are constrained.
            if (! Schema::hasColumn('orders', 'pending_user_id')) {
                DB::statement("
                    ALTER TABLE `orders`
                    ADD COLUMN `pending_user_id` BIGINT UNSIGNED
                    AS (CASE WHEN `status` = 'pending' THEN `user_id` ELSE NULL END) VIRTUAL
                ");
            }

            // Create unique index if not already exists
            $indexExists = collect(DB::select("SHOW INDEX FROM `orders` WHERE Key_name = 'idx_orders_user_pending_unique'"))->isNotEmpty();
            if (! $indexExists) {
                DB::statement("CREATE UNIQUE INDEX `idx_orders_user_pending_unique` ON `orders` (`pending_user_id`)");
            }

            return;
        }

        // SQLite and PostgreSQL support partial indexes natively
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS idx_orders_user_pending_unique
            ON orders(user_id)
            WHERE status = 'pending'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            // Drop unique index if exists
            $indexExists = collect(DB::select("SHOW INDEX FROM `orders` WHERE Key_name = 'idx_orders_user_pending_unique'"))->isNotEmpty();
            if ($indexExists) {
                try {
                    DB::statement("DROP INDEX `idx_orders_user_pending_unique` ON `orders`");
                } catch (\Throwable $e) {
                    // ignore if index already dropped
                }
            }

            if (Schema::hasColumn('orders', 'pending_user_id')) {
                try {
                    DB::statement("ALTER TABLE `orders` DROP COLUMN `pending_user_id`");
                } catch (\Throwable $e) {
                    Schema::table('orders', function (Blueprint $table) {
                        if (Schema::hasColumn('orders', 'pending_user_id')) {
                            $table->dropColumn('pending_user_id');
                        }
                    });
                }
            }

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("DROP INDEX IF EXISTS idx_orders_user_pending_unique");
            return;
        }

        // SQLite
        try {
            DB::statement("DROP INDEX IF EXISTS idx_orders_user_pending_unique");
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
