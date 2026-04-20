<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function sosialAccount()
    {
        return $this->hasOne(SocialAccount::class);
    }

    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    public function personal()
    {
        return $this->hasOne(Personal::class);
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }


    public function timeOffRequests()
    {
        return $this->hasMany(TimeOffRequest::class);
    }

    public function isDirector()
    {
        if (!$this->employee) return false;

        if (!$this->employee->relationLoaded('position')) {
            $this->employee->load('position');
        }

        $positionName = strtolower($this->employee->position->name ?? '');

        return in_array($positionName, ['direktur', 'Direktur']);
    }

    public function isHRD()
    {
        return $this->role === 'admin' && !$this->isDirector();
    }
    public function getTodayShift()
    {
        $today = Carbon::now()->format('Y-m-d');

        $specialShift = ShiftSubmission::where('user_id', $this->id)
            ->where('date', $today)
            ->where('status', 'approved')
            ->with('targetShift')
            ->first();

        if ($specialShift && $specialShift->targetShift) {
            return $specialShift->targetShift;
        }

        return $this->employee->shift ?? null;
    }

    public function hasRole($role)
    {

        return $this->role === $role;
    }

    public function diklatAttendances()
    {
        return $this->hasMany(DiklatAttendance::class);
    }

    public function approvalLines()
    {
        return $this->hasMany(ApprovalLine::class, 'user_id')->orderBy('step', 'asc');
    }

    public function approvalsToProcess()
    {
        return $this->hasMany(RequestApproval::class, 'approver_id');

    }
    public function approvalDelegation()
    {
        return $this->hasOne(ApprovalDelegation::class, 'user_id');
    }
}
