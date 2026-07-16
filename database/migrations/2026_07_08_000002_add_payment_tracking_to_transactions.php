<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->string('status', 30)->default('pending')->after('payment_method')
                ->comment('pending, paid, failed, expired');
            $table->decimal('amount', 10, 2)->nullable()->after('status');
            $table->string('currency', 3)->default('EGP')->after('amount');
            $table->string('gateway_transaction_id', 255)->nullable()->after('currency');
            $table->json('gateway_response')->nullable()->after('gateway_transaction_id');
            $table->text('error_message')->nullable()->after('gateway_response');
            $table->string('qr_code_url', 500)->nullable()->after('error_message');
            $table->timestamp('paid_at')->nullable()->after('qr_code_url');

            $table->index('status', 'txn_status_idx');
            $table->index('uuid', 'txn_uuid_idx');
        });

        DB::statement("UPDATE transactions SET status = 'paid' WHERE status IS NULL");
        DB::statement("UPDATE transactions SET amount = (SELECT total_price FROM orders WHERE orders.id = transactions.order_id) WHERE amount IS NULL");
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('txn_status_idx');
            $table->dropIndex('txn_uuid_idx');
            $table->dropColumn([
                'uuid', 'status', 'amount', 'currency',
                'gateway_transaction_id', 'gateway_response',
                'error_message', 'qr_code_url', 'paid_at',
            ]);
        });
    }
};
