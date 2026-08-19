<?php

namespace App\Http\Controllers;

use App\Models\Ems\Event;
use App\Models\Ems\Venue;
use App\Models\MaterialPlanning\Area;
use App\Models\MaterialPlanning\CatalogItem;
use App\Models\MaterialPlanning\ChangeOrder;
use App\Models\MaterialPlanning\Domain;
use App\Models\MaterialPlanning\MaterialRequest;
use App\Models\MaterialPlanning\ServiceOption;
use App\Models\MaterialPlanning\Space;
use App\Models\MaterialPlanning\Supplier;
use App\Models\User;
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
        $permissions = [
            'isAdmin' => $user?->hasRole('admin') ?? false,
            'managedDomain' => $user?->managed_domain,
        ];

        $domains = Domain::orderBy('sort_order')->get();
        $domainsByCode = $domains->keyBy('code');

        $areas = Area::withCount('spaces')->orderBy('sort_order')->get();
        $spaces = Space::with('area')->orderBy('name')->get();

        $suppliers = Supplier::with('owner')->orderBy('name')->get();
        $catalogItems = CatalogItem::orderBy('sku')->get();
        $serviceOptions = ServiceOption::orderBy('id')->get();

        $requests = MaterialRequest::with(['venue', 'owner', 'lines.catalogItem'])->orderByDesc('id')->get();
        $changeOrders = ChangeOrder::with(['request.venue', 'raisedBy', 'lines.catalogItem', 'lines.serviceOptionAfter.supplier'])
            ->orderByDesc('id')->get();

        // "People" is derived from real users: anyone referenced as an owner/approver,
        // plus anyone with a Material Planning role, so the directory always covers
        // domain managers/admins even if they don't currently own a seeded record.
        $referencedUserIds = collect()
            ->merge($requests->pluck('owner_user_id'))
            ->merge($changeOrders->pluck('raised_by_user_id'))
            ->merge($suppliers->pluck('owner_user_id'))
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

        // Default active event: whichever real event Material Planning demo data is
        // actually attached to, falling back to the most recently created real event.
        $event = Event::whereHas('materialRequests')->latest('id')->first()
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
                'code' => $v->short_name,
                'name' => $v->title,
                'city' => $v->city,
                'sites' => $v->sites,
            ])->values()->all(),

            'domains' => $domains->map(fn (Domain $d) => [
                'id' => $d->code,
                'label' => $d->label,
                'color' => $d->color,
                'chip' => $d->chip,
                'desc' => $d->description,
            ])->values()->all(),

            'areas' => $areas->map(fn (Area $a) => [
                'id' => $a->code,
                'label' => $a->label,
                'description' => $a->description,
                'sortOrder' => $a->sort_order,
                'spacesCount' => $a->spaces_count,
            ])->values()->all(),

            'spaces' => $spaces->map(fn (Space $s) => [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'description' => $s->description,
                'area' => $s->area_code,
                'areaLabel' => $s->area?->label,
            ])->values()->all(),

            'statuses' => self::STATUSES,

            'people' => $people,

            'requests' => $requests->map(fn (MaterialRequest $r) => [
                'id' => $r->code,
                'title' => $r->title,
                'venue' => $r->venue?->short_name,
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
                'id' => $s->code,
                'name' => $s->name,
                'kind' => $s->kind,
                'status' => $s->status,
                'msa' => $s->msa_reference ?? '—',
                'owner' => $s->owner?->initials,
            ])->values()->all(),

            'serviceOptions' => $serviceOptions->map(fn (ServiceOption $o) => [
                'id' => $o->code,
                'sku' => $o->sku,
                'name' => $o->name,
                'supplier' => $o->supplier_code,
                'cost' => (float) $o->cost,
                'lead' => $o->lead_days,
                'sla' => $o->sla,
                'capacity' => $o->capacity,
                'contract' => $o->contract_reference ?? '—',
                'spec' => $o->spec ?? '—',
                'status' => $o->status,
                'isDefault' => $o->is_default,
            ])->values()->all(),

            'changeOrders' => $changeOrders->map(fn (ChangeOrder $co) => [
                'id' => $co->code,
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
}
