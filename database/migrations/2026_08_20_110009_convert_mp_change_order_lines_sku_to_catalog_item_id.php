<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * sku's FK to mp_catalog_items was already dropped by the
     * mp_catalog_items conversion migration.
     */
    public function up(): void
    {
        Schema::table('mp_change_order_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('catalog_item_id')->nullable()->after('sku');
        });

        DB::statement('UPDATE mp_change_order_lines l JOIN mp_catalog_items c ON l.sku = c.sku SET l.catalog_item_id = c.id');

        Schema::table('mp_change_order_lines', function (Blueprint $table) {
            $table->dropColumn('sku');
        });

        DB::statement('ALTER TABLE mp_change_order_lines MODIFY catalog_item_id BIGINT UNSIGNED NOT NULL');

        Schema::table('mp_change_order_lines', function (Blueprint $table) {
            $table->foreign('catalog_item_id')->references('id')->on('mp_catalog_items')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mp_change_order_lines', function (Blueprint $table) {
            $table->dropForeign(['catalog_item_id']);
            $table->string('sku', 20)->nullable()->after('request_line_id');
        });

        DB::statement('UPDATE mp_change_order_lines l JOIN mp_catalog_items c ON l.catalog_item_id = c.id SET l.sku = c.sku');

        Schema::table('mp_change_order_lines', function (Blueprint $table) {
            $table->dropColumn('catalog_item_id');
        });

        DB::statement('ALTER TABLE mp_change_order_lines MODIFY sku VARCHAR(20) NOT NULL');

        Schema::table('mp_change_order_lines', function (Blueprint $table) {
            $table->foreign('sku')->references('sku')->on('mp_catalog_items')->restrictOnDelete();
        });
    }
};
