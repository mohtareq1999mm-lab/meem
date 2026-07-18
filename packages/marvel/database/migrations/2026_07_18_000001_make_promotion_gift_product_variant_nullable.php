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
            $this->rollbackSqlite();
        } else {
            $this->rollbackMysql();
        }
    }

    private function migrateSqlite(): void
    {
        $from = 'promotion_gift_products';
        $to = $from . '_temp';

        Schema::disableForeignKeyConstraints();

        $rows = DB::table($from)->get();

        Schema::dropIfExists($to);

        DB::statement("
            CREATE TABLE {$to} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                promotion_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                product_variant_id INTEGER,
                quantity INTEGER NOT NULL DEFAULT 1,
                created_at TIMESTAMP,
                updated_at TIMESTAMP,
                FOREIGN KEY (promotion_id) REFERENCES promotions(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
            )
        ");

        foreach ($rows as $row) {
            DB::table($to)->insert((array) $row);
        }

        Schema::drop($from);
        DB::statement("ALTER TABLE {$to} RENAME TO {$from}");
        DB::statement("CREATE UNIQUE INDEX {$from}_promotion_product_unique ON {$from}(promotion_id, product_id)");
        DB::statement("CREATE INDEX {$from}_product_id_index ON {$from}(product_id)");
        DB::statement("CREATE INDEX {$from}_product_variant_id_index ON {$from}(product_variant_id)");

        Schema::enableForeignKeyConstraints();
    }

    private function rollbackSqlite(): void
    {
        $from = 'promotion_gift_products';
        $to = $from . '_temp';

        Schema::disableForeignKeyConstraints();

        $rows = DB::table($from)->get();

        Schema::dropIfExists($to);

        DB::statement("
            CREATE TABLE {$to} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                promotion_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                product_variant_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL DEFAULT 1,
                created_at TIMESTAMP,
                updated_at TIMESTAMP,
                FOREIGN KEY (promotion_id) REFERENCES promotions(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
            )
        ");

        DB::statement("
            INSERT INTO {$to} (id, promotion_id, product_id, product_variant_id, quantity, created_at, updated_at)
            SELECT id, promotion_id, product_id, COALESCE(product_variant_id, 1), quantity, created_at, updated_at
            FROM {$from}
        ");

        Schema::drop($from);
        DB::statement("ALTER TABLE {$to} RENAME TO {$from}");
        DB::statement("CREATE UNIQUE INDEX {$from}_promotion_product_unique ON {$from}(promotion_id, product_id)");
        DB::statement("CREATE INDEX {$from}_product_id_index ON {$from}(product_id)");
        DB::statement("CREATE INDEX {$from}_product_variant_id_index ON {$from}(product_variant_id)");

        Schema::enableForeignKeyConstraints();
    }

    private function migrateMysql(): void
    {
        Schema::table('promotion_gift_products', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropIndex(['product_variant_id']);
            $table->unsignedBigInteger('product_variant_id')->nullable()->change();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->index('product_variant_id');
        });
    }

    private function rollbackMysql(): void
    {
        Schema::table('promotion_gift_products', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropIndex(['product_variant_id']);
            $table->unsignedBigInteger('product_variant_id')->nullable(false)->change();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->index('product_variant_id');
        });
    }
};
