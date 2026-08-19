<?php

namespace App\Models\MaterialPlanning;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RequestApprovalStep extends Model
{
    protected $table = 'mp_request_approval_steps';
    protected $guarded = [];

    public function request()
    {
        return $this->belongsTo(MaterialRequest::class, 'request_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
