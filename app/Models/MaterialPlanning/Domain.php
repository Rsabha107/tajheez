<?php

namespace App\Models\MaterialPlanning;

use App\Models\GlobalStatus;
use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    protected $table = 'mp_domains';
    protected $guarded = [];

    public function status()
    {
        return $this->belongsTo(GlobalStatus::class, 'status_id');
    }

    public function catalogItems()
    {
        return $this->hasMany(CatalogItem::class, 'domain_id');
    }

    public function itemGroups()
    {
        return $this->hasMany(ItemGroup::class, 'domain_id');
    }
}
