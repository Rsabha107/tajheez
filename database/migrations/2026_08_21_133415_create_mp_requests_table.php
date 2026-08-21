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
        Schema::create('mp_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 20)->unique();
            $table->string('title', 200)->nullable();
            $table->unsignedBigInteger('event_id')->index('mp_requests_event_id_foreign');
            $table->unsignedBigInteger('venue_id')->index('mp_requests_venue_id_foreign');
            $table->integer('functional_area_id')->nullable()->index('mp_requests_functional_area_id_foreign');
            $table->string('site_type', 80)->nullable();
            $table->string('site_code', 40)->nullable();
            $table->string('site_name', 120)->nullable();
            $table->string('ls_category', 40)->nullable();
            $table->string('ls_sub', 40)->nullable();
            $table->string('ls_name', 80)->nullable();
            $table->string('ls_code', 20)->nullable();
            $table->string('base_room', 20)->default('No');
            $table->date('move_in')->nullable();
            $table->date('move_out')->nullable();
            $table->string('priority', 20)->default('Medium');
            $table->string('status', 20)->default('draft')->index();
            $table->string('approver_routing', 60)->default('Auto-route (multi-step)');
            $table->text('notes')->nullable();
            $table->string('layout_file_path')->nullable();
            $table->string('layout_file_name')->nullable();
            $table->unsignedBigInteger('owner_user_id')->nullable()->index('mp_requests_owner_user_id_foreign');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mp_requests');
    }
};
