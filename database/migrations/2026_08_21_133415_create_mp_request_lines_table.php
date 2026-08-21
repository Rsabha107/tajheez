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
        Schema::create('mp_request_lines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('request_id')->index('mp_request_lines_request_id_foreign');
            $table->unsignedBigInteger('catalog_item_id')->index('mp_request_lines_catalog_item_id_foreign');
            $table->unsignedInteger('qty');
            $table->decimal('rate_snapshot', 12);
            $table->string('comment')->nullable();
            $table->unsignedBigInteger('service_option_id')->nullable()->index('mp_request_lines_service_option_id_foreign');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mp_request_lines');
    }
};
