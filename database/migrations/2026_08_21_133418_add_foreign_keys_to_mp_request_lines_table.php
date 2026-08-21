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
        Schema::table('mp_request_lines', function (Blueprint $table) {
            $table->foreign(['catalog_item_id'])->references(['id'])->on('mp_catalog_items')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['request_id'])->references(['id'])->on('mp_requests')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['service_option_id'])->references(['id'])->on('mp_service_options')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mp_request_lines', function (Blueprint $table) {
            $table->dropForeign('mp_request_lines_catalog_item_id_foreign');
            $table->dropForeign('mp_request_lines_request_id_foreign');
            $table->dropForeign('mp_request_lines_service_option_id_foreign');
        });
    }
};
