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
        Schema::table('functional_area_user', function (Blueprint $table) {
            $table->foreign(['functional_area_id'])->references(['id'])->on('functional_areas')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['user_id'])->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('functional_area_user', function (Blueprint $table) {
            $table->dropForeign('functional_area_user_functional_area_id_foreign');
            $table->dropForeign('functional_area_user_user_id_foreign');
        });
    }
};
