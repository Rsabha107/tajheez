<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fallback event for any area with no request history to infer an event
     * from (matches MaterialPlanningSeeder::EVENT_ID).
     */
    private const DEFAULT_EVENT_ID = 10;

    /**
     * Run the migrations.
     *
     * Areas were global until now. To make them strictly one-event-each,
     * each area is backfilled from the events of the requests that already
     * reference it via mp_requests.area_id:
     *   - referenced by exactly one event -> stamped with that event.
     *   - referenced by 2+ events -> the original row keeps the lowest-id
     *     event; a duplicate row (new code) is created per additional event,
     *     and that event's requests are repointed to the duplicate.
     *   - referenced by no events -> falls back to DEFAULT_EVENT_ID.
     *
     * mp_spaces.area_id is NOT repointed here — spaces aren't event-scoped
     * yet at this point in the migration sequence; that FK is repaired by
     * the mp_spaces event_id migration instead.
     */
    public function up(): void
    {
        Schema::table('mp_areas', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable()->after('code');
        });

        $eventsByArea = DB::table('mp_requests')
            ->whereNotNull('area_id')
            ->select('area_id', 'event_id')
            ->distinct()
            ->get()
            ->groupBy('area_id');

        $areas = DB::table('mp_areas')->orderBy('id')->get();

        foreach ($areas as $area) {
            $eventIds = ($eventsByArea[$area->id] ?? collect())
                ->pluck('event_id')
                ->unique()
                ->sort()
                ->values();

            if ($eventIds->isEmpty()) {
                DB::table('mp_areas')->where('id', $area->id)->update([
                    'event_id' => self::DEFAULT_EVENT_ID,
                ]);
                continue;
            }

            $primaryEventId = $eventIds->first();
            DB::table('mp_areas')->where('id', $area->id)->update([
                'event_id' => $primaryEventId,
            ]);

            foreach ($eventIds->slice(1) as $extraEventId) {
                $suffix = '-E' . $extraEventId;
                $newId = DB::table('mp_areas')->insertGetId([
                    'code' => substr($area->code, 0, 20 - strlen($suffix)) . $suffix,
                    'event_id' => $extraEventId,
                    'status_id' => $area->status_id,
                    'label' => $area->label,
                    'description' => $area->description,
                    'sort_order' => $area->sort_order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('mp_requests')
                    ->where('area_id', $area->id)
                    ->where('event_id', $extraEventId)
                    ->update(['area_id' => $newId]);
            }
        }

        Schema::table('mp_areas', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable(false)->change();
            $table->index('event_id', 'mp_areas_event_id_index');
            $table->foreign('event_id')->references('id')->on('events')->onUpdate('no action')->onDelete('restrict');
        });
    }

    /**
     * Best-effort only: duplicate rows created for shared areas are left in
     * place (dropping them could orphan the requests that were repointed to
     * them during up()).
     */
    public function down(): void
    {
        Schema::table('mp_areas', function (Blueprint $table) {
            $table->dropForeign('mp_areas_event_id_foreign');
            $table->dropIndex('mp_areas_event_id_index');
            $table->dropColumn('event_id');
        });
    }
};
