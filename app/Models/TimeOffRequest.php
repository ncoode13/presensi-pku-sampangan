<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimeOffRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'time_off_requests';

    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function timeOff()
    {
        return $this->belongsTo(TimeOff::class);
    }
}
