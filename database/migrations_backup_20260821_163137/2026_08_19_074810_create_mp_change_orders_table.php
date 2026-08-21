<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_change_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // e.g. CO-014
            $table->foreignId('request_id')->constrained('mp_requests')->cascadeOnDelete();
            $table->string('context', 120);
            $table->string('reason', 40);
            $table->foreignId('raised_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('raised_on');
            $table->string('state', 20)->default('draft'); // draft|pending|approved|rejected
            $table->string('stage', 40)->default('Not submitted');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_change_orders');
    }
};
