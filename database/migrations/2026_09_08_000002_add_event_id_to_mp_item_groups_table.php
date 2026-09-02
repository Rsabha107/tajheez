<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fallback event for any item group with no bundle/library-item history
     * to infer an event from (matches MaterialPlanningSeeder::EVENT_ID).
     */
    private const DEFAULT_EVENT_ID = 10;

    /**
     * Run the migrations.
     *
     * Item groups were global until now. To make them strictly one-event-each,
     * each group is backfilled from the events of the service options/service
     * option items that already reference it:
     *   - referenced by exactly one event -> stamped with that event.
     *   - referenced by 2+ events -> the original row keeps the lowest-id
     *     event; a duplicate row (new code) is created per additional event,
     *     and that event's service options/items are repointed to the
     *     duplicate.
     *   - referenced by no events -> falls back to DEFAULT_EVENT_ID.
     *
     * mp_domains now has event_id (see the previous migration), so every
     * resulting item-group row (assigned directly, duplicated, or fallen
     * back) also gets its domain_id repointed to a domain in its OWN
     * resolved event, creating an on-demand domain duplicate via
     * resolveParentForEvent() if the domain migration didn't already make
     * one for that event (e.g. a group whose domain has no catalog-item
     * usage of its own, so the domain migration never split it).
     */
    public function up(): void
    {
        Schema::table('mp_item_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable()->after('code');
        });

        $eventsByGroup = DB::table('mp_service_options')
            ->select('item_group_id', 'event_id')
            ->distinct()
            ->get()
            ->merge(
                DB::table('mp_service_option_items')
                    ->select('item_group_id', 'event_id')
                    ->distinct()
                    ->get()
            )
            ->filter(fn ($r) => $r->item_group_id !== null)
            ->groupBy('item_group_id');

        $groups = DB::table('mp_item_groups')->orderBy('id')->get();

        $domainCache = [];

        foreach ($groups as $group) {
            $eventIds = ($eventsByGroup[$group->id] ?? collect())
                ->pluck('event_id')
                ->unique()
                ->sort()
                ->values();

            if ($eventIds->isEmpty()) {
                $eventIds = collect([self::DEFAULT_EVENT_ID]);
            }

            $primaryEventId = $eventIds->first();
            DB::table('mp_item_groups')->where('id', $group->id)->update([
                'event_id' => $primaryEventId,
                'domain_id' => $this->resolveParentForEvent(
                    'mp_domains', 'code', 8, $group->domain_id, $primaryEventId, $domainCache
                ),
            ]);

            foreach ($eventIds->slice(1) as $extraEventId) {
                $suffix = '-E' . $extraEventId;
                $newId = DB::table('mp_item_groups')->insertGetId([
                    'code' => substr($group->code, 0, 20 - strlen($suffix)) . $suffix,
                    'event_id' => $extraEventId,
                    'status_id' => $group->status_id,
                    'domain_id' => $this->resolveParentForEvent(
                        'mp_domains', 'code', 8, $group->domain_id, $extraEventId, $domainCache
                    ),
                    'label' => $group->label,
                    'description' => $group->description,
                    'sort_order' => $group->sort_order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('mp_service_options')
                    ->where('item_group_id', $group->id)
                    ->where('event_id', $extraEventId)
                    ->update(['item_group_id' => $newId]);

                DB::table('mp_service_option_items')
                    ->where('item_group_id', $group->id)
                    ->where('event_id', $extraEventId)
                    ->update(['item_group_id' => $newId]);
            }
        }

        Schema::table('mp_item_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable(false)->change();
            $table->index('event_id', 'mp_item_groups_event_id_index');
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
     * Best-effort only: duplicate rows created for shared groups (and any
     * on-demand domain duplicates they triggered) are left in place —
     * dropping them could orphan rows repointed to them during up().
     */
    public function down(): void
    {
        Schema::table('mp_item_groups', function (Blueprint $table) {
            $table->dropForeign('mp_item_groups_event_id_foreign');
            $table->dropIndex('mp_item_groups_event_id_index');
            $table->dropColumn('event_id');
        });
    }
};
