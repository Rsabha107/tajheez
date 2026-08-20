<?php

namespace App\Http\Controllers\MaterialPlanning;

use App\Http\Controllers\Controller;
use App\Models\MaterialPlanning\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DomainController extends Controller
{
    public function data()
    {
        $domains = Domain::with('status')->withCount('itemGroups')->orderBy('sort_order')->get();

        return response()->json($domains->map(fn (Domain $d) => $this->present($d)));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Domain::class);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:8', 'regex:/^[A-Z0-9_]+$/', 'unique:mp_domains,code'],
            'label' => ['required', 'string', 'max:50'],
            'color' => ['required', 'string', 'max:9'],
            'chip' => ['required', 'string', 'max:9'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status_id' => ['sometimes', 'exists:global_statuses,id'],
        ]);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['status_id'] = $data['status_id'] ?? 1;

        $domain = Domain::create($data);

        return response()->json($this->present($domain), 201);
    }

    public function update(Request $request, Domain $domain)
    {
        Gate::authorize('update', $domain);

        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:50'],
            'color' => ['sometimes', 'string', 'max:9'],
            'chip' => ['sometimes', 'string', 'max:9'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'status_id' => ['sometimes', 'exists:global_statuses,id'],
        ]);

        $domain->update($data);

        return response()->json($this->present($domain));
    }

    public function destroy(Domain $domain)
    {
        Gate::authorize('delete', $domain);

        $domain->delete();

        return response()->json(['message' => 'Domain deleted successfully.']);
    }

    private function present(Domain $domain): array
    {
        return [
            'id' => $domain->id,
            'code' => $domain->code,
            'label' => $domain->label,
            'color' => $domain->color,
            'chip' => $domain->chip,
            'description' => $domain->description,
            'sortOrder' => $domain->sort_order,
            'itemGroupsCount' => $domain->item_groups_count ?? $domain->itemGroups()->count(),
            'statusId' => $domain->status_id,
            'statusName' => $domain->status?->name,
            'statusColor' => $domain->status?->color,
        ];
    }
}
