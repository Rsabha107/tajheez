<?php

namespace App\Http\Controllers;

use App\Models\Classification;
use App\Models\Ems\Event;
use App\Models\Ems\FunctionalArea;
use App\Models\Ems\Venue;
use App\Models\GlobalStatus;
use App\Models\MaterialPlanning\Area;
use App\Models\MaterialPlanning\CatalogItem;
use App\Models\MaterialPlanning\ChangeOrder;
use App\Models\MaterialPlanning\Domain;
use App\Models\MaterialPlanning\ItemGroup;
use App\Models\MaterialPlanning\ItemSubgroup;
use App\Models\MaterialPlanning\MaterialRequest;
use App\Models\MaterialPlanning\ServiceOption;
use App\Models\MaterialPlanning\Space;
use App\Models\MaterialPlanning\Supplier;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MaterialPlanningController extends Controller
{
    /** Request lifecycle metadata — display-only, no admin-editability implied, so kept as a constant rather than a table. */
    private const STATUSES = [
        'draft'     => ['label' => 'Draft',      'dot' => '#9ca3af', 'bg' => '#f3f4f6', 'fg' => '#374151'],
        'submitted' => ['label' => 'Submitted',  'dot' => '#1d4ed8', 'bg' => '#dbeafe', 'fg' => '#1e3a8a'],
        'l1'        => ['label' => 'L1 Review',  'dot' => '#b45309', 'bg' => '#fef3c7', 'fg' => '#92400e'],
        'l2'        => ['label' => 'Category',   'dot' => '#b45309', 'bg' => '#fef3c7', 'fg' => '#92400e'],
        'finance'   => ['label' => 'Finance',    'dot' => '#b45309', 'bg' => '#fef3c7', 'fg' => '#92400e'],
        'approved'  => ['label' => 'Approved',   'dot' => '#166534', 'bg' => '#dcfce7', 'fg' => '#14532d'],
        'changed'   => ['label' => 'Change Req', 'dot' => '#7c2d12', 'bg' => '#fde7d3', 'fg' => '#7c2d12'],
        'rejected'  => ['label' => 'Rejected',   'dot' => '#991b1b', 'bg' => '#fee2e2', 'fg' => '#7f1d1d'],
        'fulfilled' => ['label' => 'Fulfilled',  'dot' => '#0f766e', 'bg' => '#ccfbf1', 'fg' => '#134e4a'],
    ];

    /** Change-order state metadata — same reasoning as STATUSES above. */
    private const CO_STATES = [
        'draft'    => ['label' => 'Draft',    'bg' => '#f3f4f6', 'fg' => '#374151', 'dot' => '#9ca3af'],
        'pending'  => ['label' => 'Pending',  'bg' => '#fef3c7', 'fg' => '#92400e', 'dot' => '#b45309'],
        'approved' => ['label' => 'Approved', 'bg' => '#dcfce7', 'fg' => '#14532d', 'dot' => '#166534'],
        'rejected' => ['label' => 'Rejected', 'bg' => '#fee2e2', 'fg' => '#7f1d1d', 'dot' => '#b91c1c'],
    ];

    public function index(): Response
    {
        $user = auth()->user();
        $isAdmin = $user?->hasRole('admin') ?? false;
        $userFunctionalAreaIds = $user?->functionalAreas()->pluck('functional_areas.id')->all() ?? [];

        $permissions = [
            'isAdmin' => $isAdmin,
            'managedDomain' => $user?->managed_domain,
            'functionalAreaIds' => $userFunctionalAreaIds,
        ];

        $domains = Domain::with('status')->withCount('itemGroups')->orderBy('sort_order')->get();
        $domainsByCode = $domains->keyBy('code');

        $areas = Area::with('status')->withCount('spaces')->orderBy('sort_order')->get();
        $spaces = Space::with(['area', 'status'])->orderBy('name')->get();

        $itemGroups = ItemGroup::with(['domain', 'status'])->withCount('subgroups')->orderBy('sort_order')->get();
        $itemSubgroups = ItemSubgroup::with(['group', 'status'])->orderBy('name')->get();

        $entityStatuses = GlobalStatus::where('is_active', 1)->get();
        $classifications = Classification::where('is_active', 1)->get();

        $suppliers = Supplier::with(['classification', 'status'])->withAvg('serviceOptions', 'lead_days')->orderBy('name')->get();
        $catalogItems = CatalogItem::orderBy('sku')->get();
        $serviceOptions = ServiceOption::with(['classification', 'status'])->orderBy('id')->get();

        $requests = MaterialRequest::with(['venue', 'owner', 'functionalArea', 'lines.catalogItem'])
            ->when(! $isAdmin, fn ($q) => $q->whereIn('functional_area_id', $userFunctionalAreaIds))
            ->orderByDesc('id')->get();

        // Requests are scoped to a Functional Area — a regular user only ever picks
        // from the FA(s) they themselves are assigned to when raising a new request.
        $functionalAreas = FunctionalArea::query()
            ->when(! $isAdmin, fn ($q) => $q->whereIn('id', $userFunctionalAreaIds))
            ->orderBy('title')->get();
        $changeOrders = ChangeOrder::with(['request.venue', 'raisedBy', 'lines.catalogItem', 'lines.serviceOptionAfter.supplier'])
            ->orderByDesc('id')->get();

        // "People" is derived from real users: anyone referenced as an owner/approver,
        // plus anyone with a Material Planning role, so the directory always covers
        // domain managers/admins even if they don't currently own a seeded record.
        $referencedUserIds = collect()
            ->merge($requests->pluck('owner_user_id'))
            ->merge($changeOrders->pluck('raised_by_user_id'))
            ->filter()
            ->unique();
        $roleBasedUserIds = User::where(function ($q) {
            $q->whereNotNull('managed_domain')->orWhereHas('roles', fn ($r) => $r->where('name', 'admin'));
        })->pluck('id');
        $peopleIds = $referencedUserIds->merge($roleBasedUserIds)->unique()->values();

        $people = User::whereIn('id', $peopleIds)->get()->map(function (User $u) use ($domainsByCode) {
            $role = $u->hasRole('admin')
                ? 'Admin'
                : ($u->managed_domain
                    ? 'Category Lead — ' . ($domainsByCode->get($u->managed_domain)?->label ?? $u->managed_domain)
                    : 'Team Member');

            return ['id' => $u->id, 'initials' => $u->initials, 'name' => $u->name, 'role' => $role, 'org' => 'HQ'];
        })->values()->all();

        // Active event is whatever the user last selected in this session (see
        // setActiveEvent()). If nothing has been picked yet, fall back to whichever
        // real event Material Planning demo data is actually attached to, then the
        // most recently created real event.
        $event = ($sessionEventId = session('mp_active_event_id'))
            ? Event::find($sessionEventId)
            : null;
        $event ??= Event::whereHas('materialRequests')->latest('id')->first()
            ?? Event::latest('id')->first();

        return Inertia::render('MaterialPlanning/Index', [
            'event' => $event ? [
                'id' => $event->id,
                'name' => $event->name,
                'window' => ($event->start_date && $event->end_date)
                    ? $event->start_date->format('M j') . ' – ' . $event->end_date->format('M j, Y')
                    : null,
                'daysOut' => $event->start_date ? max(0, (int) round(now()->diffInDays($event->start_date, false))) : null,
            ] : null,

            'venues' => Venue::orderBy('title')->get()->map(fn (Venue $v) => [
                'id' => $v->id,
                'code' => $v->short_name,
                'name' => $v->title,
                'city' => $v->city,
                'sites' => $v->sites,
            ])->values()->all(),

            'domains' => $domains->map(fn (Domain $d) => [
                'id' => $d->id,
                'code' => $d->code,
                'label' => $d->label,
                'color' => $d->color,
                'chip' => $d->chip,
                'desc' => $d->description,
                'sortOrder' => $d->sort_order,
                'itemGroupsCount' => $d->item_groups_count,
                'statusId' => $d->status_id,
                'statusName' => $d->status?->name,
                'statusColor' => $d->status?->color,
            ])->values()->all(),

            'areas' => $areas->map(fn (Area $a) => [
                'id' => $a->id,
                'code' => $a->code,
                'label' => $a->label,
                'description' => $a->description,
                'sortOrder' => $a->sort_order,
                'spacesCount' => $a->spaces_count,
                'statusId' => $a->status_id,
                'statusName' => $a->status?->name,
                'statusColor' => $a->status?->color,
            ])->values()->all(),

            'spaces' => $spaces->map(fn (Space $s) => [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'description' => $s->description,
                'area' => $s->area_id,
                'areaLabel' => $s->area?->label,
                'statusId' => $s->status_id,
                'statusName' => $s->status?->name,
                'statusColor' => $s->status?->color,
            ])->values()->all(),

            'itemGroups' => $itemGroups->map(fn (ItemGroup $g) => [
                'id' => $g->id,
                'code' => $g->code,
                'domain' => $g->domain_id,
                'domainLabel' => $g->domain?->label,
                'label' => $g->label,
                'description' => $g->description,
                'sortOrder' => $g->sort_order,
                'subgroupsCount' => $g->subgroups_count,
                'statusId' => $g->status_id,
                'statusName' => $g->status?->name,
                'statusColor' => $g->status?->color,
            ])->values()->all(),

            'itemSubgroups' => $itemSubgroups->map(fn (ItemSubgroup $s) => [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'description' => $s->description,
                'group' => $s->group_id,
                'groupLabel' => $s->group?->label,
                'statusId' => $s->status_id,
                'statusName' => $s->status?->name,
                'statusColor' => $s->status?->color,
            ])->values()->all(),

            'entityStatuses' => $entityStatuses->map(fn (GlobalStatus $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'color' => $s->color,
            ])->values()->all(),

            'classifications' => $classifications->map(fn (Classification $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'color' => $c->color,
            ])->values()->all(),

            'statuses' => self::STATUSES,

            'people' => $people,

            'functionalAreas' => $functionalAreas->map(fn (FunctionalArea $fa) => [
                'id' => $fa->id,
                'code' => $fa->fa_code,
                'title' => $fa->title,
            ])->values()->all(),

            'requests' => $requests->map(fn (MaterialRequest $r) => [
                'id' => $r->code,
                'eventId' => $r->event_id,
                'title' => $r->title,
                'venue' => $r->venue?->short_name,
                'functionalArea' => $r->functionalArea?->fa_code,
                'site' => $r->site_name,
                'domain' => $r->domain,
                'status' => $r->status,
                'items' => $r->items,
                'qty' => $r->qty,
                'value' => $r->value,
                'submitted' => $r->submitted,
                'updated' => $r->updated,
                'owner' => $r->owner?->initials,
                'priority' => $r->priority,
                'hasServiceOption' => $r->lines->contains(fn ($l) => $l->service_option_id !== null),
            ])->values()->all(),

            'catalog' => $catalogItems->map(fn (CatalogItem $c) => [
                'sku' => $c->sku,
                'domain' => $c->domain_code,
                'group' => $c->group,
                'sub' => $c->sub,
                'name' => $c->name,
                'unit' => $c->unit,
                'rate' => (float) $c->rate,
                'stock' => $c->stock,
                'baseline' => $c->baseline,
            ])->values()->all(),

            'suppliers' => $suppliers->map(fn (Supplier $s) => [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'kind' => $s->kind,
                'classificationId' => $s->classification_id,
                'classificationName' => $s->classification?->name,
                'classificationColor' => $s->classification?->color,
                'statusId' => $s->status_id,
                'statusName' => $s->status?->name,
                'statusColor' => $s->status?->color,
                'msa' => $s->msa_reference ?? '—',
                'contactName' => $s->contact_name,
                'contactPhone' => $s->contact_phone,
                'avgLeadDays' => $s->service_options_avg_lead_days !== null
                    ? (int) round($s->service_options_avg_lead_days)
                    : null,
            ])->values()->all(),

            'serviceOptions' => $serviceOptions->map(fn (ServiceOption $o) => [
                'id' => $o->code,
                // Real numeric PK — `id` above is the human-readable code used
                // everywhere else in the UI, but assigning a line to an option
                // needs the actual FK value (mp_request_lines.service_option_id).
                'dbId' => $o->id,
                'sku' => $o->sku,
                'name' => $o->name,
                'supplier' => $o->supplier_code,
                'cost' => (float) $o->cost,
                'lead' => $o->lead_days,
                'sla' => $o->sla,
                'capacity' => $o->capacity,
                'contract' => $o->contract_reference ?? '—',
                'spec' => $o->spec ?? '—',
                'classificationId' => $o->classification_id,
                'classificationName' => $o->classification?->name,
                'classificationColor' => $o->classification?->color,
                'statusId' => $o->status_id,
                'statusName' => $o->status?->name,
                'statusColor' => $o->status?->color,
                'isDefault' => $o->is_default,
            ])->values()->all(),

            'changeOrders' => $changeOrders->map(fn (ChangeOrder $co) => [
                'id' => $co->code,
                'eventId' => $co->request?->event_id,
                'req' => $co->request?->code,
                'context' => $co->context,
                'venue' => $co->request?->venue?->short_name,
                'domain' => $co->domain,
                'reason' => $co->reason,
                'raisedBy' => $co->raisedBy?->initials,
                'raisedOn' => $co->raised_on?->format('M d'),
                'age' => $co->age,
                'rows' => $co->rows,
                'state' => $co->state,
                'stage' => $co->stage,
                'delta' => $co->delta,
                'title' => $co->title,
            ])->values()->all(),

            'coStates' => self::CO_STATES,

            'permissions' => $permissions,
        ]);
    }

    /** Persists the user's chosen active event server-side so it survives reloads/devices instead of living only in localStorage. */
    public function setActiveEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|exists:events,id',
        ]);

        session(['mp_active_event_id' => $validated['event_id']]);

        return response()->json(['ok' => true]);
    }
}
