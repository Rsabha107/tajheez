<?php

namespace App\Models\MaterialPlanning;

use Illuminate\Database\Eloquent\Model;

class ServiceOption extends Model
{
    protected $table = 'mp_service_options';
    protected $guarded = [];
    protected $casts = [
        'cost' => 'decimal:2',
        'is_default' => 'boolean',
    ];

    public function catalogItem()
    {
        return $this->belongsTo(CatalogItem::class, 'sku', 'sku');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_code', 'code');
    }

    public function getDomainAttribute()
    {
        return $this->catalogItem?->domain_code;
    }
}
