<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // e.g. MR-26-04188
            $table->string('title', 200);
            $table->foreignId('event_id')->constrained('events')->restrictOnDelete();
            $table->foreignId('venue_id')->constrained('venues')->restrictOnDelete();
            $table->string('site_type', 80)->nullable();
            $table->string('site_code', 40)->nullable();
            $table->string('site_name', 120);
            $table->string('ls_category', 40)->nullable();
            $table->string('ls_sub', 40)->nullable();
            $table->string('ls_name', 80)->nullable();
            $table->string('ls_code', 20)->nullable();
            $table->string('base_room', 20)->default('No'); // No|Yes — shared|Yes — dedicated
            $table->date('move_in')->nullable();
            $table->date('move_out')->nullable();
            $table->string('priority', 20)->default('Medium'); // Low|Medium|High|Critical
            $table->string('status', 20)->default('draft');
            $table->string('approver_routing', 60)->default('Auto-route (multi-step)');
            $table->text('notes')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_requests');
    }
};
