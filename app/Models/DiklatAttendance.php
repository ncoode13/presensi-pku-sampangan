<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiklatAttendance extends Model
{
    protected $guarded = ['id'];

    public function event()
    {
        return $this->belongsTo(DiklatEvent::class, 'diklat_event_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}