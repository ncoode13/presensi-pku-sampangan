<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Employee;
use App\Models\Position;
use App\Models\JobLevel;
use App\Models\Unit;
use App\Models\Shift;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FullUnitVerificationSeeder extends Seeder
{
    public function run(): void
    {
        $shift = Shift::where('name', 'Reguler')->first() ?? Shift::first();

        $levelHead = JobLevel::where('name', 'LIKE', '%Kepala%')->first()
            ?? JobLevel::create(['name' => 'Kepala Unit Dummy']);

        $levelStaff = JobLevel::where('name', 'LIKE', '%Pelaksana%')->first()
            ?? JobLevel::create(['name' => 'Pelaksana Dummy']);

        $units = Unit::all();

        if ($units->isEmpty()) {
            $this->command->error('Tabel Units kosong! Jalankan UnitSeeder terlebih dahulu.');
            return;
        }

        $this->command->info("Ditemukan {$units->count()} Unit. Memulai generate user...");
        $bar = $this->command->getOutput()->createProgressBar($units->count());
        $bar->start();

        $createdAccounts = [];

        foreach ($units as $unit) {
            $slug = Str::slug($unit->name, '_');

            $deptId = $unit->department_id ?? Department::first()->id;


            $posHead = Position::where('name', 'LIKE', '%Kepala%')
                ->where('name', 'LIKE', '%' . $unit->name . '%')
                ->first();

            if (!$posHead) {
                $posHead = Position::firstOrCreate(['name' => 'Kepala ' . $unit->name]);
            }

            $usernameHead = 'ka.' . $slug;

            if (!User::where('username', $usernameHead)->exists()) {
                $userHead = User::create([
                    'name'     => 'Bpk/Ibu Ka. ' . $unit->name,
                    'username' => $usernameHead,
                    'email'    => $usernameHead . '@rs.com',
                    'password' => Hash::make('password'),
                    'role'     => 'user',
                ]);

                Employee::create([
                    'user_id'       => $userHead->id,
                    'employee_id'   => 'H-' . strtoupper(substr($slug, 0, 4)) . rand(10, 99),
                    'nip'           => rand(10000000, 19999999),
                    'department_id' => $deptId,
                    'unit_id'       => $unit->id,
                    'position_id'   => $posHead->id,
                    'job_level_id'  => $levelHead->id,
                    'shift_id'      => $shift->id,
                    'work_scheme'   => 'shift',
                    'join_date'     => now(),
                ]);
            }


            $posStaff = Position::where('name', 'LIKE', '%Pelaksana%')
                ->where('name', 'LIKE', '%' . $unit->name . '%')
                ->first();

            if (!$posStaff) {
                $posStaff = Position::firstOrCreate(['name' => 'Pelaksana ' . $unit->name]);
            }

            $usernameStaff = 'staf.' . $slug;

            if (!User::where('username', $usernameStaff)->exists()) {
                $userStaff = User::create([
                    'name'     => 'Staf ' . $unit->name,
                    'username' => $usernameStaff,
                    'email'    => $usernameStaff . '@rs.com',
                    'password' => Hash::make('password'),
                    'role'     => 'user',
                ]);

                Employee::create([
                    'user_id'       => $userStaff->id,
                    'employee_id'   => 'S-' . strtoupper(substr($slug, 0, 4)) . rand(10, 99),
                    'nip'           => rand(20000000, 29999999),
                    'department_id' => $deptId,
                    'unit_id'       => $unit->id,
                    'position_id'   => $posStaff->id,
                    'job_level_id'  => $levelStaff->id,
                    'shift_id'      => $shift->id,
                    'work_scheme'   => 'shift',
                    'join_date'     => now(),
                ]);
            }

            $createdAccounts[] = [
                $unit->name,
                $usernameHead,
                $usernameStaff,
                'password'
            ];

            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine(2);

        $this->command->info('BERHASIL GENERATE USER UNTUK SEMUA UNIT!');
        $this->command->info('Silakan gunakan akun di bawah ini untuk Login & Test Matrix:');

        $this->command->table(
            ['Unit', 'Username Kepala (Login ini)', 'Username Staf (Harus Muncul)', 'Password'],
            $createdAccounts
        );
    }
}
