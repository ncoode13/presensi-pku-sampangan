<?php

namespace Database\Seeders;

use App\Models\EmploymentStatus;
use Illuminate\Database\Seeder;

class EmploymentStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        EmploymentStatus::create(['name' => 'Pegawai Tetap'],);
        EmploymentStatus::create(['name' => 'Calon Pegawai'],);
        EmploymentStatus::create(['name' => 'Kontrak'],);
        EmploymentStatus::create(['name' => 'Magang'],);
        EmploymentStatus::create(['name' => 'Full Time'],);
        EmploymentStatus::create(['name' => 'Part Time'],);
    }
}
