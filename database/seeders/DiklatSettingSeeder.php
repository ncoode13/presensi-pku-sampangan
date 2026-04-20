<?php

namespace Database\Seeders;

use App\Models\DiklatSetting;
use Illuminate\Database\Seeder;

class DiklatSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'target_jpl_tahunan',
                'value' => '20',
                'display_name' => 'Target JPL Tahunan Global',
                'type' => 'number'
            ],
        ];

        foreach ($settings as $s) {
            DiklatSetting::updateOrCreate(['key' => $s['key']], $s);
        }
    }
}