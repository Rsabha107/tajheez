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
        $suppliers = Supplier::with(['classification', 'status'])
            ->withAvg('serviceOptions', 'lead_days')
            ->orderBy('name')->get();

        return response()->json($suppliers->map(fn (Supplier $s) => $this->present($s)));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Supplier::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'kind' => ['required', 'string', 'max:100'],
            'classification_id' => ['nullable', 'exists:classifications,id'],
            'status_id' => ['nullable', 'exists:global_statuses,id'],
            'msa_reference' => ['nullable', 'string', 'max:60'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
        ]);
        $data['classification_id'] = $data['classification_id'] ?? 2;
        $data['status_id'] = $data['status_id'] ?? 1;
        $data['code'] = $this->generateCode($data['name']);

        $supplier = Supplier::create($data);

        return response()->json($this->present($supplier->load('classification', 'status')->loadAvg('serviceOptions', 'lead_days')), 201);
    }

    public function update(Request $request, Supplier $supplier)
    {
        Gate::authorize('update', $supplier);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'kind' => ['sometimes', 'string', 'max:100'],
            'classification_id' => ['sometimes', 'exists:classifications,id'],
            'status_id' => ['sometimes', 'exists:global_statuses,id'],
            'msa_reference' => ['nullable', 'string', 'max:60'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $supplier->update($data);

        return response()->json($this->present($supplier->load('classification', 'status')->loadAvg('serviceOptions', 'lead_days')));
    }

    public function destroy(Supplier $supplier)
    {
        Gate::authorize('delete', $supplier);

        $supplier->delete();

        return response()->json(['message' => 'Supplier deleted successfully.']);
    }

    /** SUP-<3 letters from the name>, with a numeric suffix appended on collision. */
    private function generateCode(string $name): string
    {
        $base = 'SUP-' . str_pad(strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3)), 3, 'X');

        $code = $base;
        $suffix = 1;
        while (Supplier::where('code', $code)->exists()) {
            $suffix++;
            $code = $base . $suffix;
        }

        return $code;
    }

    private function present(Supplier $supplier): array
    {
        return [
            'id' => $supplier->id,
            'code' => $supplier->code,
            'name' => $supplier->name,
            'kind' => $supplier->kind,
            'classificationId' => $supplier->classification_id,
            'classificationName' => $supplier->classification?->name,
            'classificationColor' => $supplier->classification?->color,
            'statusId' => $supplier->status_id,
            'statusName' => $supplier->status?->name,
            'statusColor' => $supplier->status?->color,
            'msa' => $supplier->msa_reference ?? '—',
            'contactName' => $supplier->contact_name,
            'contactPhone' => $supplier->contact_phone,
            'avgLeadDays' => $supplier->service_options_avg_lead_days !== null
                ? (int) round($supplier->service_options_avg_lead_days)
                : null,
        ];
    }
}
