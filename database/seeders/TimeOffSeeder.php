<?php
namespace Database\Seeders;

use App\Models\TimeOff;
use Illuminate\Database\Seeder;

class TimeOffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TimeOff::create(['name' => 'Cuti Tahunan', 'is_deduct_quota' => true]);
        TimeOff::create(['name' => 'Cuti Umroh', 'is_deduct_quota' => false]);
        TimeOff::create(['name' => 'Sakit', 'is_deduct_quota' => false]);
        TimeOff::create(['name' => 'Dinas Luar', 'is_deduct_quota' => false]);
        TimeOff::create(['name' => 'Pelatihan', 'is_deduct_quota' => false]);
        TimeOff::create(['name' => 'Cuti Menikah', 'is_deduct_quota' => false]);
        TimeOff::create(['name' => 'Cuti Menikahkan Anak', 'is_deduct_quota' => false]);
        TimeOff::create(['name' => 'Cuti Khitanan Anak', 'is_deduct_quota' => false]);
        TimeOff::create(['name' => 'Cuti Baptis Anak', 'is_deduct_quota' => false]);
        TimeOff::create(['name' => 'Cuti Istri Melahirkan atau Keguguran', 'is_deduct_quota' => false]);
        TimeOff::create(['name' => 'Cuti Keluarga Meninggal', 'is_deduct_quota' => false]);
        TimeOff::create(['name' => 'Cuti Anggota Keluarga Dalam Satu Rumah Meninggal', 'is_deduct_quota' => false]);
        TimeOff::create(['name' => 'Cuti Melahirkan', 'is_deduct_quota' => false]);
        TimeOff::create(['name' => 'Cuti Haid', 'is_deduct_quota' => false]);
        TimeOff::create(['name' => 'Cuti Keguguran', 'is_deduct_quota' => false]);
        TimeOff::create(['name' => 'Cuti Ibadah Haji', 'is_deduct_quota' => false]);
        TimeOff::create(['name' => 'Cuti Menjalankan Kewajiban Terhadap Negara', 'is_deduct_quota' => false]);
        TimeOff::create(['name' => 'Cuti Melaksanakan Tugas Serikat Pekerja/Serikat Buruh Atas Persetujuan Pengusaha', 'is_deduct_quota' => false]);
        TimeOff::create(['name' => 'Cuti Melaksanakan Tugas Pendidikan Dari Perusahaan', 'is_deduct_quota' => false]);
        TimeOff::create(['name' => 'Cuti Menunggu Keluarga Sakit', 'is_deduct_quota' => false]);
        TimeOff::create(['name' => 'Cuti Orang tua/Mertua/IStri/Suamu/Anak Meninggal', 'is_deduct_quota' => false]);
        TimeOff::create(['name' => 'Cuti Keluarga (Satu Rumah) Meninggal ', 'is_deduct_quota' => false]);
        TimeOff::create(['name' => 'Cuti Non Tahunan', 'is_deduct_quota' => false]);
        TimeOff::create(['name' => 'Libur Proposional Gaji', 'is_deduct_quota' => false]);  

    }
}
