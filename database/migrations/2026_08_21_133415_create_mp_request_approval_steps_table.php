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
        Schema::create('mp_request_approval_steps', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('request_id');
            $table->unsignedTinyInteger('step_no');
            $table->string('role_label', 80);
            $table->unsignedBigInteger('approver_user_id')->nullable()->index('mp_request_approval_steps_approver_user_id_foreign');
            $table->string('state', 20)->default('next');
            $table->timestamp('acted_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['request_id', 'step_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mp_request_approval_steps');
    }
};
