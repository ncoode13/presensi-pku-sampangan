<?php

namespace Database\Seeders;

use App\Models\DiklatCategory;
use Illuminate\Database\Seeder;

class DiklatCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'IHT (In House Training)', 'target_jpl_year' => 20],
            ['name' => 'Pengajian & Kerohanian', 'target_jpl_year' => 10],
            ['name' => 'Workshop / Seminar Luar', 'target_jpl_year' => 20],
            ['name' => 'Pelatihan Medis Kompetensi', 'target_jpl_year' => 30],
            ['name' => 'Lainnya', 'target_jpl_year' => 10],
        ];

        foreach ($categories as $cat) {
            DiklatCategory::updateOrCreate(['name' => $cat['name']], $cat);
        }
    }
}