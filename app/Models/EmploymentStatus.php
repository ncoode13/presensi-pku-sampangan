<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmploymentStatus extends Model
{
    protected $table = 'employment_statuses';

    protected $fillable = [
        'name',
    ];
    
    public function employee()
    {
        return $this->hasOne(Employee::class);
    }
}
