<?php

namespace App\Models\MaterialPlanning;

use App\Models\Classification;
use App\Models\GlobalStatus;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $table = 'mp_suppliers';
    protected $guarded = [];

    public function classification()
    {
        return $this->belongsTo(Classification::class, 'classification_id');
    }

    public function status()
    {
        return $this->belongsTo(GlobalStatus::class, 'status_id');
    }

    public function serviceOptionItems()
    {
        return $this->hasMany(ServiceOptionItem::class, 'supplier_id');
    }
}
