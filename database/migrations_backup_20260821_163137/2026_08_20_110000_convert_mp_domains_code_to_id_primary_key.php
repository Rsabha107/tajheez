<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * mp_catalog_items.domain_code and mp_item_groups.domain_code still FK
     * to mp_domains.code at this point — MySQL refuses to drop a PRIMARY
     * KEY that a foreign key still depends on, even within the same ALTER
     * that replaces it, so those FKs must be dropped first. They're
     * re-established against the new domain_id columns in the later
     * catalog-items/item-groups conversion migrations.
     */
    public function up(): void
    {
        Schema::table('mp_catalog_items', function (Blueprint $table) {
            $table->dropForeign(['domain_code']);
        });
        Schema::table('mp_item_groups', function (Blueprint $table) {
            $table->dropForeign(['domain_code']);
        });

        Schema::table('mp_domains', function (Blueprint $table) {
            $table->dropPrimary(['code']);
            $table->id()->first();
            $table->unique('code');
        });
    }

    /**
     * Runs after the catalog-items/item-groups conversion migrations have
     * already been rolled back, so mp_catalog_items.domain_code and
     * mp_item_groups.domain_code (FK -> mp_domains.code) already exist
     * again at this point — same drop-before-swap dance as up().
     */
    public function down(): void
    {
        Schema::table('mp_catalog_items', function (Blueprint $table) {
            $table->dropForeign(['domain_code']);
        });
        Schema::table('mp_item_groups', function (Blueprint $table) {
            $table->dropForeign(['domain_code']);
        });

        Schema::table('mp_domains', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('id');
            $table->primary('code');
        });

        Schema::table('mp_catalog_items', function (Blueprint $table) {
            $table->foreign('domain_code')->references('code')->on('mp_domains')->restrictOnDelete();
        });
        Schema::table('mp_item_groups', function (Blueprint $table) {
            $table->foreign('domain_code')->references('code')->on('mp_domains')->restrictOnDelete();
        });
    }
};
