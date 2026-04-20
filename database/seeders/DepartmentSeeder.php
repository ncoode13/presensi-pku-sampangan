<?php
namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Department::create(['name' => 'Sumber Daya Insani'], );
        Department::create(['name' => 'Komite, Tim, dan SPI'], );
        Department::create(['name' => 'Penunjang Medis'], );
        Department::create(['name' => 'Keperawatan'], );
        Department::create(['name' => 'Administrasi dan Rumah Tangga'], );
        Department::create(['name' => 'Humas dan Pemasaran'], );
        Department::create(['name' => 'Direksi'], );
        Department::create(['name' => 'Keuangan'], );
        Department::create(['name' => 'Pelayanan Medis'], );
        Department::create(['name' => 'IT'], );
        Department::create(['name' => 'Medis'], );
        Department::create(['name' => 'Diklat'], );
        Department::create(['name' => 'Al Islam dan Kemuhammadiyahan'], );
        Department::create(['name' => 'Akuntansi'], );
    }
}
