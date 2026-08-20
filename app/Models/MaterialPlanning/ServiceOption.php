<?php

namespace App\Models\MaterialPlanning;

use App\Models\Classification;
use App\Models\GlobalStatus;
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
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function classification()
    {
        return $this->belongsTo(Classification::class, 'classification_id');
    }

    public function status()
    {
        return $this->belongsTo(GlobalStatus::class, 'status_id');
    }

    public function getSkuAttribute()
    {
        return $this->catalogItem?->sku;
    }

    public function getSupplierCodeAttribute()
    {
        return $this->supplier?->code;
    }

    public function getDomainAttribute()
    {
        return $this->catalogItem?->domain_code;
    }
}
