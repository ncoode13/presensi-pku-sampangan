<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Personal;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Position;
use App\Models\JobLevel;
use App\Models\Shift;
use App\Models\Unit;
use App\Models\EmploymentStatus;
use Faker\Factory as Faker;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        // Ambil Data Master
        $positionIds = Position::pluck('id')->toArray();
        $jobLevelIds = JobLevel::pluck('id')->toArray();
        $shiftIds    = Shift::pluck('id')->toArray();
        $statusIds   = EmploymentStatus::pluck('id')->toArray();
        $units       = Unit::with('department')->get();

        if (empty($positionIds) || empty($jobLevelIds) || empty($shiftIds) || empty($statusIds) || $units->isEmpty()) {
            $this->command->error('Data Master kosong! Harap jalankan seeder master terlebih dahulu.');
            return;
        }

        // --- UBAH DISINI ---
        $totalEmployees = 206; // Sesuai jumlah karyawan di Excel Anda
        // -------------------

        $this->command->info("Sedang membuat {$totalEmployees} data karyawan dummy...");

        for ($i = 0; $i < $totalEmployees; $i++) {

            DB::transaction(function () use ($faker, $positionIds, $jobLevelIds, $shiftIds, $statusIds, $units) {

                // 1. Random Data
                $randomUnit   = $units->random();
                $departmentId = $randomUnit->department_id ?? Department::inRandomOrder()->value('id');

                $positionId = $faker->randomElement($positionIds);
                $jobLevelId = $faker->randomElement($jobLevelIds);
                $shiftId    = $faker->randomElement($shiftIds);
                $statusId   = $faker->randomElement($statusIds);
                $workScheme = $faker->randomElement(['office', 'shift']);

                // 2. Faker Biodata
                $gender     = $faker->randomElement(['male', 'female']);
                $firstName  = $faker->firstName($gender);
                $lastName   = $faker->lastName;
                $fullName   = $firstName . ' ' . $lastName;

                // Email unik
                $email = strtolower(Str::slug($firstName) . '.' . Str::slug($lastName) . rand(100, 999) . '@example.com');

                $nikDummy  = $faker->unique()->numerify('33##############');
                $npwpDummy = $faker->unique()->numerify('##.###.###.#-###.###');
                $nipDummy  = $faker->unique()->numerify('202#####'); // 202xxxxx

                // 3. Create User
                $user = User::create([
                    'name'              => $fullName,
                    'username'          => Str::slug($firstName . $lastName) . rand(100, 999),
                    'email'             => $email,
                    'password'          => Hash::make('user1234'),
                    'role'              => 'user',
                    'email_verified_at' => now(),
                ]);

                // 4. Create Personal (TANPA EMAIL)
                Personal::create([
                    'user_id'        => $user->id,
                    'first_name'     => $firstName,
                    'last_name'      => $lastName,
                    'place_of_birth' => $faker->city,
                    'birth_date'     => $faker->date('Y-m-d', '-22 years'),
                    'gender'         => $gender,
                    'marital_status' => $faker->randomElement(['Belum Menikah', 'Menikah', 'Cerai']),
                    'blood_type'     => $faker->randomElement(['A', 'B', 'AB', 'O']),
                    'religion'       => $faker->randomElement(['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha']),
                    'phone'          => $faker->phoneNumber,
                    'nik'            => $nikDummy,
                    'npwp'           => $npwpDummy,
                    'postal_code'    => $faker->postcode,
                    'address'        => $faker->address,
                ]);

                // 5. Create Employee
                $empIdString = 'EMP-' . date('Y') . '-' . strtoupper(Str::random(5)); // Random 5 char biar aman utk 206 data

                Employee::create([
                    'user_id'              => $user->id,
                    'nip'                  => $nipDummy,
                    'employee_id'          => $empIdString,
                    'department_id'        => $departmentId,
                    'unit_id'              => $randomUnit->id,
                    'position_id'          => $positionId,
                    'job_level_id'         => $jobLevelId,
                    'shift_id'             => $shiftId,
                    'employment_status_id' => $statusId,
                    'work_scheme'          => $workScheme,
                    'join_date'            => $faker->date('Y-m-d', '-3 years'),
                    'end_date'             => null,
                    'photo'                => null,
                    'avatar'               => null,
                    'is_ppa'               => $faker->boolean(10),
                ]);
            });
        }

        $this->command->info("Selesai! Berhasil membuat {$totalEmployees} data karyawan dummy.");
    }
}
