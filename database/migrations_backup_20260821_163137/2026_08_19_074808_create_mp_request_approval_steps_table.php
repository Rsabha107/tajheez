<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_request_approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('mp_requests')->cascadeOnDelete();
            $table->unsignedTinyInteger('step_no');
            $table->string('role_label', 80);
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('state', 20)->default('next'); // done|now|next|change
            $table->timestamp('acted_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['request_id', 'step_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_request_approval_steps');
    }
};
