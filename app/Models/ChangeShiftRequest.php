<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChangeShiftRequest extends Model
{
    protected $table = 'change_shift_requests';

    protected $fillable = [
        'user_id',
        'shift_old_id',
        'shift_new_id',
        'reason',
        'date',
        'status',
        'approved_1_by',
        'approved_1_at',
        'approved_2_by',
        'approved_2_at',

    ];

    public function oldShift()
    {
        return $this->belongsTo(Shift::class, 'shift_old_id');
    }

    public function newShift()
    {
        return $this->belongsTo(Shift::class, 'shift_new_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
