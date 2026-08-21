<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->foreign(['catalog_item_id'])->references(['id'])->on('mp_catalog_items')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['classification_id'])->references(['id'])->on('classifications')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['status_id'])->references(['id'])->on('global_statuses')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['supplier_id'])->references(['id'])->on('mp_suppliers')->onUpdate('no action')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->dropForeign('mp_service_options_catalog_item_id_foreign');
            $table->dropForeign('mp_service_options_classification_id_foreign');
            $table->dropForeign('mp_service_options_status_id_foreign');
            $table->dropForeign('mp_service_options_supplier_id_foreign');
        });
    }
};
