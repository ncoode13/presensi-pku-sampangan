<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\AnnouncementCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class AnnouncementSeeder extends Seeder
{
    public function run()
    {
        // Pastikan ada user untuk relasi created_by
        $admin = User::first() ?? User::factory()->create(['name' => 'Admin Test']);
        $categories = AnnouncementCategory::pluck('id')->toArray();
        
        if (empty($categories)) {
            $this->command->error("Kategori kosong! Jalankan AnnouncementCategorySeeder dulu.");
            return;
        }

        $totalData = 1000;
        $batchSize = 200;
        $data = [];

        $this->command->info("Sedang membuat {$totalData} data pengumuman...");

        for ($i = 1; $i <= $totalData; $i++) {
            $isPublic = fake()->boolean(40); // 40% publik
            
            $criteria = null;
            if (!$isPublic) {
                $criteria = [
                    'branches' => fake()->randomElements([1, 2, 3, 4, 5], fake()->numberBetween(0, 2)),
                    'departments' => fake()->randomElements([1, 2, 3, 4, 5], fake()->numberBetween(0, 1)),
                    'positions' => [], 
                    'job_levels' => fake()->randomElements([1, 2, 3], fake()->numberBetween(0, 1)),
                ];
            }

            $data[] = [
                'title' => fake()->sentence(6),
                'content' => fake()->paragraphs(3, true),
                'category_id' => fake()->randomElement($categories),
                'is_publish_to_all' => $isPublic,
                'target_criteria' => $criteria ? json_encode($criteria) : null,
                'created_by' => $admin->id,
                'attachment' => fake()->boolean(50) ? 'announcements/sample.pdf' : null,
                'created_at' => fake()->dateTimeBetween('-1 year', 'now'),
                'updated_at' => now(),
            ];

            if (count($data) >= $batchSize) {
                Announcement::insert($data);
                $data = [];
                $this->command->getOutput()->write('.');
            }
        }
        
        $this->command->info("\nSeeding selesai!");
    }
}