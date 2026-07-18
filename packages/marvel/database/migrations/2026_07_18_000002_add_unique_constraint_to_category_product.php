<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->migrateSqlite();
        } else {
            $this->migrateMysql();
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS cat_prod_unique');
        } else {
            Schema::table('category_product', function (Blueprint $table) {
                $table->dropUnique('cat_prod_unique');
            });
        }
    }

    private function migrateSqlite(): void
    {
        $from = 'category_product';
        $to = $from . '_temp';

        Schema::disableForeignKeyConstraints();

        $rows = DB::table($from)
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($row) => $row->category_id . '_' . $row->product_id)
            ->map(fn ($group) => $group->first())
            ->values();

        Schema::dropIfExists($to);

        DB::statement("
            CREATE TABLE {$to} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            )
        ");

        DB::statement("CREATE UNIQUE INDEX cat_prod_unique ON {$to}(category_id, product_id)");

        foreach ($rows as $row) {
            DB::table($to)->insert((array) $row);
        }

        Schema::drop($from);
        DB::statement("ALTER TABLE {$to} RENAME TO {$from}");

        Schema::enableForeignKeyConstraints();
    }

    private function migrateMysql(): void
    {
        DB::statement('
            DELETE cp1 FROM category_product cp1
            INNER JOIN category_product cp2
            WHERE cp1.id > cp2.id
            AND cp1.category_id = cp2.category_id
            AND cp1.product_id = cp2.product_id
        ');

        Schema::table('category_product', function (Blueprint $table) {
            $table->unique(['category_id', 'product_id'], 'cat_prod_unique');
        });
    }
};
