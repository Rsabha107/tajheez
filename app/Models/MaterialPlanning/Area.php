<?php

namespace App\Models\MaterialPlanning;

use App\Models\GlobalStatus;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    protected $table = 'mp_areas';
    protected $guarded = [];

    public function status()
    {
        return $this->belongsTo(GlobalStatus::class, 'status_id');
    }

    public function spaces()
    {
        return $this->hasMany(Space::class, 'area_id');
    }
}
