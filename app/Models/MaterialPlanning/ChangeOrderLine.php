<?php

namespace App\Models\MaterialPlanning;

use Illuminate\Database\Eloquent\Model;

class ChangeOrderLine extends Model
{
    protected $table = 'mp_change_order_lines';
    protected $guarded = [];
    protected $casts = [
        'rate_before' => 'decimal:2',
        'rate_after' => 'decimal:2',
    ];

    public function changeOrder()
    {
        return $this->belongsTo(ChangeOrder::class, 'change_order_id');
    }

    public function requestLine()
    {
        return $this->belongsTo(RequestLine::class, 'request_line_id');
    }

    public function catalogItem()
    {
        return $this->belongsTo(CatalogItem::class, 'sku', 'sku');
    }

    public function serviceOptionBefore()
    {
        return $this->belongsTo(ServiceOption::class, 'service_option_before_id');
    }

    public function serviceOptionAfter()
    {
        return $this->belongsTo(ServiceOption::class, 'service_option_after_id');
    }

    public function getDValueAttribute()
    {
        $before = ($this->qty_before ?? 0) * ($this->rate_before ?? 0);
        $after = ($this->qty_after ?? 0) * ($this->rate_after ?? 0);
        return $after - $before;
    }
}
