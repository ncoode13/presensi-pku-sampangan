<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(MaritalStatusSeeder::class);
        $this->call(ReligionSeeder::class);
        $this->call(AttendanceLocationSeeder::class);
        $this->call(DepartmentSeeder::class);
        $this->call(EmploymentStatusSeeder::class);
        $this->call(JobLevelSeeder::class);
        $this->call(PositionSeeder::class);
        $this->call(RelationshipSeeder::class);
        $this->call(ShiftSeeder::class);
        $this->call(TimeOffSeeder::class);
        // $this->call(UserSeeder::class);
        // $this->call(UnitSeeder::class,);
        // $this->call(EmployeeSeeder::class,);
        // $this->call(FullUnitVerificationSeeder::class,);
        $this->call(SpecificEmployeeSeeder::class);
        // $this->call(AttendanceSeeder::class,);
        $this->call(DiklatCategorySeeder::class);
        $this->call(DiklatSettingSeeder::class);
        $this->call(AnnouncementCategorySeeder::class);
        $this->call(AnnouncementSeeder::class);
    }
}
