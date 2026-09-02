<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fallback event for any space with no request history to infer an
     * event from (matches MaterialPlanningSeeder::EVENT_ID).
     */
    private const DEFAULT_EVENT_ID = 10;

    /**
     * Run the migrations.
     *
     * Spaces were global until now. To make them strictly one-event-each,
     * each space is backfilled from the events of the requests that already
     * reference it via mp_requests.space_id:
     *   - referenced by exactly one event -> stamped with that event.
     *   - referenced by 2+ events -> the original row keeps the lowest-id
     *     event; a duplicate row (new code) is created per additional event,
     *     and that event's requests are repointed to the duplicate.
     *   - referenced by no events -> falls back to DEFAULT_EVENT_ID.
     *
     * mp_areas already has event_id (see the earlier migration), so every
     * resulting space row also gets its area_id repointed to an area in its
     * OWN resolved event, creating an on-demand area duplicate via
     * resolveParentForEvent() if the areas migration didn't already make
     * one for that event.
     */
    public function up(): void
    {
        Schema::table('mp_spaces', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable()->after('code');
        });

        $eventsBySpace = DB::table('mp_requests')
            ->whereNotNull('space_id')
            ->select('space_id', 'event_id')
            ->distinct()
            ->get()
            ->groupBy('space_id');

        $spaces = DB::table('mp_spaces')->orderBy('id')->get();

        $areaCache = [];

        foreach ($spaces as $space) {
            $eventIds = ($eventsBySpace[$space->id] ?? collect())
                ->pluck('event_id')
                ->unique()
                ->sort()
                ->values();

            if ($eventIds->isEmpty()) {
                $eventIds = collect([self::DEFAULT_EVENT_ID]);
            }

            $primaryEventId = $eventIds->first();
            DB::table('mp_spaces')->where('id', $space->id)->update([
                'event_id' => $primaryEventId,
                'area_id' => $this->resolveParentForEvent(
                    'mp_areas', 'code', 20, $space->area_id, $primaryEventId, $areaCache
                ),
            ]);

            foreach ($eventIds->slice(1) as $extraEventId) {
                $suffix = '-E' . $extraEventId;
                $newId = DB::table('mp_spaces')->insertGetId([
                    'code' => substr($space->code, 0, 20 - strlen($suffix)) . $suffix,
                    'event_id' => $extraEventId,
                    'status_id' => $space->status_id,
                    'area_id' => $this->resolveParentForEvent(
                        'mp_areas', 'code', 20, $space->area_id, $extraEventId, $areaCache
                    ),
                    'name' => $space->name,
                    'description' => $space->description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('mp_requests')
                    ->where('space_id', $space->id)
                    ->where('event_id', $extraEventId)
                    ->update(['space_id' => $newId]);
            }
        }

        Schema::table('mp_spaces', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable(false)->change();
            $table->index('event_id', 'mp_spaces_event_id_index');
            $table->foreign('event_id')->references('id')->on('events')->onUpdate('no action')->onDelete('restrict');
        });
    }

    /**
     * Returns the id of the row in $table whose "family" (same $codeCol
     * root as $originalParentId's row) belongs to $eventId — reusing an
     * existing duplicate if one was already created (by this method or an
     * earlier migration's own duplication pass), otherwise cloning the
     * original row with a suffixed code for that event.
     */
    private function resolveParentForEvent(
        string $table,
        string $codeCol,
        int $codeMaxLen,
        int $originalParentId,
        int $eventId,
        array &$cache
    ): int {
        if (isset($cache[$originalParentId][$eventId])) {
            return $cache[$originalParentId][$eventId];
        }

        $original = DB::table($table)->where('id', $originalParentId)->first();

        if ((int) $original->event_id === $eventId) {
            return $cache[$originalParentId][$eventId] = $original->id;
        }

        $suffix = '-E' . $eventId;
        $newCode = substr($original->{$codeCol}, 0, $codeMaxLen - strlen($suffix)) . $suffix;

        $existing = DB::table($table)->where($codeCol, $newCode)->first();
        if ($existing) {
            return $cache[$originalParentId][$eventId] = $existing->id;
        }

        $row = (array) $original;
        unset($row['id']);
        $row[$codeCol] = $newCode;
        $row['event_id'] = $eventId;
        $row['created_at'] = $row['updated_at'] = now();
        $newId = DB::table($table)->insertGetId($row);

        return $cache[$originalParentId][$eventId] = $newId;
    }

    /**
     * Best-effort only: duplicate rows created for shared spaces (and any
     * on-demand area duplicates they triggered) are left in place —
     * dropping them could orphan rows repointed to them during up().
     */
    public function down(): void
    {
        Schema::table('mp_spaces', function (Blueprint $table) {
            $table->dropForeign('mp_spaces_event_id_foreign');
            $table->dropIndex('mp_spaces_event_id_index');
            $table->dropColumn('event_id');
        });
    }
};
