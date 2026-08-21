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
        Schema::create('mp_change_order_lines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('change_order_id')->index('mp_change_order_lines_change_order_id_foreign');
            $table->unsignedBigInteger('request_line_id')->nullable()->index('mp_change_order_lines_request_line_id_foreign');
            $table->unsignedBigInteger('catalog_item_id')->index('mp_change_order_lines_catalog_item_id_foreign');
            $table->unsignedInteger('qty_before')->nullable();
            $table->unsignedInteger('qty_after')->nullable();
            $table->decimal('rate_before', 12)->nullable();
            $table->decimal('rate_after', 12)->nullable();
            $table->unsignedBigInteger('service_option_before_id')->nullable()->index('mp_change_order_lines_service_option_before_id_foreign');
            $table->unsignedBigInteger('service_option_after_id')->nullable()->index('mp_change_order_lines_service_option_after_id_foreign');
            $table->string('why')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mp_change_order_lines');
    }
};
