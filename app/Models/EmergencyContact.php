<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmergencyContact extends Model
{
    protected $fillable = ['personal_id', 'relationship_id', 'name', 'phone'];

    public function relationship()
    {
        return $this->belongsTo(Relationship::class);
    }
}
