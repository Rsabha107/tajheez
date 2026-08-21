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
        Schema::create('mp_request_activity', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('request_id')->index('mp_request_activity_request_id_foreign');
            $table->unsignedBigInteger('actor_user_id')->nullable()->index('mp_request_activity_actor_user_id_foreign');
            $table->string('verb', 40);
            $table->string('subject', 60)->nullable();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mp_request_activity');
    }
};
