<?php

namespace App\Models\MaterialPlanning;

use App\Models\Ems\Event;
use App\Models\GlobalStatus;
use Illuminate\Database\Eloquent\Model;

class ItemGroup extends Model
{
    protected $table = 'mp_item_groups';
    protected $guarded = [];

    public function status()
    {
        return $this->belongsTo(GlobalStatus::class, 'status_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function subgroups()
    {
        return $this->hasMany(ItemSubgroup::class, 'group_id');
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_id');
    }
}
