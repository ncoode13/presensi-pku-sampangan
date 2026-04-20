<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftSchedule extends Model
{
    protected $fillable = [
        'user_id',
        'month',
        'year',
        'schedule_data',
        'status',
        'created_by'
    ];


    protected $casts = [
        'schedule_data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
