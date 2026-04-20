<?php

namespace Database\Seeders;

use App\Models\Religion;
use Illuminate\Database\Seeder;

class ReligionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Religion::create(['name'=>'Catholic']);
        Religion::create(['name'=>'Islam']);
        Religion::create(['name'=>'Christian']);
        Religion::create(['name'=>'Buddha']);
        Religion::create(['name'=>'Hindu']);
        Religion::create(['name'=>'Confucius']);
        Religion::create(['name'=>'Others']);
    }
}
