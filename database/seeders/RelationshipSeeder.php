<?php

namespace Database\Seeders;

use App\Models\Relationship;
use Illuminate\Database\Seeder;

class RelationshipSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Relationship::create(['name' => 'Ayah']);
        Relationship::create(['name' => 'Ibu']);
        Relationship::create(['name' => 'Saudara Kandung']);
        Relationship::create(['name' => 'Pasangan']);
        Relationship::create(['name' => 'Anak']);
        Relationship::create(['name' => 'Sepupu']);
        Relationship::create(['name' => 'Keponakan']);
        Relationship::create(['name' => 'Orang Tua Mertua']);
        Relationship::create(['name' => 'Saudara Ipar Laki-laki']);
        Relationship::create(['name' => 'Saudara Ipar Perempuan']);
        Relationship::create(['name' => 'Paman']);
        Relationship::create(['name' => 'Bibi']);
        Relationship::create(['name' => 'Kakek']);
        Relationship::create(['name' => 'Nenek']);
        Relationship::create(['name' => 'Teman']);
        Relationship::create(['name' => 'Rekan Kerja']);
        Relationship::create(['name' => 'Lainnya']);
    }
}
