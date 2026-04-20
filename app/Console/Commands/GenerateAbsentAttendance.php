<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Shift;
use Carbon\Carbon;
use App\Models\ShiftSubmission;

class GenerateAbsentAttendance extends Command
{
    protected $signature = 'attendance:generate-alpha-dynamic';
    protected $description = 'Cek Alpha berdasarkan jam selesainya shift masing-masing user';

    public function handle()
    {
        $this->info('Memulai pengecekan Alpha dinamis...');

        $users = User::whereHas('employee', fn($q) => $q->whereNull('end_date'))
            ->where('role', '!=', 'superadmin')
            ->get();

        $counter = 0;
        $now = Carbon::now();

        foreach ($users as $user) {
            $datesToCheck = [Carbon::today(), Carbon::yesterday()];

            foreach ($datesToCheck as $dateData) {
                $dateStr = $dateData->format('Y-m-d');

                if ($dateData->isWeekend()) continue;

                $exists = Attendance::where('user_id', $user->id)
                    ->whereDate('date', $dateStr)
                    ->exists();

                if ($exists) continue;

                $shift = $this->getShiftForDate($user, $dateStr);

                if (!$shift) continue;

                $shiftStart = Carbon::parse($dateStr . ' ' . $shift->start_time);
                $shiftEnd   = Carbon::parse($dateStr . ' ' . $shift->end_time);

                if ($shiftEnd->lessThan($shiftStart)) {
                    $shiftEnd->addDay();
                }

                $deadline = $shiftEnd->copy()->addHours(2);

                if ($now->greaterThan($deadline)) {
                    Attendance::create([
                        'user_id'                => $user->id,
                        'shift_id'               => $shift->id,
                        'date'                   => $dateStr,
                        'status'                 => 2,

                        'type'                   => 'Alpha',

                        'attendance_location_id' => null,
                        'is_location_valid'      => true,
                        'approved_1_by'          => 1,
                        'approved_1_at'          => now(),
                        'approved_2_by'          => 1,
                        'approved_2_at'          => now(),
                    ]);

                    $this->info("User {$user->name} divonis Alpha untuk tgl {$dateStr}. Deadline: {$deadline}");
                    $counter++;
                }
            }
        }

        $this->info("Selesai. Total Alpha Baru: {$counter}");
    }

    private function getShiftForDate($user, $dateStr)
    {
        $specialShift = \App\Models\ShiftSubmission::where('user_id', $user->id)
            ->where('date', $dateStr)
            ->where('status', 'approved')
            ->with('targetShift')
            ->first();

        if ($specialShift && $specialShift->targetShift) {
            return $specialShift->targetShift;
        }

        return $user->employee->shift ?? null;
    }
}
