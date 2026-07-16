<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'type')) {
                $table->string('type')->default('user')->after('remember_token');
            }
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('type');
            }
            if (!Schema::hasColumn('users', 'phone_number')) {
                $table->string('phone_number')->nullable()->unique()->after('is_active');
            }
            if (!Schema::hasColumn('users', 'shop_id')) {
                $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete()->after('phone_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['type', 'is_active', 'phone_number', 'shop_id']);
        });
    }
};
