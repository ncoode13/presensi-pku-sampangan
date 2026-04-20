<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceSubmission extends Model
{

    use HasFactory, SoftDeletes;
    protected $table = "attendance_requests";

    protected $guarded = ['id'];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    const STATUS_PENDING      = 'pending';
    const STATUS_APPROVED_HRD = 'approved_hrd';
    const STATUS_APPROVED     = 'approved';
    const STATUS_REJECTED     = 'rejected';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function approver1()
    {
        return $this->belongsTo(User::class, 'approved_1_by');
    }


    public function approvalSteps()
    {
        return $this->morphMany(RequestApproval::class, 'requestable')->orderBy('step', 'asc');
    }
}
