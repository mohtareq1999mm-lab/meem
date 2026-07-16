<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Adds 'path' and 'data' columns to support Puck page builder format.
     * - path: URL path for the page (e.g., "/", "/about")
     * - data: Complete Puck JSON structure (root, content, zones)
     */
    public function up(): void
    {
        Schema::table('cms_pages', function (Blueprint $table): void {
            $table->string('path')->nullable()->unique()->after('slug');
            $table->json('data')->nullable()->after('content');
        });

        // Copy existing slugs to paths with "/" prefix if not already present
        DB::table('cms_pages')->whereNull('path')->update([
            'path' => DB::raw("CASE WHEN slug LIKE '/%' THEN slug ELSE CONCAT('/', slug) END")
        ]);

        // Make path required after migration
        Schema::table('cms_pages', function (Blueprint $table): void {
            $table->string('path')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cms_pages', function (Blueprint $table): void {
            $table->dropColumn(['path', 'data']);
        });
    }
};
