<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceLog extends Model
{
    use SoftDeletes;

    protected $fillable = ['attendance_id', 'attendance_type', 'time', 'lat', 'lng', 'photo', 'device_info', 'note'];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }
}
