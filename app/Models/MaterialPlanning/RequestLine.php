<?php

namespace App\Models\MaterialPlanning;

use Illuminate\Database\Eloquent\Model;

class RequestLine extends Model
{
    protected $table = 'mp_request_lines';
    protected $guarded = [];
    protected $casts = ['rate_snapshot' => 'decimal:2'];

    public function request()
    {
        return $this->belongsTo(MaterialRequest::class, 'request_id');
    }

    public function catalogItem()
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    public function serviceOption()
    {
        return $this->belongsTo(ServiceOption::class, 'service_option_id');
    }

    public function getSkuAttribute()
    {
        return $this->catalogItem?->sku;
    }

    public function getUnitAttribute()
    {
        return $this->catalogItem?->unit;
    }

    public function getDomainAttribute()
    {
        return $this->catalogItem?->domain_code;
    }
}
