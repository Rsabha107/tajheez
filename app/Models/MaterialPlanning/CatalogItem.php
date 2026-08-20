<?php

namespace App\Models\MaterialPlanning;

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

    public function serviceOptions()
    {
        return $this->hasMany(ServiceOption::class, 'catalog_item_id');
    }

    public function requestLines()
    {
        return $this->hasMany(RequestLine::class, 'catalog_item_id');
    }

    public function getDomainCodeAttribute()
    {
        return $this->domain?->code;
    }

    /**
     * Every catalog item must have at least one way to be delivered.
     * Called by the seeder and by CatalogItemController@store for items
     * created with no explicit service option.
     */
    public function ensureOwnPoolOption(): ServiceOption
    {
        $existing = $this->serviceOptions()->first();
        if ($existing) {
            return $existing;
        }

        return ServiceOption::create([
            'code' => "SO-{$this->sku}-OWN",
            'catalog_item_id' => $this->id,
            'supplier_id' => Supplier::where('code', 'SUP-OWN')->value('id'),
            'name' => 'Own pool — event stock',
            'cost' => $this->rate,
            'lead_days' => 2,
            'sla' => 'Spare-pool swap',
            'capacity' => (int) round(($this->baseline ?: 40) / 6),
            'contract_reference' => null,
            'spec' => 'As catalogued — issued from secured event stock',
            'classification_id' => 2,
            'is_default' => true,
        ]);
    }
}
