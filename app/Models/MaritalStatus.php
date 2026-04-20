<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaritalStatus extends Model
{
    protected $table = 'marital_statuses';

    use SoftDeletes;

    protected $fillable = ['name'];

    public function employee()
    {
        return $this->hasOne(Employee::class);
    }
}
