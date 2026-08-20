<?php

namespace App\Models\MaterialPlanning;

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

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }
}
