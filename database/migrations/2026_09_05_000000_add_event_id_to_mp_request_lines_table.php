<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `mp_request_lines.event_id` was previously only derivable by joining
     * up through `mp_requests.event_id` (see ItemsView.vue's `eventItems`) —
     * denormalized here for direct analysis/reporting queries. Kept in sync
     * going forward by RequestLineController::store() (new lines) and
     * MaterialRequestController::update() (cascades if a request's own
     * event_id is ever changed).
     */
    public function up(): void
    {
        Schema::table('mp_request_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable()->after('request_id');
        });

        DB::table('mp_request_lines')
            ->join('mp_requests', 'mp_requests.id', '=', 'mp_request_lines.request_id')
            ->update(['mp_request_lines.event_id' => DB::raw('mp_requests.event_id')]);

        Schema::table('mp_request_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable(false)->change();
            $table->index('event_id', 'mp_request_lines_event_id_index');
            $table->foreign('event_id')->references('id')->on('events')->onUpdate('no action')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mp_request_lines', function (Blueprint $table) {
            $table->dropForeign('mp_request_lines_event_id_foreign');
            $table->dropIndex('mp_request_lines_event_id_index');
            $table->dropColumn('event_id');
        });
    }
};
