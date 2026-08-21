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
        Schema::create('mp_change_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 20)->unique();
            $table->unsignedBigInteger('request_id')->index('mp_change_orders_request_id_foreign');
            $table->string('context', 120);
            $table->string('reason', 40);
            $table->unsignedBigInteger('raised_by_user_id')->nullable()->index('mp_change_orders_raised_by_user_id_foreign');
            $table->date('raised_on');
            $table->string('state', 20)->default('draft');
            $table->string('stage', 40)->default('Not submitted');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mp_change_orders');
    }
};
