<?php

namespace App\Models\MaterialPlanning;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ChangeOrder extends Model
{
    protected $table = 'mp_change_orders';
    protected $guarded = [];
    protected $casts = ['raised_on' => 'date'];

    /** Verb used at the front of the computed title, per reason. */
    private const REASON_VERBS = [
        'Scope increase' => 'Increase',
        'Scope reduction' => 'Reduce',
        'Overlay revision' => 'Revise',
        'Budget correction' => 'Trim',
        'Client request' => 'Add',
        'Service option change' => 'Switch',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $co) {
            if (! $co->code) {
                $co->code = static::generateCode();
            }
        });
    }

    public static function generateCode(): string
    {
        $seq = ((int) static::max('id')) + 1;
        return sprintf('CO-%03d', $seq);
    }

    public function request()
    {
        return $this->belongsTo(MaterialRequest::class, 'request_id');
    }

    public function raisedBy()
    {
        return $this->belongsTo(User::class, 'raised_by_user_id');
    }

    public function lines()
    {
        return $this->hasMany(ChangeOrderLine::class, 'change_order_id');
    }

    public function getRowsAttribute()
    {
        return $this->lines->count();
    }

    public function getAgeAttribute()
    {
        return $this->raised_on ? ((int) round($this->raised_on->diffInDays(now()))) . 'd' : null;
    }

    public function getDeltaAttribute()
    {
        return (int) round($this->lines->sum(fn ($l) => $l->d_value));
    }

    public function getDomainAttribute()
    {
        $top = $this->lines->sortByDesc(fn ($l) => abs($l->d_value))->first();
        return $top?->catalogItem?->domain_code;
    }

    public function getTitleAttribute()
    {
        $head = $this->lines->first();
        if (! $head) {
            return $this->context;
        }

        $item = $head->catalogItem;
        $short = trim(preg_split('/[—–(]/', $item?->name ?? $head->sku)[0]);
        $more = $this->lines->count() > 1 ? ' +' . ($this->lines->count() - 1) . ' more' : '';

        if ($this->reason === 'Service option change') {
            // A bundle's services can each have their own supplier, so this is a summary list.
            $supplierName = $head->serviceOptionAfter?->services->pluck('supplier.name')->unique()->filter()->implode(', ') ?? '';
            return "Switch {$short} to {$supplierName}{$more} — {$this->context}";
        }

        if ($this->reason === 'Client request') {
            $qty = abs(($head->qty_after ?? 0) - ($head->qty_before ?? 0));
            return "Add {$qty} {$item?->unit} of {$short} — {$this->context}";
        }

        $verb = self::REASON_VERBS[$this->reason] ?? 'Update';
        return "{$verb} {$short} {$head->qty_before} → {$head->qty_after} {$item?->unit}{$more} — {$this->context}";
    }
}
