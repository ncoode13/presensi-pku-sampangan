<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiklatEvent extends Model
{
    protected $guarded = ['id'];

    public function category()
    {
        return $this->belongsTo(DiklatCategory::class, 'diklat_category_id');
    }

    public function attendances()
    {
        return $this->hasMany(DiklatAttendance::class);
    }

    public function getAttendedUserIdsAttribute()
    {
        return $this->attendances()->pluck('user_id')->toArray();
    }
}
