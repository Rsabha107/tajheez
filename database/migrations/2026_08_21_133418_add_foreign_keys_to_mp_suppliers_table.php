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
        Schema::table('mp_suppliers', function (Blueprint $table) {
            $table->foreign(['classification_id'])->references(['id'])->on('classifications')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['status_id'])->references(['id'])->on('global_statuses')->onUpdate('no action')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mp_suppliers', function (Blueprint $table) {
            $table->dropForeign('mp_suppliers_classification_id_foreign');
            $table->dropForeign('mp_suppliers_status_id_foreign');
        });
    }
};
