<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveSubmission extends Model
{
    protected $table = 'time_off_requests';

    protected $fillable = [
        'time_off_id',
        'user_id',
        'start_date',
        'end_date',
        'reason',
        'file',
        'status',
        'current_step',
        'total_steps',
        'rejection_note'
    ];

    public function leave()
    {
        return $this->belongsTo(Leave::class, 'time_off_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approvalSteps()
    {
        return $this->morphMany(RequestApproval::class, 'requestable')->orderBy('step', 'asc');
    }
}
