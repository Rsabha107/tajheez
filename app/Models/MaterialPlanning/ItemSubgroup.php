<?php

namespace App\Models\MaterialPlanning;

use App\Models\Ems\Event;
use App\Models\GlobalStatus;
use Illuminate\Database\Eloquent\Model;

class ItemSubgroup extends Model
{
    protected $table = 'mp_item_subgroups';
    protected $guarded = [];

    public function status()
    {
        return $this->belongsTo(GlobalStatus::class, 'status_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function group()
    {
        return $this->belongsTo(ItemGroup::class, 'group_id');
    }
}
