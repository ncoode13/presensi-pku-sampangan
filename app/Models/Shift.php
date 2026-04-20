<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shift extends Model
{
    use SoftDeletes;

    public $fillable = [
        'name',
        'start_time',
        'end_time',
        'tolerance_come_too_late',
        'tolerance_go_home_early',
    ];

    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }
}
