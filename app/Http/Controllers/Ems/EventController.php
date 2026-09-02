<?php

namespace App\Http\Controllers\Ems;

use App\Http\Controllers\Controller;
use App\Models\Ems\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class EventController extends Controller
{
    public function index()
    {
        return Inertia::render('Events/Index');
    }

    /** Bootstrap Table endpoint – GET /api/events */
    public function data(Request $request)
    {
        $limit  = max(1, min((int) $request->input('limit', 10), 100));
        $offset = (int) $request->input('offset', 0);
        $order  = in_array(strtolower($request->input('order', 'desc')), ['asc', 'desc'])
            ? strtolower($request->input('order', 'desc'))
            : 'desc';
        $sort   = $request->input('sort', 'id');
        $search = $request->input('search');

        $sortMap = [
            'id'          => 'events.id',
            'name'        => 'events.name',
            'status_name' => 'global_statuses.name',
            'created_at'  => 'events.created_at',
            'updated_at'  => 'events.updated_at',
        ];
        $sortCol = $sortMap[$sort] ?? 'events.id';

        $query = Event::query()
            ->leftJoin('global_statuses', 'events.active_flag', '=', 'global_statuses.id')
            ->select([
                'events.id',
                'events.name',
                'events.code',
                'events.start_date',
                'events.end_date',
                'events.event_logo',
                'events.active_flag',
                'events.created_at',
                'events.updated_at',
                'global_statuses.name  as status_name',
                'global_statuses.color as status_color',
            ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('events.name', 'like', "%{$search}%")
                    ->orWhere('global_statuses.name', 'like', "%{$search}%");
            });
        }

        $query->orderBy($sortCol, $order);

        $total = $query->count();
        $rows  = $query->offset($offset)->limit($limit)->get()
            ->map(function ($e) {
                $venues = \App\Models\Ems\Event::find($e->id)
                    ?->venues()->orderBy('title')->get(['venues.id', 'venues.title'])
                    ->toArray() ?? [];
                return [
                    'id'           => $e->id,
                    'name'         => $e->name,
                    'code'         => $e->code,
                    'start_date'   => $e->start_date?->format('Y-m-d'),
                    'end_date'     => $e->end_date?->format('Y-m-d'),
                    'event_logo'   => $e->event_logo,
                    'logo_url'     => $e->event_logo ? asset('storage/event-logos/' . $e->event_logo) : null,
                    'active_flag'  => $e->active_flag,
                    'status_name'  => $e->status_name  ?? '',
                    'status_color' => $e->status_color ?? '',
                    'created_at'   => $e->created_at?->format('Y-m-d'),
                    'updated_at'   => $e->updated_at?->format('Y-m-d'),
                    'venues'       => $venues,
                    'venue_ids'    => array_column($venues, 'id'),
                ];
            })
            ->values();

        return response()->json(['total' => $total, 'rows' => $rows]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'code'        => ['nullable', 'string', 'max:20', 'unique:events,code'],
            'start_date'  => ['nullable', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
            'active_flag' => ['required', 'exists:global_statuses,id'],
            'logo'        => ['nullable', 'image', 'max:2048'],
            'venue_ids'   => ['nullable', 'array'],
            'venue_ids.*' => ['exists:venues,id'],
        ]);

        if ($request->hasFile('logo')) {
            $data['event_logo'] = basename($request->file('logo')->store('event-logos', 'public'));
        }

        $venueIds = $data['venue_ids'] ?? [];
        unset($data['logo'], $data['venue_ids']);

        $event = Event::create($data);
        $event->venues()->sync($venueIds);

        return back()->with('success', 'Event created successfully.');
    }

    public function update(Request $request, Event $event)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'code'        => ['nullable', 'string', 'max:20', 'unique:events,code,' . $event->id],
            'start_date'  => ['nullable', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
            'active_flag' => ['required', 'exists:global_statuses,id'],
            'logo'        => ['nullable', 'image', 'max:2048'],
            'venue_ids'   => ['nullable', 'array'],
            'venue_ids.*' => ['exists:venues,id'],
        ]);

        if ($request->hasFile('logo')) {
            if ($event->event_logo) {
                Storage::disk('public')->delete('event-logos/' . $event->event_logo);
            }
            $data['event_logo'] = basename($request->file('logo')->store('event-logos', 'public'));
        }

        $venueIds = $data['venue_ids'] ?? [];
        unset($data['logo'], $data['venue_ids']);

        $event->update($data);
        $event->venues()->sync($venueIds);

        return back()->with('success', 'Event updated successfully.');
    }

    public function destroy(Event $event)
    {
        $event->delete();

        return response()->json(['message' => 'Event deleted successfully.']);

        return back()->with('success', 'Event deleted successfully.');
    }
}
