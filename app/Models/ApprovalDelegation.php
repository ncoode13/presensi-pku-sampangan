<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ApprovalDelegation extends Model
{
    protected $guarded = ['id'];

    protected $appends = ['is_expired', 'status_label'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function delegateTo()
    {
        return $this->belongsTo(User::class, 'delegate_to_id');
    }

    public function getIsExpiredAttribute()
    {
        return Carbon::now()->startOfDay()->gt(Carbon::parse($this->end_date));
    }

    public function getStatusLabelAttribute()
    {
        $today = Carbon::now()->startOfDay();
        $start = Carbon::parse($this->start_date);
        $end = Carbon::parse($this->end_date);

        if (!$this->is_active) return 'inactive';
        if ($today->gt($end)) return 'expired';
        if ($today->lt($start)) return 'pending'; 

        return 'active'; 
    }

    /**
     * Scope untuk memfilter delegasi yang aktif hari ini
     */
    public function scopeActive($query)
    {
        $today = now()->toDateString();
        return $query->where('is_active', true)
                     ->where('start_date', '<=', $today)
                     ->where('end_date', '>=', $today);
    }
}