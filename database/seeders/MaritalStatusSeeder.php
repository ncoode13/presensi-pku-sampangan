<?php
namespace Database\Seeders;

use App\Models\MaritalStatus;
use Illuminate\Database\Seeder;

class MaritalStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        MaritalStatus::create(['name' => 'Single']);
        MaritalStatus::create(['name' => 'Maried']);
        MaritalStatus::create(['name' => 'Widow']);
        MaritalStatus::create(['name' => 'Widower']);
    }
}
