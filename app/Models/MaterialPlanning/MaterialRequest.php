<?php

namespace App\Models\MaterialPlanning;

use App\Models\Ems\Event;
use App\Models\Ems\FunctionalArea;
use App\Models\Ems\Venue;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class MaterialRequest extends Model
{
    protected $table = 'mp_requests';
    protected $guarded = [];
    protected $casts = [
        'move_in' => 'date',
        'move_out' => 'date',
        'submitted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $request) {
            if (! $request->code) {
                $request->code = static::generateCode();
            }
        });
    }

    public static function generateCode(): string
    {
        $year = now()->format('y');
        $seq = ((int) static::max('id')) + 1;
        return sprintf('MR-%s-%05d', $year, $seq);
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class, 'venue_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function functionalArea()
    {
        return $this->belongsTo(FunctionalArea::class, 'functional_area_id');
    }

    public function space()
    {
        return $this->belongsTo(Space::class, 'space_id');
    }

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function lines()
    {
        return $this->hasMany(RequestLine::class, 'request_id');
    }

    public function approvalSteps()
    {
        return $this->hasMany(RequestApprovalStep::class, 'request_id')->orderBy('step_no');
    }

    public function activity()
    {
        return $this->hasMany(RequestActivity::class, 'request_id')->latest('created_at');
    }

    public function changeOrders()
    {
        return $this->hasMany(ChangeOrder::class, 'request_id');
    }

    public function getItemsAttribute()
    {
        return $this->lines->count();
    }

    public function getQtyAttribute()
    {
        return (int) $this->lines->sum('qty');
    }

    public function getValueAttribute()
    {
        return (float) $this->lines->sum(fn ($l) => $l->qty * $l->rate_snapshot);
    }

    public function getDomainAttribute()
    {
        $top = $this->lines->sortByDesc(fn ($l) => $l->qty * $l->rate_snapshot)->first();
        return $top?->catalogItem?->domain_code;
    }

    public function getSubmittedAttribute()
    {
        return $this->submitted_at ? $this->submitted_at->format('M d') : '—';
    }

    public function getUpdatedAttribute()
    {
        return $this->updated_at?->diffForHumans();
    }
}
