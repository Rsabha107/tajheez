<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * sku's FK to mp_catalog_items and supplier_code's FK to mp_suppliers
     * were already dropped by those tables' own conversion migrations —
     * only the composite index remains to clean up here.
     */
    public function up(): void
    {
        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->unsignedBigInteger('catalog_item_id')->nullable()->after('sku');
            $table->unsignedBigInteger('supplier_id')->nullable()->after('supplier_code');
        });

        DB::statement('UPDATE mp_service_options o JOIN mp_catalog_items c ON o.sku = c.sku SET o.catalog_item_id = c.id');
        DB::statement('UPDATE mp_service_options o JOIN mp_suppliers s ON o.supplier_code = s.code SET o.supplier_id = s.id');

        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->dropIndex(['sku', 'is_default']);
            $table->dropColumn(['sku', 'supplier_code']);
        });

        DB::statement('ALTER TABLE mp_service_options MODIFY catalog_item_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE mp_service_options MODIFY supplier_id BIGINT UNSIGNED NOT NULL');

        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->foreign('catalog_item_id')->references('id')->on('mp_catalog_items')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('mp_suppliers')->restrictOnDelete();
            $table->index(['catalog_item_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->dropForeign(['catalog_item_id']);
            $table->dropForeign(['supplier_id']);
            $table->dropIndex(['catalog_item_id', 'is_default']);
            $table->string('sku', 20)->nullable()->after('code');
            $table->string('supplier_code', 20)->nullable()->after('sku');
        });

        DB::statement('UPDATE mp_service_options o JOIN mp_catalog_items c ON o.catalog_item_id = c.id SET o.sku = c.sku');
        DB::statement('UPDATE mp_service_options o JOIN mp_suppliers s ON o.supplier_id = s.id SET o.supplier_code = s.code');

        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->dropColumn(['catalog_item_id', 'supplier_id']);
        });

        DB::statement('ALTER TABLE mp_service_options MODIFY sku VARCHAR(20) NOT NULL');
        DB::statement('ALTER TABLE mp_service_options MODIFY supplier_code VARCHAR(20) NOT NULL');

        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->foreign('sku')->references('sku')->on('mp_catalog_items')->cascadeOnDelete();
            $table->foreign('supplier_code')->references('code')->on('mp_suppliers')->restrictOnDelete();
            $table->index(['sku', 'is_default']);
        });
    }
};
