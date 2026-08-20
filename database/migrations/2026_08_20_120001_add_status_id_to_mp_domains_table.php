<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mp_domains', function (Blueprint $table) {
            $table->integer('status_id')->default(1)->after('code');
            $table->foreign('status_id')->references('id')->on('global_statuses')->restrictOnDelete();
            $table->index('status_id');
        });
    }

    public function down(): void
    {
        Schema::table('mp_domains', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->dropIndex(['status_id']);
            $table->dropColumn('status_id');
        });
    }
};
