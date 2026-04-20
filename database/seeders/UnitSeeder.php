<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // KITA DEFINISIKAN STRUKTUR DEPARTEMEN & UNITNYA
        // Berdasarkan data PositionSeeder yang Anda kirim

        $structure = [
            // 1. Departemen Pelayanan Medis & Keperawatan
            'Pelayanan Medis & Keperawatan' => [
                'IGD',
                'ICU',
                'PICU-NICU',
                'IBS (Bedah Sentral)',
                'Kamar Bersalin (VK)',
                'PONEK',
                'Rawat Jalan (Poliklinik)',
                'Rawat Inap Dewasa',
                'Rawat Inap Anak',
                'Rawat Inap Bedah',
                'Rawat Inap Obgyn',
                'Rawat Inap Maryam-Kahfi', // Dari data posisi spesifik Anda
                'Rawat Inap Yusuf',        // Dari data posisi spesifik Anda
            ],

            // 2. Departemen Penunjang Medis
            'Penunjang Medis' => [
                'Instalasi Farmasi',
                'Laboratorium',
                'Radiologi',
                'Gizi',
                'Rekam Medis',
                'CSSD',
                'Fisioterapi',
                'Ambulance',
                'Elektromedis',
            ],

            // 3. Departemen Umum & Operasional
            'Umum & Operasional' => [
                'IPSRS', // Pemeliharaan Sarana
                'IT / SIMRS',
                'Sanitasi / Cleaning Service',
                'Laundry',
                'Logistik',
                'Keamanan (Satpam)',
                'Administrasi & Rumah Tangga',
                'Customer Service',
            ],

            // 4. Departemen Keuangan & Akuntansi
            'Keuangan & Akuntansi' => [
                'Keuangan',
                'Akuntansi',
                'Kasir',
                'Pendaftaran (Admisi)',
                'BPJS / Casemix', // Dari posisi "Pojok BPJS"
            ],

            // 5. Departemen Manajemen & SDM
            'Manajemen & SDM' => [
                'Direksi', // Untuk Direktur, Wadir
                'Sumber Daya Insani (HRD)',
                'Diklat',
                'Humas & Pemasaran', // Mencakup Digital Marketing
                'Komite Medis & Mutu', // Untuk SPI, Komite PPI, Etik
            ]
        ];

        // EKSEKUSI LOOPING
        foreach ($structure as $deptName => $units) {
            // 1. Buat atau Ambil Department
            $dept = Department::firstOrCreate(['name' => $deptName]);

            // 2. Buat Unit di bawah Department tersebut
            foreach ($units as $unitName) {
                Unit::firstOrCreate([
                    'name' => $unitName,
                    'department_id' => $dept->id
                ]);
            }
        }
    }
}
