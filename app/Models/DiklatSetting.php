<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiklatSetting extends Model
{
    protected $fillable = ['key', 'value', 'display_name', 'type'];
}