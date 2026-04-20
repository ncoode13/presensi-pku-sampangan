<?php
namespace Database\Seeders;

use App\Models\JobLevel;
use Illuminate\Database\Seeder;

class JobLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        JobLevel::create(['name' => 'Direktur']);
        JobLevel::create(['name' => 'Kepala Bagian']);
        JobLevel::create(['name' => 'Kepala Instalasi']);
        JobLevel::create(['name' => 'Kepala Instalasi, Ketua Komite']);
        JobLevel::create(['name' => 'Kepala Seksie']);
        JobLevel::create(['name' => 'Ketua Komite']);
        JobLevel::create(['name' => 'Ketua Tim']);
        JobLevel::create(['name' => 'Koordinator']);
        JobLevel::create(['name' => 'Manajer Pelayanan Pasien']);
        JobLevel::create(['name' => 'Pelaksana']);
        JobLevel::create(['name' => 'Sekretaris Direktur']);
        JobLevel::create(['name' => 'SPI']);
        JobLevel::create(['name' => 'Supervisi Pelayanan']);
        JobLevel::create(['name' => 'Wakil Direktur']);
    }
}
