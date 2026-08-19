<?php

namespace App\Models\MaterialPlanning;

use Illuminate\Database\Eloquent\Model;

class Space extends Model
{
    protected $table = 'mp_spaces';
    protected $guarded = [];

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_code', 'code');
    }
}
