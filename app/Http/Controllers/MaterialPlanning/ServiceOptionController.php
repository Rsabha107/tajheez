<?php

namespace App\Http\Controllers\MaterialPlanning;

use App\Http\Controllers\Controller;
use App\Models\MaterialPlanning\ServiceOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ServiceOptionController extends Controller
{
    private const ITEM_RULES = [
        'service_option_item_ids' => ['required', 'array', 'min:1'],
        'service_option_item_ids.*' => ['integer', 'exists:mp_service_option_items,id'],
    ];

    public function store(Request $request)
    {
        Gate::authorize('create', ServiceOption::class);

        $data = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:180'],
            'classification_id' => ['nullable', 'exists:classifications,id'],
            'status_id' => ['nullable', 'exists:global_statuses,id'],
            'is_default' => ['nullable', 'boolean'],
            'item_group_id' => ['nullable', 'exists:mp_item_groups,id'],
            'item_subgroup_id' => ['nullable', 'exists:mp_item_subgroups,id'],
        ], self::ITEM_RULES));

        $option = ServiceOption::create([
            'code' => $this->generateCode($data['name']),
            'name' => $data['name'],
            'classification_id' => $data['classification_id'] ?? 2,
            'status_id' => $data['status_id'] ?? 1,
            'is_default' => $data['is_default'] ?? false,
            'item_group_id' => $data['item_group_id'] ?? null,
            'item_subgroup_id' => $data['item_subgroup_id'] ?? null,
        ]);

        $this->syncItems($option, $data['service_option_item_ids']);

        return response()->json($this->present($option->load(['services.supplier', 'itemGroup', 'itemSubgroup'])), 201);
    }

    public function update(Request $request, ServiceOption $serviceOption)
    {
        Gate::authorize('update', $serviceOption);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:180'],
            'classification_id' => ['sometimes', 'exists:classifications,id'],
            'status_id' => ['sometimes', 'exists:global_statuses,id'],
            'is_default' => ['sometimes', 'boolean'],
            'item_group_id' => ['nullable', 'exists:mp_item_groups,id'],
            'item_subgroup_id' => ['nullable', 'exists:mp_item_subgroups,id'],
            'service_option_item_ids' => ['sometimes', 'array', 'min:1'],
            'service_option_item_ids.*' => ['integer', 'exists:mp_service_option_items,id'],
        ]);

        $itemIds = $data['service_option_item_ids'] ?? null;
        unset($data['service_option_item_ids']);

        $serviceOption->update($data);

        if ($itemIds !== null) {
            $this->syncItems($serviceOption, $itemIds);
        }

        return response()->json($this->present($serviceOption->load(['services.supplier', 'itemGroup', 'itemSubgroup'])));
    }

    public function destroy(ServiceOption $serviceOption)
    {
        Gate::authorize('delete', $serviceOption);

        $serviceOption->delete();

        return response()->json(['message' => 'Service option deleted successfully.']);
    }

    /** Replaces a bundle's whole item list (in order) — the modal always submits the full set. */
    private function syncItems(ServiceOption $option, array $itemIds): void
    {
        $pivot = [];
        foreach (array_values($itemIds) as $i => $id) {
            $pivot[$id] = ['sort_order' => $i];
        }

        $option->services()->sync($pivot);
    }

    private function generateCode(string $name): string
    {
        $nameTail = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 6)) ?: 'SVC';

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = sprintf('SO-%s-%d', $nameTail, random_int(100, 999));
            if (! ServiceOption::where('code', $code)->exists()) {
                return $code;
            }
        }

        return sprintf('SO-%s-%d', $nameTail, random_int(1000, 9999));
    }

    /** Shape matches the mock service-option row exactly (id = code), for drop-in frontend compatibility. */
    private function present(ServiceOption $option): array
    {
        $services = $option->services;

        return [
            'id' => $option->code,
            'dbId' => $option->id,
            'name' => $option->name,
            'cost' => (float) $services->sum('cost'),
            'lead' => (int) $services->max('lead_days'),
            // Distinct suppliers behind this bundle's services, for a quick summary display.
            'suppliers' => $services->pluck('supplier_code')->unique()->values()->all(),
            'classificationId' => $option->classification_id,
            'classificationName' => $option->classification?->name,
            'classificationColor' => $option->classification?->color,
            'statusId' => $option->status_id,
            'statusName' => $option->status?->name,
            'statusColor' => $option->status?->color,
            'isDefault' => $option->is_default,
            'itemGroupId' => $option->item_group_id,
            'itemGroupLabel' => $option->itemGroup?->label,
            'itemSubgroupId' => $option->item_subgroup_id,
            'itemSubgroupLabel' => $option->itemSubgroup?->name,
            'services' => $services->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'supplier' => $s->supplier_code,
                'cost' => (float) $s->cost,
                'lead' => $s->lead_days,
                'sla' => $s->sla,
                'capacity' => $s->capacity,
                'contract' => $s->contract_reference ?? '—',
                'spec' => $s->spec ?? '—',
            ])->values()->all(),
        ];
    }
}
