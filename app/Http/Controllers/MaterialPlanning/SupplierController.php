<?php

namespace App\Http\Controllers\MaterialPlanning;

use App\Http\Controllers\Controller;
use App\Models\MaterialPlanning\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SupplierController extends Controller
{
    public function data()
    {
        $suppliers = Supplier::with('owner')->orderBy('name')->get();

        return response()->json($suppliers->map(fn (Supplier $s) => $this->present($s)));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Supplier::class);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^SUP-[A-Z0-9]+$/', 'unique:mp_suppliers,code'],
            'name' => ['required', 'string', 'max:150'],
            'kind' => ['required', 'string', 'max:100'],
            'status' => ['nullable', 'in:preferred,active,suspended'],
            'msa_reference' => ['nullable', 'string', 'max:60'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
        ]);
        $data['status'] = $data['status'] ?? 'active';

        $supplier = Supplier::create($data);

        return response()->json($this->present($supplier->load('owner')), 201);
    }

    public function update(Request $request, Supplier $supplier)
    {
        Gate::authorize('update', $supplier);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'kind' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', 'in:preferred,active,suspended'],
            'msa_reference' => ['nullable', 'string', 'max:60'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $supplier->update($data);

        return response()->json($this->present($supplier->load('owner')));
    }

    public function destroy(Supplier $supplier)
    {
        Gate::authorize('delete', $supplier);

        $supplier->delete();

        return response()->json(['message' => 'Supplier deleted successfully.']);
    }

    private function present(Supplier $supplier): array
    {
        return [
            'id' => $supplier->code,
            'name' => $supplier->name,
            'kind' => $supplier->kind,
            'status' => $supplier->status,
            'msa' => $supplier->msa_reference ?? '—',
            'owner' => $supplier->owner?->initials,
        ];
    }
}
