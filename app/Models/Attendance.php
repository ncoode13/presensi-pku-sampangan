<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    const STATUS_PRESENT   = 1;
    const STATUS_ABSENT    = 2;
    const STATUS_LATE      = 3;
    const STATUS_LEAVE     = 4;
    const STATUS_PENDING   = 5;
    const STATUS_EARLY_OUT = 6;
    const STATUS_REJECTED  = 7;

    public static function statusLabels()
    {
        return [
            static::STATUS_PRESENT   => 'Hadir',
            static::STATUS_ABSENT    => 'Alpa',
            static::STATUS_LATE      => 'Terlambat',
            static::STATUS_LEAVE     => 'Cuti',
            static::STATUS_PENDING   => 'Menunggu Konfirmasi',
            static::STATUS_EARLY_OUT => 'Pulang Awal',
            static::STATUS_REJECTED  => 'Ditolak',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
    public function location()
    {
        return $this->belongsTo(AttendanceLocation::class);
    }
    public function logs()
    {
        return $this->hasMany(AttendanceLog::class);
    }



    public function approvalSteps()
    {
        return $this->morphMany(RequestApproval::class, 'requestable')->orderBy('step', 'asc');
    }
}
