<?php

namespace Database\Seeders;

use App\Models\AttendanceLocation;
use Illuminate\Database\Seeder;

class AttendanceLocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AttendanceLocation::create(
            [
                'name'      => 'Titik A',
                'address'   => 'Gang Cempaka Sebelas RT.2/12, Jl. Sampangan No.119, Semanggi, Kec. Ps. Kliwon, Kota Surakarta, Jawa Tengah 57191, Indonesia',
                'latitude'  => '-7.5813755',
                'longitude' => '110.8364652',
                'radius'    => '18',
            ],
        );
        AttendanceLocation::create(
            [
                'name'      => 'Titik B',
                'address'   => 'Gg. Cempaka 10 No.22, Semanggi, Kec. Ps. Kliwon, Kota Surakarta, Jawa Tengah 57191, Indonesia',
                'latitude'  => '-7.5817177',
                'longitude' => '110.8363414',
                'radius'    => '18',
            ],
        );
        AttendanceLocation::create(
            [
                'name'      => 'Titik C',
                'address'   => 'CR9P+7PV, Jl. Wiropaten, Semanggi, Kec. Ps. Kliwon, Kota Surakarta, Jawa Tengah 57191, Indonesia',
                'latitude'  => '-7.5817865',
                'longitude' => '110.8366918',
                'radius'    => '18',
            ],
        );
    }
}
