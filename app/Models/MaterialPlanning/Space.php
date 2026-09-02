<?php

namespace App\Models\MaterialPlanning;

use App\Models\Ems\Event;
use App\Models\GlobalStatus;
use Illuminate\Database\Eloquent\Model;

class Space extends Model
{
    protected $table = 'mp_spaces';
    protected $guarded = [];

    public function status()
    {
        return $this->belongsTo(GlobalStatus::class, 'status_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }
}
