<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * global_statuses already exists in this app's live databases (used by
     * users/events/venues/functional-areas) but was never captured in a
     * migration — this backfills that history so fresh installs have it too,
     * ahead of the mp_* tables that are about to FK onto it.
     */
    public function up(): void
    {
        if (Schema::hasTable('global_statuses')) {
            return;
        }

        Schema::create('global_statuses', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->string('color', 45)->nullable();
            $table->timestamps();
        });

        DB::table('global_statuses')->insert([
            ['id' => 1, 'name' => 'active', 'is_active' => true, 'color' => 'success', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'inactive', 'is_active' => true, 'color' => 'secondary', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * No-op: this table may already have existed before this migration ran
     * (the common case, since it's shared by other unrelated features), so
     * rolling back must never drop it out from under them.
     */
    public function down(): void
    {
    }
};
