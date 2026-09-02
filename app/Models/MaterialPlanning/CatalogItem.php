<?php

namespace App\Models\MaterialPlanning;

use App\Models\Ems\Event;
use Illuminate\Database\Eloquent\Model;

class CatalogItem extends Model
{
    protected $table = 'mp_catalog_items';
    protected $guarded = [];
    protected $casts = ['rate' => 'decimal:2'];

    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function requestLines()
    {
        return $this->hasMany(RequestLine::class, 'catalog_item_id');
    }

    public function getDomainCodeAttribute()
    {
        return $this->domain?->code;
    }
}
