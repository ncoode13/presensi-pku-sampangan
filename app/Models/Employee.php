<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'user_id',
        'department_id',
        'position_id',
        'job_level_id',
        'shift_id',
        'employment_status_id',
        'work_scheme',
        'employee_id',
        'marital_status_id',
        'nip',
        'join_date',
        'end_date',
        'photo',
        'avatar',
        'is_ppa',
        'emergency_contact_name',
        'emergency_contact_relationship_id',
        'emergency_contact_phone',
        'group',
        'rank',
        'attachment',

    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function job_level()
    {
        return $this->belongsTo(JobLevel::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function employment_status()
    {
        return $this->belongsTo(EmploymentStatus::class);
    }

    public function personal()
    {
        return $this->hasOne(Personal::class, 'user_id', 'user_id');
    }

    public function attendances()
    {
        return $this->hasMany(
            Attendance::class,
            'user_id',
            'user_id'
        );
    }

    public function jobLevel()
    {
        return $this->belongsTo(JobLevel::class);
    }
}
