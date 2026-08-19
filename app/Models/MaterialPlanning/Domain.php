<?php

namespace App\Models\MaterialPlanning;

use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    protected $table = 'mp_domains';
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    public function catalogItems()
    {
        return $this->hasMany(CatalogItem::class, 'domain_code', 'code');
    }
}
