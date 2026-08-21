<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * mp_service_options.sku, mp_request_lines.sku and
     * mp_change_order_lines.sku still FK to mp_catalog_items.sku — drop
     * them first (MySQL refuses to drop a PRIMARY KEY a foreign key still
     * depends on), they're re-established against catalog_item_id in their
     * own conversion migrations. mp_catalog_items.domain_code's own FK to
     * mp_domains was already dropped by the mp_domains conversion
     * migration, so it isn't dropped again here.
     */
    public function up(): void
    {
        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->dropForeign(['sku']);
        });
        Schema::table('mp_request_lines', function (Blueprint $table) {
            $table->dropForeign(['sku']);
        });
        Schema::table('mp_change_order_lines', function (Blueprint $table) {
            $table->dropForeign(['sku']);
        });

        Schema::table('mp_catalog_items', function (Blueprint $table) {
            $table->dropPrimary(['sku']);
            $table->id()->first();
            $table->unique('sku');
        });

        Schema::table('mp_catalog_items', function (Blueprint $table) {
            $table->unsignedBigInteger('domain_id')->nullable()->after('domain_code');
        });

        DB::statement('UPDATE mp_catalog_items c JOIN mp_domains d ON c.domain_code = d.code SET c.domain_id = d.id');

        Schema::table('mp_catalog_items', function (Blueprint $table) {
            $table->dropIndex('mp_catalog_items_domain_code_group_sub_index');
            $table->dropColumn('domain_code');
        });

        DB::statement('ALTER TABLE mp_catalog_items MODIFY domain_id BIGINT UNSIGNED NOT NULL');

        Schema::table('mp_catalog_items', function (Blueprint $table) {
            $table->foreign('domain_id')->references('id')->on('mp_domains')->restrictOnDelete();
            $table->index(['domain_id', 'group', 'sub']);
        });
    }

    /**
     * Runs after the mp_service_options/mp_request_lines/
     * mp_change_order_lines conversion migrations have already been rolled
     * back, so their `sku` columns (FK -> mp_catalog_items.sku) already
     * exist again at this point.
     */
    public function down(): void
    {
        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->dropForeign(['sku']);
        });
        Schema::table('mp_request_lines', function (Blueprint $table) {
            $table->dropForeign(['sku']);
        });
        Schema::table('mp_change_order_lines', function (Blueprint $table) {
            $table->dropForeign(['sku']);
        });

        Schema::table('mp_catalog_items', function (Blueprint $table) {
            $table->dropForeign(['domain_id']);
            $table->dropIndex(['domain_id', 'group', 'sub']);
            $table->string('domain_code', 8)->nullable()->after('sku');
        });

        DB::statement('UPDATE mp_catalog_items c JOIN mp_domains d ON c.domain_id = d.id SET c.domain_code = d.code');

        Schema::table('mp_catalog_items', function (Blueprint $table) {
            $table->dropColumn('domain_id');
        });

        DB::statement('ALTER TABLE mp_catalog_items MODIFY domain_code VARCHAR(8) NOT NULL');

        Schema::table('mp_catalog_items', function (Blueprint $table) {
            $table->index(['domain_code', 'group', 'sub']);
            $table->dropUnique(['sku']);
            $table->dropColumn('id');
            $table->primary('sku');
        });

        Schema::table('mp_catalog_items', function (Blueprint $table) {
            $table->foreign('domain_code')->references('code')->on('mp_domains')->restrictOnDelete();
        });
        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->foreign('sku')->references('sku')->on('mp_catalog_items')->cascadeOnDelete();
        });
        Schema::table('mp_request_lines', function (Blueprint $table) {
            $table->foreign('sku')->references('sku')->on('mp_catalog_items')->restrictOnDelete();
        });
        Schema::table('mp_change_order_lines', function (Blueprint $table) {
            $table->foreign('sku')->references('sku')->on('mp_catalog_items')->restrictOnDelete();
        });
    }
};
