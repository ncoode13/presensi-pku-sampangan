<?php

namespace Database\Seeders;

use App\Models\AnnouncementCategory;
use Illuminate\Database\Seeder;

class AnnouncementCategorySeeder extends Seeder
{
    public function run()
    {
        $categories = ['Info RS', 'Kebijakan HR', 'Event', 'Penting', 'Undangan'];
        foreach ($categories as $cat) {
            AnnouncementCategory::updateOrCreate(['name' => $cat]);
        }
    }
}