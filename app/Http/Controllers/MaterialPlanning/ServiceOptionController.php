<?php

namespace App\Http\Controllers\MaterialPlanning;

use App\Http\Controllers\Controller;
use App\Models\MaterialPlanning\CatalogItem;
use App\Models\MaterialPlanning\ServiceOption;
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
            'is_default' => ['nullable', 'boolean'],
        ]);

        $item = CatalogItem::findOrFail($data['sku']);
        Gate::authorize('createForItem', [ServiceOption::class, $item]);

        $skuTail = substr($data['sku'], strrpos($data['sku'], '-') + 1);
        $supplierTail = substr($data['supplier_code'], -3);

        $option = ServiceOption::create([
            'code' => sprintf('SO-%s-%s-%d', $skuTail, $supplierTail, random_int(10, 99)),
            'sku' => $data['sku'],
            'supplier_code' => $data['supplier_code'],
            'name' => $data['name'],
            'cost' => $data['cost'],
            'lead_days' => $data['lead_days'] ?? 0,
            'sla' => $data['sla'] ?? '',
            'capacity' => $data['capacity'] ?? 0,
            'contract_reference' => $data['contract_reference'] ?? null,
            'spec' => $data['spec'] ?? null,
            'status' => 'active',
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
            'status' => ['sometimes', 'in:preferred,active,suspended'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

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
            'status' => $option->status,
            'isDefault' => $option->is_default,
        ];
    }
}
