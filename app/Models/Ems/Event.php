<?php

namespace App\Models\Ems;

use App\Models\GlobalStatus;
use App\Models\MaterialPlanning\MaterialRequest;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;
    protected $table = 'events';
    protected $fillable = ['name', 'active_flag', 'event_logo', 'start_date', 'end_date'];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    // protected static function booted(){
    //     appLog(auth()->user()->functional_area_id);
    //     self::addGlobalScope(function(EloquentBuilder $builder){
    //         $builder->when(session()->get('workspace_id'), function ($query, $workspace) {
    //             return $query->where('events.workspace_id', $workspace);
    //         });
    //     });
    // }
    // protected $casts = [
    //     'start_time' => 'datetime: H:i',
    //     'end_time' => 'datetime: H:i',
    //   ];
    protected $appends = ["open"];

    public function getOpenAttribute()
    {
        return true;
    }

    public function active_status()
    {
        return $this->belongsTo(GlobalStatus::class, 'active_flag');
    }

    public function venues()
    {
        return $this->belongsToMany(Venue::class, 'venue_event', 'event_id', 'venue_id');
    }

    public function materialRequests()
    {
        return $this->hasMany(MaterialRequest::class, 'event_id');
    }
}
