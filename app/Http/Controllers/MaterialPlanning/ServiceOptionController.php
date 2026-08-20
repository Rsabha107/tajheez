<?php

namespace App\Http\Controllers\MaterialPlanning;

use App\Http\Controllers\Controller;
use App\Models\MaterialPlanning\CatalogItem;
use App\Models\MaterialPlanning\ServiceOption;
use App\Models\MaterialPlanning\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ServiceOptionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'sku' => ['required', 'exists:mp_catalog_items,sku'],
            'name' => ['required', 'string', 'max:180'],
            'supplier_code' => ['required', 'exists:mp_suppliers,code'],
            'cost' => ['required', 'numeric', 'min:0'],
            'lead_days' => ['nullable', 'integer', 'min:0'],
            'sla' => ['nullable', 'string', 'max:120'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'contract_reference' => ['nullable', 'string', 'max:60'],
            'spec' => ['nullable', 'string'],
            'classification_id' => ['nullable', 'exists:classifications,id'],
            'status_id' => ['nullable', 'exists:global_statuses,id'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $item = CatalogItem::where('sku', $data['sku'])->firstOrFail();
        Gate::authorize('createForItem', [ServiceOption::class, $item]);

        $supplier = Supplier::where('code', $data['supplier_code'])->firstOrFail();

        $skuTail = substr($data['sku'], strrpos($data['sku'], '-') + 1);
        $supplierTail = substr($data['supplier_code'], -3);

        $option = ServiceOption::create([
            'code' => sprintf('SO-%s-%s-%d', $skuTail, $supplierTail, random_int(10, 99)),
            'catalog_item_id' => $item->id,
            'supplier_id' => $supplier->id,
            'name' => $data['name'],
            'cost' => $data['cost'],
            'lead_days' => $data['lead_days'] ?? 0,
            'sla' => $data['sla'] ?? '',
            'capacity' => $data['capacity'] ?? 0,
            'contract_reference' => $data['contract_reference'] ?? null,
            'spec' => $data['spec'] ?? null,
            'classification_id' => $data['classification_id'] ?? 2,
            'status_id' => $data['status_id'] ?? 1,
            'is_default' => $data['is_default'] ?? false,
        ]);

        return response()->json($this->present($option), 201);
    }

    public function update(Request $request, ServiceOption $serviceOption)
    {
        Gate::authorize('update', $serviceOption);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:180'],
            'supplier_code' => ['sometimes', 'exists:mp_suppliers,code'],
            'cost' => ['sometimes', 'numeric', 'min:0'],
            'lead_days' => ['sometimes', 'integer', 'min:0'],
            'sla' => ['sometimes', 'string', 'max:120'],
            'capacity' => ['sometimes', 'integer', 'min:0'],
            'contract_reference' => ['nullable', 'string', 'max:60'],
            'spec' => ['nullable', 'string'],
            'classification_id' => ['sometimes', 'exists:classifications,id'],
            'status_id' => ['sometimes', 'exists:global_statuses,id'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['supplier_code'])) {
            $data['supplier_id'] = Supplier::where('code', $data['supplier_code'])->value('id');
            unset($data['supplier_code']);
        }

        $serviceOption->update($data);

        return response()->json($this->present($serviceOption));
    }

    public function destroy(ServiceOption $serviceOption)
    {
        Gate::authorize('delete', $serviceOption);

        $serviceOption->delete();

        return response()->json(['message' => 'Service option deleted successfully.']);
    }

    /** Shape matches the mock service-option row exactly (id = code), for drop-in frontend compatibility. */
    private function present(ServiceOption $option): array
    {
        return [
            'id' => $option->code,
            'sku' => $option->sku,
            'name' => $option->name,
            'supplier' => $option->supplier_code,
            'cost' => (float) $option->cost,
            'lead' => $option->lead_days,
            'sla' => $option->sla,
            'capacity' => $option->capacity,
            'contract' => $option->contract_reference ?? '—',
            'spec' => $option->spec ?? '—',
            'classificationId' => $option->classification_id,
            'classificationName' => $option->classification?->name,
            'classificationColor' => $option->classification?->color,
            'statusId' => $option->status_id,
            'statusName' => $option->status?->name,
            'statusColor' => $option->status?->color,
            'isDefault' => $option->is_default,
        ];
    }
}
