<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fallback event for any item subgroup with no bundle/library-item
     * history to infer an event from (matches MaterialPlanningSeeder::EVENT_ID).
     */
    private const DEFAULT_EVENT_ID = 10;

    /**
     * Run the migrations.
     *
     * Item subgroups were global until now. To make them strictly
     * one-event-each, each subgroup is backfilled from the events of the
     * service options/service option items that already reference it:
     *   - referenced by exactly one event -> stamped with that event.
     *   - referenced by 2+ events -> the original row keeps the lowest-id
     *     event; a duplicate row (new code) is created per additional event,
     *     and that event's service options/items are repointed to the
     *     duplicate.
     *   - referenced by no events -> falls back to DEFAULT_EVENT_ID.
     *
     * mp_item_groups already has event_id (see the earlier migration), so
     * every resulting subgroup row also gets its group_id repointed to a
     * group in its OWN resolved event, creating an on-demand group
     * duplicate via resolveParentForEvent() if the item-groups migration
     * didn't already make one for that event.
     */
    public function up(): void
    {
        Schema::table('mp_item_subgroups', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable()->after('code');
        });

        $eventsBySubgroup = DB::table('mp_service_options')
            ->select('item_subgroup_id', 'event_id')
            ->distinct()
            ->get()
            ->merge(
                DB::table('mp_service_option_items')
                    ->select('item_subgroup_id', 'event_id')
                    ->distinct()
                    ->get()
            )
            ->filter(fn ($r) => $r->item_subgroup_id !== null)
            ->groupBy('item_subgroup_id');

        $subgroups = DB::table('mp_item_subgroups')->orderBy('id')->get();

        $groupCache = [];

        foreach ($subgroups as $subgroup) {
            $eventIds = ($eventsBySubgroup[$subgroup->id] ?? collect())
                ->pluck('event_id')
                ->unique()
                ->sort()
                ->values();

            if ($eventIds->isEmpty()) {
                $eventIds = collect([self::DEFAULT_EVENT_ID]);
            }

            $primaryEventId = $eventIds->first();
            DB::table('mp_item_subgroups')->where('id', $subgroup->id)->update([
                'event_id' => $primaryEventId,
                'group_id' => $this->resolveParentForEvent(
                    'mp_item_groups', 'code', 20, $subgroup->group_id, $primaryEventId, $groupCache
                ),
            ]);

            foreach ($eventIds->slice(1) as $extraEventId) {
                $suffix = '-E' . $extraEventId;
                $newId = DB::table('mp_item_subgroups')->insertGetId([
                    'code' => substr($subgroup->code, 0, 20 - strlen($suffix)) . $suffix,
                    'event_id' => $extraEventId,
                    'status_id' => $subgroup->status_id,
                    'group_id' => $this->resolveParentForEvent(
                        'mp_item_groups', 'code', 20, $subgroup->group_id, $extraEventId, $groupCache
                    ),
                    'name' => $subgroup->name,
                    'description' => $subgroup->description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('mp_service_options')
                    ->where('item_subgroup_id', $subgroup->id)
                    ->where('event_id', $extraEventId)
                    ->update(['item_subgroup_id' => $newId]);

                DB::table('mp_service_option_items')
                    ->where('item_subgroup_id', $subgroup->id)
                    ->where('event_id', $extraEventId)
                    ->update(['item_subgroup_id' => $newId]);
            }
        }

        Schema::table('mp_item_subgroups', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable(false)->change();
            $table->index('event_id', 'mp_item_subgroups_event_id_index');
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
     * Best-effort only: duplicate rows created for shared subgroups (and any
     * on-demand group duplicates they triggered) are left in place —
     * dropping them could orphan rows repointed to them during up().
     */
    public function down(): void
    {
        Schema::table('mp_item_subgroups', function (Blueprint $table) {
            $table->dropForeign('mp_item_subgroups_event_id_foreign');
            $table->dropIndex('mp_item_subgroups_event_id_index');
            $table->dropColumn('event_id');
        });
    }
};
