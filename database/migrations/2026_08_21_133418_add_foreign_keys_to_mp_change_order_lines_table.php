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
        Schema::table('mp_change_order_lines', function (Blueprint $table) {
            $table->foreign(['catalog_item_id'])->references(['id'])->on('mp_catalog_items')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['change_order_id'])->references(['id'])->on('mp_change_orders')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['request_line_id'])->references(['id'])->on('mp_request_lines')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['service_option_after_id'])->references(['id'])->on('mp_service_options')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['service_option_before_id'])->references(['id'])->on('mp_service_options')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mp_change_order_lines', function (Blueprint $table) {
            $table->dropForeign('mp_change_order_lines_catalog_item_id_foreign');
            $table->dropForeign('mp_change_order_lines_change_order_id_foreign');
            $table->dropForeign('mp_change_order_lines_request_line_id_foreign');
            $table->dropForeign('mp_change_order_lines_service_option_after_id_foreign');
            $table->dropForeign('mp_change_order_lines_service_option_before_id_foreign');
        });
    }
};
