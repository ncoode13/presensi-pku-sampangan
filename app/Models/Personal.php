<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Personal extends Model
{
    use HasFactory, SoftDeletes;


    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'place_of_birth',
        'birth_date',
        'gender',
        'marital_status',
        'blood_type',
        'religion',
        'phone',
        'nik',
        'npwp',
        'postal_code',
        'address',
    ];


    protected $casts = [
        'birth_date' => 'date',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function maritalStatus()
    {

        return $this->belongsTo(MaritalStatus::class, 'marital_status');
    }

    public function emergencyContact()
    {
        return $this->hasOne(EmergencyContact::class);
    }
}
