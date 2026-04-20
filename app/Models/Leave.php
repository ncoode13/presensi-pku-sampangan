<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Leave extends Model
{
    protected $table = 'time_offs';

    protected $fillable = ['name', 'description'];

    public function submissions()
    {
        return $this->hasMany(LeaveSubmission::class, 'time_off_id');
    }
}
