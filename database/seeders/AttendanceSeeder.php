<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{User, Attendance, Shift, AttendanceSubmission};
use Carbon\Carbon;
use Illuminate\Support\Facades\{Schema, DB};

class AttendanceSeeder extends Seeder
{
    public function run()
    {
        // Load user beserta employee-nya untuk mendapatkan shift_id asli mereka
        $users = User::with('employee')->get();
        
        if ($users->isEmpty()) {
            $this->command->error('Tidak ada user ditemukan.');
            return;
        }

        $year = Carbon::now()->year; 
        $month = Carbon::now()->month;
        $limitDay = Carbon::now()->day;

        $this->command->info("Cleaning old data...");
        Schema::disableForeignKeyConstraints();
        DB::table('attendances')->truncate();
        DB::table('attendance_logs')->truncate();
        DB::table('attendance_requests')->truncate();
        DB::table('request_approvals')->truncate();
        Schema::enableForeignKeyConstraints();

        $this->command->info("Generating data absensi berdasarkan shift masing-masing employee...");

        foreach ($users as $user) {
            // Ambil shift_id dari data employee, jika tidak ada pakai ID 2 (Reguler) sebagai default
            $userShiftId = $user->employee->shift_id ?? 2;
            $shift = Shift::find($userShiftId);

            if (!$shift) continue;

            for ($day = 1; $day <= $limitDay; $day++) {
                $date = Carbon::createFromDate($year, $month, $day);
                if ($date->isSunday()) continue;

                $dateStr = $date->format('Y-m-d');
                $dice = rand(1, 100); 
                
                $status = Attendance::STATUS_PRESENT;
                $type = 'normal';
                
                // Set waktu masuk lebih awal (Early In) berdasarkan jam shift asli si user
                $randomMinutes = [15, 30, 45, 10, 20];
                $earlyMinutes = $randomMinutes[array_rand($randomMinutes)];
                
                $inTime = Carbon::parse($shift->start_time)->subMinutes($earlyMinutes)->format('H:i:s');
                $outTime = Carbon::parse($shift->end_time)->addMinutes(5)->format('H:i:s');

                if ($dice <= 10) { 
                    $status = Attendance::STATUS_LATE;
                    $inTime = Carbon::parse($shift->start_time)->addMinutes(20)->format('H:i:s');
                } elseif ($dice <= 15) {
                    $status = Attendance::STATUS_EARLY_OUT;
                    $outTime = Carbon::parse($shift->end_time)->subMinutes(30)->format('H:i:s');
                } elseif ($dice <= 20) {
                    $type = 'forgot';
                } elseif ($dice <= 25) {
                    $type = 'violation';
                } elseif ($dice <= 30) {
                    continue; 
                }

                $attendanceId = DB::table('attendances')->insertGetId([
                    'user_id' => $user->id,
                    'shift_id' => $shift->id,
                    'attendance_location_id' => 1,
                    'date' => $dateStr,
                    'status' => $status,
                    'is_location_valid' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($type === 'normal' || $type === 'violation') {
                    DB::table('attendance_logs')->insert([
                        'attendance_id' => $attendanceId,
                        'attendance_type' => 'check_in',
                        'time' => $inTime,
                        'lat' => -7.56, 'lng' => 110.82,
                        'device_info' => 'Seeder Device',
                    ]);
                    
                    if ($type === 'normal') {
                        DB::table('attendance_logs')->insert([
                            'attendance_id' => $attendanceId,
                            'attendance_type' => 'check_out',
                            'time' => $outTime,
                            'lat' => -7.56, 'lng' => 110.82,
                            'device_info' => 'Seeder Device',
                        ]);
                    }
                } elseif ($type === 'forgot') {
                    $subId = DB::table('attendance_requests')->insertGetId([
                        'user_id' => $user->id,
                        'shift_id' => $shift->id,
                        'attendance_type' => 'check_in',
                        'date' => $dateStr,
                        'time' => $shift->start_time,
                        'reason' => 'Lupa absen seeder',
                        'status' => 'approved',
                        'current_step' => 1,
                        'total_steps' => 1,
                        'created_at' => now(),
                    ]);

                    DB::table('request_approvals')->insert([
                        'requestable_id' => $subId,
                        'requestable_type' => AttendanceSubmission::class,
                        'step' => 1,
                        'status' => 'approved',
                        'approver_id' => 1,
                        'action_at' => now()
                    ]);
                }
            }
        }
        $this->command->info('Success: Data dummy disinkronkan dengan jam Shift asli karyawan.');
    }
}