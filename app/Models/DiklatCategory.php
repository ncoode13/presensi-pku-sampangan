<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiklatCategory extends Model
{
    protected $guarded = ['id'];

    public function events()
    {
        return $this->hasMany(DiklatEvent::class);
    }
}
