<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classifications', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->string('color', 45)->nullable();
            $table->timestamps();
        });

        DB::table('classifications')->insert([
            ['id' => 1, 'name' => 'Preferred', 'is_active' => true, 'color' => 'success', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Normal', 'is_active' => true, 'color' => 'secondary', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Suspended', 'is_active' => true, 'color' => 'danger', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('classifications');
    }
};
