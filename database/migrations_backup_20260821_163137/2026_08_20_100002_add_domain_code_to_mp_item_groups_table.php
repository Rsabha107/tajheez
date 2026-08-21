<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Item groups belong to a material Domain (Sport, Overlay, IT, ...),
     * same relationship shape as Space -> Area.
     */
    public function up(): void
    {
        Schema::table('mp_item_groups', function (Blueprint $table) {
            $table->string('domain_code', 8)->after('code');
            $table->foreign('domain_code')->references('code')->on('mp_domains')->restrictOnDelete();
            $table->index('domain_code');
        });
    }

    public function down(): void
    {
        Schema::table('mp_item_groups', function (Blueprint $table) {
            $table->dropForeign(['domain_code']);
            $table->dropIndex(['domain_code']);
            $table->dropColumn('domain_code');
        });
    }
};
