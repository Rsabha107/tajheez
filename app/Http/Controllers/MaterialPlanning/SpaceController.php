<?php

namespace App\Http\Controllers\MaterialPlanning;

use App\Http\Controllers\Controller;
use App\Models\MaterialPlanning\Space;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SpaceController extends Controller
{
    public function data()
    {
        $spaces = Space::with('area')->orderBy('name')->get();

        return response()->json($spaces->map(fn (Space $s) => $this->present($s)));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Space::class);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:mp_spaces,code'],
            'area_code' => ['required', 'exists:mp_areas,code'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $space = Space::create($data);

        return response()->json($this->present($space->load('area')), 201);
    }

    public function update(Request $request, Space $space)
    {
        Gate::authorize('update', $space);

        $data = $request->validate([
            'area_code' => ['sometimes', 'exists:mp_areas,code'],
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $space->update($data);

        return response()->json($this->present($space->load('area')));
    }

    public function destroy(Space $space)
    {
        Gate::authorize('delete', $space);

        $space->delete();

        return response()->json(['message' => 'Space deleted successfully.']);
    }

    private function present(Space $space): array
    {
        return [
            'id' => $space->id,
            'code' => $space->code,
            'name' => $space->name,
            'description' => $space->description,
            'area' => $space->area_code,
            'areaLabel' => $space->area?->label,
        ];
    }
}
