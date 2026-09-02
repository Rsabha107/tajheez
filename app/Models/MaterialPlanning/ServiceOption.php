<?php

namespace App\Models\MaterialPlanning;

use App\Models\Classification;
use App\Models\Ems\Event;
use App\Models\GlobalStatus;
use Illuminate\Database\Eloquent\Model;

class ServiceOption extends Model
{
    protected $table = 'mp_service_options';
    protected $guarded = [];
    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function services()
    {
        return $this->belongsToMany(ServiceOptionItem::class, 'mp_bundle_service_options', 'bundle_id', 'service_option_item_id')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('mp_bundle_service_options.sort_order');
    }

    public function classification()
    {
        return $this->belongsTo(Classification::class, 'classification_id');
    }

    public function status()
    {
        return $this->belongsTo(GlobalStatus::class, 'status_id');
    }

    public function itemGroup()
    {
        return $this->belongsTo(ItemGroup::class, 'item_group_id');
    }

    public function itemSubgroup()
    {
        return $this->belongsTo(ItemSubgroup::class, 'item_subgroup_id');
    }
}
