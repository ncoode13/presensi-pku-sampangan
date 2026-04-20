<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShiftSubmission extends Model
{
    use SoftDeletes;

    protected $table = 'change_shift_requests';

    protected $fillable = [
        'user_id',
        'shift_old_id',
        'shift_new_id',
        'date',
        'reason',
        'file',
        'status',
        'current_step',
        'total_steps',
        'rejection_note'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function currentShift()
    {
        return $this->belongsTo(Shift::class, 'shift_old_id');
    }

    public function targetShift()
    {
        return $this->belongsTo(Shift::class, 'shift_new_id');
    }

    public function approvalSteps()
    {
        return $this->morphMany(RequestApproval::class, 'requestable')->orderBy('step', 'asc');
    }
}
