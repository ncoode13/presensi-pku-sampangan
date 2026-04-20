<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OvertimeSubmission extends Model
{
    protected $table = 'overtime_requests';

    protected $fillable = [
        'user_id',
        'shift_id',
        'date',
        'duration_before',
        'rest_duration_before',
        'duration_after',
        'rest_duration_after',
        'reason',
        'file',
        'status',
        'current_step',
        'total_steps',
        'rejection_note',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function approvalSteps()
    {
        return $this->morphMany(RequestApproval::class, 'requestable')->orderBy('step', 'asc');
    }
}
