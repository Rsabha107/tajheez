<?php

namespace App\Models\MaterialPlanning;

use Illuminate\Database\Eloquent\Model;

class ServiceOptionItem extends Model
{
    protected $table = 'mp_service_option_items';
    protected $guarded = [];
    protected $casts = [
        'cost' => 'decimal:2',
    ];

    public function bundles()
    {
        return $this->belongsToMany(ServiceOption::class, 'mp_bundle_service_options', 'service_option_item_id', 'bundle_id')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function itemGroup()
    {
        return $this->belongsTo(ItemGroup::class, 'item_group_id');
    }

    public function itemSubgroup()
    {
        return $this->belongsTo(ItemSubgroup::class, 'item_subgroup_id');
    }

    public function getSupplierCodeAttribute()
    {
        return $this->supplier?->code;
    }
}
