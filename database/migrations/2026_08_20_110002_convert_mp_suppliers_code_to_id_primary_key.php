<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * mp_service_options.supplier_code still FKs to mp_suppliers.code — drop
     * it first (MySQL refuses to drop a PRIMARY KEY a foreign key still
     * depends on), it's re-established against supplier_id in the
     * mp_service_options conversion migration.
     */
    public function up(): void
    {
        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->dropForeign(['supplier_code']);
        });

        Schema::table('mp_suppliers', function (Blueprint $table) {
            $table->dropPrimary(['code']);
            $table->id()->first();
            $table->unique('code');
        });
    }

    /**
     * Runs after the mp_service_options conversion migration has already
     * been rolled back, so mp_service_options.supplier_code (FK ->
     * mp_suppliers.code) already exists again at this point.
     */
    public function down(): void
    {
        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->dropForeign(['supplier_code']);
        });

        Schema::table('mp_suppliers', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('id');
            $table->primary('code');
        });

        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->foreign('supplier_code')->references('code')->on('mp_suppliers')->restrictOnDelete();
        });
    }
};
