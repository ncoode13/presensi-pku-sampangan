<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Position;
use App\Models\JobLevel;
use App\Models\Shift;
use App\Models\EmploymentStatus;
use Faker\Factory as Faker;

class SpecificEmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        $maritalStatusIds = \App\Models\MaritalStatus::pluck('id')->toArray();
        $religionIds = \App\Models\Religion::pluck('id')->toArray();

        if (empty($maritalStatusIds) || empty($religionIds)) {
            $this->command->error('Data Master MaritalStatus atau Religion kosong! Harap jalankan seeder master terlebih dahulu.');
            return;
        }

        $shifts = Shift::pluck('id')->toArray();
        $employmentStatuses = EmploymentStatus::pluck('id')->toArray();

        if (empty($shifts) || empty($employmentStatuses)) {
            $this->command->error('Data Master Shift atau Employment Status kosong! Harap jalankan seeder master terlebih dahulu.');
            return;
        }

        $employeeData = [
            [
                'name' => 'Direktur Utama',
                'position_name' => 'Direktur Utama',
                'job_level_name' => 'Direksi',
                'department_name' => 'Manajemen',
                'role' => 'director',
            ],
            [
                'name' => 'Pelaksana IGD',
                'position_name' => 'Pelaksana IGD',
                'job_level_name' => 'Pelaksana',
                'department_name' => 'Keperawatan',
                'role' => 'user',
            ],
            [
                'name' => 'Kepala Seksie IGD',
                'position_name' => 'Kepala Seksie IGD',
                'job_level_name' => 'Kepala Seksie',
                'department_name' => 'Keperawatan',
                'role' => 'admin',
            ],
            [
                'name' => 'Kepala Bagian Keperawatan',
                'position_name' => 'Kepala Bagian Keperawatan',
                'job_level_name' => 'Kepala Bagian',
                'department_name' => 'Keperawatan',
                'role' => 'admin',
            ],
            [
                'name' => 'Kepala Bagian Sumber Daya Insani',
                'position_name' => 'Kepala Bagian Sumber Daya Insani',
                'job_level_name' => 'Kepala Bagian',
                'department_name' => 'Sumber Daya Insani',
                'role' => 'superadmin',
            ],
            [
                'name' => 'Pelaksana Sumber Daya Insani',
                'position_name' => 'Pelaksana HRD',
                'job_level_name' => 'Pelaksana',
                'department_name' => 'Sumber Daya Insani',
                'role' => 'superadmin',
            ],
        ];

        $this->command->info('Sedang membuat 6 karyawan spesifik (Struktural)...');

        foreach ($employeeData as $data) {
            DB::transaction(function () use ($faker, $data, $shifts, $employmentStatuses, $maritalStatusIds, $religionIds) {
                $position = Position::firstOrCreate(['name' => $data['position_name']]);
                $jobLevel = JobLevel::firstOrCreate(['name' => $data['job_level_name']]);
                $department = Department::firstOrCreate(['name' => $data['department_name']]);

                $firstName = $data['name'];
                $email = strtolower(Str::slug($data['name']) . rand(100, 999) . '@example.com');
                $username = Str::slug($data['name']) . rand(100, 999);

                $user = User::create([
                    'name' => $firstName,
                    'username' => $username,
                    'email' => $email,
                    'password' => Hash::make('user1234'),
                    'role' => $data['role'] ?? 'user',
                    'email_verified_at' => now(),
                ]);

                Employee::create([
                    'user_id' => $user->id,
                    'department_id' => $department->id,
                    'position_id' => $position->id,
                    'job_level_id' => $jobLevel->id,
                    'shift_id' => $faker->randomElement($shifts),
                    'employment_status_id' => $faker->randomElement($employmentStatuses),
                    'work_scheme' => $faker->randomElement(['office', 'shift']),
                    'employee_id' => 'EMP' . strtoupper(Str::random(6)),
                    'nip' => $faker->unique()->numerify('202#####'),
                    'join_date' => $faker->dateTimeBetween('-5 years', 'now'),
                ]);

                DB::table('personals')->insert([
                    'user_id' => $user->id,
                    'first_name' => explode(' ', $firstName)[0],
                    'last_name' => count(explode(' ', $firstName)) > 1 ? implode(' ', array_slice(explode(' ', $firstName), 1)) : null,
                    'nik' => $faker->unique()->numerify('33##############'),
                    'npwp' => $faker->unique()->numerify('##.###.###.#-###.###'),
                    'place_of_birth' => $faker->city,
                    'birth_date' => $faker->dateTimeBetween('-60 years', '-20 years'),
                    'gender' => $faker->randomElement(['male', 'female']),
                    'marital_status' => $faker->randomElement($maritalStatusIds),
                    'religion' => $faker->randomElement($religionIds),
                    'phone' => $faker->phoneNumber,
                    'address' => $faker->address,
                    'postal_code' => $faker->postcode,
                    'blood_type' => $faker->randomElement(['A', 'B', 'AB', 'O']),
                ]);
            });
        }

        $this->command->info('✓ 6 Karyawan spesifik berhasil dibuat!');
    }
}
