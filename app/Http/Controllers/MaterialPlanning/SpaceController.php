<?php

namespace App\Http\Controllers\MaterialPlanning;

use App\Http\Controllers\Controller;
use App\Models\MaterialPlanning\Space;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SpaceController extends Controller
{
    public function data()
    {
        $spaces = Space::with(['area', 'status'])->orderBy('name')->get();

        return response()->json($spaces->map(fn (Space $s) => $this->present($s)));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Space::class);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:mp_spaces,code'],
            'event_id' => ['required', 'exists:events,id'],
            'area_id' => ['required', Rule::exists('mp_areas', 'id')->where('event_id', $request->input('event_id'))],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'status_id' => ['sometimes', 'exists:global_statuses,id'],
        ]);
        $data['status_id'] = $data['status_id'] ?? 1;

        $space = Space::create($data);

        return response()->json($this->present($space->load('area', 'status')), 201);
    }

    public function update(Request $request, Space $space)
    {
        Gate::authorize('update', $space);

        $eventId = $request->input('event_id', $space->event_id);

        $data = $request->validate([
            'event_id' => ['sometimes', 'exists:events,id'],
            'area_id' => ['sometimes', Rule::exists('mp_areas', 'id')->where('event_id', $eventId)],
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'status_id' => ['sometimes', 'exists:global_statuses,id'],
        ]);

        $space->update($data);

        return response()->json($this->present($space->load('area', 'status')));
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
            'eventId' => $space->event_id,
            'name' => $space->name,
            'description' => $space->description,
            'area' => $space->area_id,
            'areaLabel' => $space->area?->label,
            'statusId' => $space->status_id,
            'statusName' => $space->status?->name,
            'statusColor' => $space->status?->color,
        ];
    }
}
