<?php

namespace App\Models\MaterialPlanning;

use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    protected $table = 'mp_areas';
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    public function spaces()
    {
        return $this->hasMany(Space::class, 'area_code', 'code');
    }
}
