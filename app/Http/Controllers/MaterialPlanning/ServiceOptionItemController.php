<?php

namespace App\Http\Controllers\MaterialPlanning;

use App\Http\Controllers\Controller;
use App\Models\MaterialPlanning\ServiceOptionItem;
use App\Models\MaterialPlanning\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ServiceOptionItemController extends Controller
{
    private const RULES = [
        'name' => ['required', 'string', 'max:180'],
        'event_id' => ['required', 'exists:events,id'],
        'supplier_code' => ['nullable', 'exists:mp_suppliers,code'],
        'cost' => ['required', 'numeric', 'min:0'],
        'lead_days' => ['nullable', 'integer', 'min:0'],
        'sla' => ['nullable', 'string', 'max:120'],
        'capacity' => ['nullable', 'integer', 'min:0'],
        'contract_reference' => ['nullable', 'string', 'max:60'],
        'spec' => ['nullable', 'string'],
    ];

    public function store(Request $request)
    {
        Gate::authorize('create', ServiceOptionItem::class);

        $data = $request->validate(array_merge(self::RULES, [
            'item_group_id' => ['required', Rule::exists('mp_item_groups', 'id')->where('event_id', $request->input('event_id'))],
            'item_subgroup_id' => ['required', Rule::exists('mp_item_subgroups', 'id')->where('event_id', $request->input('event_id'))],
        ]));

        $item = ServiceOptionItem::create([
            'code' => $this->generateCode($data['name']),
            'event_id' => $data['event_id'],
            'name' => $data['name'],
            'supplier_id' => !empty($data['supplier_code']) ? Supplier::where('code', $data['supplier_code'])->value('id') : null,
            'cost' => $data['cost'],
            'lead_days' => $data['lead_days'] ?? 0,
            'sla' => $data['sla'] ?? '',
            'capacity' => $data['capacity'] ?? 0,
            'contract_reference' => $data['contract_reference'] ?? null,
            'spec' => $data['spec'] ?? null,
            'item_group_id' => $data['item_group_id'] ?? null,
            'item_subgroup_id' => $data['item_subgroup_id'] ?? null,
        ]);

        return response()->json($this->present($item->load(['supplier', 'itemGroup', 'itemSubgroup'])->loadCount('bundles')), 201);
    }

    public function update(Request $request, ServiceOptionItem $serviceOptionItem)
    {
        Gate::authorize('update', $serviceOptionItem);

        $eventId = $request->input('event_id', $serviceOptionItem->event_id);

        $data = $request->validate(array_merge(
            array_map(
                fn ($rules) => array_map(fn ($r) => $r === 'required' ? 'sometimes' : $r, $rules),
                self::RULES
            ),
            [
                'item_group_id' => ['sometimes', Rule::exists('mp_item_groups', 'id')->where('event_id', $eventId)],
                'item_subgroup_id' => ['sometimes', Rule::exists('mp_item_subgroups', 'id')->where('event_id', $eventId)],
            ]
        ));

        $update = $data;
        if (array_key_exists('supplier_code', $data)) {
            $update['supplier_id'] = !empty($data['supplier_code']) ? Supplier::where('code', $data['supplier_code'])->value('id') : null;
        }
        unset($update['supplier_code']);

        $serviceOptionItem->update($update);

        return response()->json($this->present($serviceOptionItem->load(['supplier', 'itemGroup', 'itemSubgroup'])->loadCount('bundles')));
    }

    public function destroy(ServiceOptionItem $serviceOptionItem)
    {
        Gate::authorize('delete', $serviceOptionItem);

        $serviceOptionItem->delete();

        return response()->json(['message' => 'Service option removed successfully.']);
    }

    /** Mirrors the pattern used for bundle codes, with a retry loop since this table sees more traffic. */
    private function generateCode(string $name): string
    {
        $nameTail = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 6)) ?: 'SVC';

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = sprintf('SVC-%s-%d', $nameTail, random_int(100, 999));
            if (! ServiceOptionItem::where('code', $code)->exists()) {
                return $code;
            }
        }

        return sprintf('SVC-%s-%d', $nameTail, random_int(1000, 9999));
    }

    private function present(ServiceOptionItem $item): array
    {
        return [
            'id' => $item->code,
            'dbId' => $item->id,
            'eventId' => $item->event_id,
            'name' => $item->name,
            'supplierCode' => $item->supplier_code,
            'supplierName' => $item->supplier?->name,
            'cost' => (float) $item->cost,
            'lead' => $item->lead_days,
            'sla' => $item->sla,
            'capacity' => $item->capacity,
            'contract' => $item->contract_reference ?? '—',
            'spec' => $item->spec ?? '—',
            'itemGroupId' => $item->item_group_id,
            'itemGroupLabel' => $item->itemGroup?->label,
            'itemSubgroupId' => $item->item_subgroup_id,
            'itemSubgroupLabel' => $item->itemSubgroup?->name,
            'usageCount' => $item->bundles_count ?? 0,
        ];
    }
}
