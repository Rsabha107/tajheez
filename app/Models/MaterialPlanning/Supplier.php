<?php

namespace App\Models\MaterialPlanning;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $table = 'mp_suppliers';
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function serviceOptions()
    {
        return $this->hasMany(ServiceOption::class, 'supplier_code', 'code');
    }
}
