<?php

namespace App\Models\Ems;

use App\Models\MaterialPlanning\MaterialRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FunctionalArea extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'functional_areas';

    public function users()
    {
        return $this->belongsToMany(User::class, 'functional_area_user');
    }

    public function materialRequests()
    {
        return $this->hasMany(MaterialRequest::class, 'functional_area_id');
    }
}
