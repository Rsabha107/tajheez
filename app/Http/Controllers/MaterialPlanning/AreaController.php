<?php

namespace App\Http\Controllers\MaterialPlanning;

use App\Http\Controllers\Controller;
use App\Models\MaterialPlanning\Area;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AreaController extends Controller
{
    public function data()
    {
        $areas = Area::with('status')->withCount('spaces')->orderBy('sort_order')->get();

        return response()->json($areas->map(fn (Area $a) => $this->present($a)));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Area::class);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_]+$/', 'unique:mp_areas,code'],
            'label' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status_id' => ['sometimes', 'exists:global_statuses,id'],
        ]);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['status_id'] = $data['status_id'] ?? 1;

        $area = Area::create($data);

        return response()->json($this->present($area), 201);
    }

    public function update(Request $request, Area $area)
    {
        Gate::authorize('update', $area);

        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'status_id' => ['sometimes', 'exists:global_statuses,id'],
        ]);

        $area->update($data);

        return response()->json($this->present($area));
    }

    public function destroy(Area $area)
    {
        Gate::authorize('delete', $area);

        $area->delete();

        return response()->json(['message' => 'Area deleted successfully.']);
    }

    private function present(Area $area): array
    {
        return [
            'id' => $area->id,
            'code' => $area->code,
            'label' => $area->label,
            'description' => $area->description,
            'sortOrder' => $area->sort_order,
            'spacesCount' => $area->spaces_count ?? $area->spaces()->count(),
            'statusId' => $area->status_id,
            'statusName' => $area->status?->name,
            'statusColor' => $area->status?->color,
        ];
    }
}
