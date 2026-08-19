<?php

namespace App\Models\MaterialPlanning;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RequestActivity extends Model
{
    protected $table = 'mp_request_activity';
    protected $guarded = [];
    public $timestamps = false; // append-only log: created_at only, no updated_at
    protected $casts = ['created_at' => 'datetime'];

    public function request()
    {
        return $this->belongsTo(MaterialRequest::class, 'request_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
