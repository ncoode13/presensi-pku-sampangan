<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller as ApiController;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceLocation;
use App\Models\AttendanceLog;
use App\Models\Shift;
use App\Models\TimeOffRequest;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\ImageService;
use Illuminate\Support\Facades\Storage;
use App\Notifications\SubmissionNotification;
use Illuminate\Support\Facades\Notification;
use App\Models\ShiftSchedule;
use App\Models\Holiday;
use App\Models\AttendanceSubmission;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends ApiController
{
    public function index(Request $request)
    {
        $currentUser = auth()->user();
        $date = $request->date ?? Carbon::now()->format('Y-m-d');
        $carbonDate = Carbon::parse($date);
        $dayIndex = $carbonDate->day;
        $month = $carbonDate->month;
        $year = $carbonDate->year;

        $query = User::with(['employee.shift', 'attendance' => function ($q) use ($date) {
            $q->whereDate('date', $date)->with('shift', 'logs');
        }]);

        $role = strtolower($currentUser->role);
        $position = strtolower($currentUser->employee->position->name ?? '');

        $isSuperAdmin = in_array($role, ['superadmin', 'director'])
            || str_contains($position, 'sdi')
            || str_contains($position, 'sumber daya insani');

        $isAdmin = $role === 'admin';

        if ($isSuperAdmin) {
            if ($request->has('user_id') && $request->user_id != null) {
                $query->where('id', $request->user_id);
            }
        } elseif ($isAdmin) {
            $query->where(function ($q) use ($currentUser) {
                $q->where('id', $currentUser->id)
                    // Cek Approver asli
                    ->orWhereHas('approvalLines', function ($subQuery) use ($currentUser) {
                        $subQuery->where('approver_id', $currentUser->id);
                    })
                    // Cek Delegasi Approval
                    ->orWhereHas('approvalLines.approver.approvalDelegation', function ($subQuery) use ($currentUser) {
                        $subQuery->where('delegate_to_id', $currentUser->id)->active();
                    });
            });

            if ($request->has('user_id') && $request->user_id != null) {
                $query->where('id', $request->user_id);
            }
        } else {
            $query->where('id', $currentUser->id);
        }

        // Ubah 'keyword' menjadi 'search'
        if ($request->has('search') && $request->search != '') {
            $keyword = $request->search; // Tetap simpan di variabel $keyword
            $query->where('name', 'ilike', "%{$keyword}%");
        }

        $users = $query->paginate(10);

        $userIds = $users->pluck('id')->toArray();
        $schedules = ShiftSchedule::whereIn('user_id', $userIds)
            ->where('month', $month)
            ->where('year', $year)
            ->get()
            ->keyBy('user_id');

        $isNationalHoliday = Holiday::whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        $formattedData = $users->getCollection()->map(function ($user) use ($schedules, $dayIndex, $isNationalHoliday, $carbonDate, $date) {

            $shiftPlan = null;
            $isOff = false;
            $scheduleStatusLabel = 'Tanpa Jadwal';

            $userSchedule = $schedules[$user->id] ?? null;
            $schedData = $userSchedule ? $userSchedule->schedule_data : [];

            if (is_string($schedData)) {
                $schedData = json_decode($schedData, true);
            }

            $dayKey = (string) $dayIndex;

            if (is_array($schedData) && isset($schedData[$dayKey])) {
                $dayData = $schedData[$dayKey];
                $isOff = $dayData['is_off'] ?? false;

                if (!$isOff && !empty($dayData['shift_id'])) {
                    $shiftPlan = \App\Models\Shift::find($dayData['shift_id']);
                }
            } else {
                $shiftPlan = $user->employee->shift ?? null;
                if ($carbonDate->isSunday()) {
                    $isOff = true;
                }
            }

            if ($isNationalHoliday) {
                $isOff = true;
                $scheduleStatusLabel = $isNationalHoliday->name;
            }

            if ($isOff) {
                $scheduleStatusLabel = 'Libur';
            }

            $shiftNameDisplay = $isOff ? 'Libur' : ($shiftPlan->name ?? '-');
            $scheduleIn = $shiftPlan->start_time ?? '-';
            $scheduleOut = $shiftPlan->end_time ?? '-';

            // 4. CEK ABSENSI ACTUAL
            $attendance = $user->attendance->first();
            $logIn = $attendance ? $attendance->logs->where('attendance_type', 'check_in')->first() : null;
            $logOut = $attendance ? $attendance->logs->where('attendance_type', 'check_out')->first() : null;

            $finalStatus = 'Alpha';
            $finalStatusLabel = 'Belum Absen';

            if ($attendance) {
                $finalStatus = $attendance->status;
                $finalStatusLabel = \App\Models\Attendance::statusLabels()[$attendance->status] ?? 'Unknown';
            } else {
                if ($isOff) {
                    $finalStatus = 'holiday';
                    $finalStatusLabel = 'Libur';
                    $scheduleIn = '-';
                    $scheduleOut = '-';
                } elseif (!$shiftPlan) {
                    $finalStatus = 'no_schedule';
                    $finalStatusLabel = '-';
                }
            }

            return [
                'id'             => $attendance ? $attendance->id : 'virtual_' . $user->id,
                'real_id'        => $attendance ? $attendance->id : null,
                'user_name'      => $user->name,
                'date'           => $date,

                'shift_name'     => $shiftNameDisplay,
                'schedule_in'    => $scheduleIn,
                'schedule_out'   => $scheduleOut,

                'check_in_time'  => $logIn ? Carbon::parse($logIn->time)->format('H:i') : '-',
                'check_out_time' => $logOut ? Carbon::parse($logOut->time)->format('H:i') : '-',

                'status'         => $finalStatus,
                'status_label'   => $finalStatusLabel,

                'employee_id'    => $user->id,
                'location_valid' => $attendance ? $attendance->is_location_valid : false
            ];
        });
        $users->setCollection($formattedData);

        return $this->respondSuccess($users);
    }

    public function store(Request $request, ImageService $imageService)
    {
        $user = auth()->user();

        if (!$user) {
            return $this->respondError('Sesi anda telah berakhir. Silakan login ulang.');
        }

        $currentUserId = $user->id;

        $validator = Validator::make($request->all(), [
            'lat'             => 'required',
            'lng'             => 'required',
            'attendance_type' => 'required|in:check_in,check_out',
            'photo'           => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->respondError($validator->errors()->first());
        }

        try {
            return DB::transaction(function () use ($request, $imageService, $user, $currentUserId) {
                $nowObject = Carbon::now();
                $date      = $nowObject->toDateString();
                $time      = $nowObject->toTimeString();

                $photoPath = null;
                if ($request->hasFile('photo')) {
                    $photoPath = $imageService->compressAndUpload(
                        $request->file('photo'),
                        'attendance_logs'
                    );
                }

                $attendanceId = null;
                $attendance   = null;
                $responseMsg  = '';
                $systemNote   = null;

                if ($request->attendance_type == 'check_in') {

                    $shiftResult = $this->getActiveShift($currentUserId);
                    $shift = null;

                    if ($shiftResult === 'libur') {
                        $shift = null;
                        $systemNote = "Masuk di Hari Libur (Roster Off)";
                    } elseif ($shiftResult instanceof Shift) {
                        $shift = $shiftResult;
                    } elseif (!$shiftResult) {
                        throw new \Exception('Jadwal Shift tidak ditemukan untuk hari ini. Harap hubungi HRD.');
                    }

                    $locations = AttendanceLocation::all();
                    $foundLocationId = null;
                    $isLocationValid = false;

                    foreach ($locations as $loc) {
                        $distance = $this->calculateDistance(
                            $request->lat,
                            $request->lng,
                            $loc->latitude,
                            $loc->longitude
                        );

                        if ($distance <= $loc->radius) {
                            $foundLocationId = $loc->id;
                            $isLocationValid = true;
                            break;
                        }
                    }

                    $isLate = false;
                    if ($shift) {
                        $shiftTime = Carbon::parse($date . ' ' . $shift->start_time);
                        $maxTime   = $shiftTime->copy()->addMinutes($shift->tolerance_come_too_late);
                        $isLate    = $nowObject->greaterThan($maxTime);
                    }

                    $currentStatus = Attendance::STATUS_PENDING;

                    if (!$isLocationValid) {
                        $currentStatus = Attendance::STATUS_PENDING;
                        $systemNote    = $systemNote ? "$systemNote. Lokasi Invalid" : "Lokasi di luar jangkauan";
                    } elseif ($isLate) {
                        $currentStatus = Attendance::STATUS_LATE;
                        $systemNote    = "Terlambat";
                    } else {
                        $currentStatus = Attendance::STATUS_PRESENT;
                    }

                    $existingAttendance = Attendance::where('user_id', $currentUserId)
                        ->where('date', $date)
                        ->first();

                    if (!$existingAttendance) {
                        $attendance = Attendance::create([
                            'user_id'                => $currentUserId,
                            'shift_id'               => $shift ? $shift->id : null,
                            'attendance_location_id' => $foundLocationId,
                            'date'                   => $date,
                            'status'                 => $currentStatus,
                            'is_location_valid'      => $isLocationValid,
                            'current_step'           => 1,
                            'total_steps'            => 1,
                        ]);

                        $shiftName = $shift ? $shift->name : 'Jadwal Libur/Lembur';
                        $responseMsg = 'Berhasil Check-in. Jadwal: ' . $shiftName;
                    } else {
                        $dbStatus = $existingAttendance->status;

                        if ($dbStatus == Attendance::STATUS_PENDING && $currentStatus != Attendance::STATUS_PENDING) {
                            $existingAttendance->update([
                                'status'                 => $currentStatus,
                                'attendance_location_id' => $foundLocationId,
                                'is_location_valid'      => true,
                                'shift_id'               => $shift ? $shift->id : null,
                            ]);

                            $existingAttendance->approvalSteps()->delete();

                            $attendance  = $existingAttendance;
                            $responseMsg = 'Data absensi diperbarui menjadi Valid.';
                        } else {
                            $attendance  = $existingAttendance;
                            $responseMsg = 'Absensi tambahan dicatat ke log.';
                        }
                    }
                    $attendanceId = $attendance->id;
                } else {
                    $attendance = Attendance::with('shift')
                        ->where('user_id', $currentUserId)
                        ->where('date', $date)
                        ->first();

                    if (!$attendance) {
                        $shiftResult = $this->getActiveShift($currentUserId);
                        $shiftId = ($shiftResult instanceof Shift) ? $shiftResult->id : null;

                        $attendance = Attendance::create([
                            'user_id'           => $currentUserId,
                            'date'              => $date,
                            'status'            => Attendance::STATUS_LATE,
                            'shift_id'          => $shiftId,
                            'is_location_valid' => true,
                            'current_step'      => 1,
                            'total_steps'       => 1
                        ]);

                        $responseMsg = 'Check-out berhasil (Anda tidak melakukan Check-in sebelumnya).';
                        $systemNote  = 'Tanpa Check-in';
                    } else {
                        $responseMsg = 'Berhasil melakukan Check-out.';
                    }

                    $attendanceId = $attendance->id;
                    $shift = $attendance->shift;

                    if ($shift) {
                        $shiftEndTime = Carbon::parse($date . ' ' . $shift->end_time);
                        $toleranceMinutes = $shift->tolerance_go_home_early ?? 0;
                        $earliestAllowedTime = $shiftEndTime->copy()->subMinutes($toleranceMinutes);

                        if ($nowObject->lessThan($earliestAllowedTime)) {
                            if ($attendance->status == Attendance::STATUS_PRESENT) {
                                $attendance->update([
                                    'status' => Attendance::STATUS_EARLY_OUT
                                ]);
                            }
                            $responseMsg  = 'Check-out berhasil (Pulang Lebih Awal).';
                            $earlyOutNote = "Pulang Awal";
                            $systemNote   = $systemNote ? "$systemNote, $earlyOutNote" : $earlyOutNote;
                        } else {
                            // --- TAMBAHKAN BLOK INI ---
                            // Jika absen pulang di waktu yang benar DAN statusnya sempat "Pulang Awal"
                            if ($attendance->status == Attendance::STATUS_EARLY_OUT) {
                                $attendance->update([
                                    'status' => Attendance::STATUS_PRESENT // Kembalikan jadi Hadir Normal
                                ]);
                                $responseMsg  = 'Check-out berhasil diperbarui (Tepat Waktu).';
                                $earlyOutNote = "Koreksi Pulang Normal";
                                $systemNote   = $systemNote ? "$systemNote, $earlyOutNote" : $earlyOutNote;
                            }
                        }
                    }
                }

                $notesParts = [];
                if (!empty($systemNote)) {
                    $notesParts[] = $systemNote;
                }
                if (!empty($request->note)) {
                    $notesParts[] = $request->note;
                }
                $finalNote = implode(' | ', $notesParts);

                $log = AttendanceLog::create([
                    'attendance_id'   => $attendanceId,
                    'attendance_type' => $request->attendance_type,
                    'time'            => $time,
                    'lat'             => $request->lat,
                    'lng'             => $request->lng,
                    'photo'           => $photoPath,
                    'device_info'     => $request->device_info ?? 'Unknown',
                    'note'            => $finalNote,
                ]);

                $attendance->refresh();

                if ($attendance->status == Attendance::STATUS_PENDING && $attendance->approvalSteps()->count() === 0) {

                    $approvalLines = $user->approvalLines;

                    if ($approvalLines->isNotEmpty()) {
                        $attendance->update([
                            'current_step' => 1,
                            'total_steps'  => $approvalLines->count()
                        ]);

                        foreach ($approvalLines as $line) {
                            $attendance->approvalSteps()->create([
                                'approver_id' => $line->approver_id,
                                'step'        => $line->step,
                                'status'      => 'pending'
                            ]);
                        }

                        $firstApprover = $attendance->approvalSteps()->where('step', 1)->first()->approver ?? null;

                        if ($firstApprover) {
                            $attendance->load('user.employee');
                            $userAvatar = $attendance->user->employee->avatar ?? null;
                            $photoUrl = $userAvatar ? Storage::url($userAvatar) : null;

                            $title   = "Butuh Validasi Absensi (Tahap 1)";
                            $message = "{$attendance->user->name} melakukan absensi di LOKASI INVALID. Mohon ditinjau.";
                            $link    = "/attendance/approvals/detail/{$attendance->id}?source_type=attendance";

                            \App\Services\ApprovalDelegationService::sendParallelNotification(
                            $firstApprover, 
                            new SubmissionNotification($title, $message, $link, 'attendance', $photoUrl)
                            );
                        }
                    }
                }

                return $this->respondSuccess([
                    'attendance'     => $attendance,
                    'attendance_log' => $log,
                    'status_code'    => (int) $attendance->status,
                    'status_label'   => Attendance::statusLabels()[$attendance->status] ?? 'Unknown',
                    'is_pending'     => $attendance->status == Attendance::STATUS_PENDING
                ], $responseMsg);
            });
        } catch (\Throwable $th) {
            return $this->respondError($th->getMessage());
        }
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000;

        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo   = deg2rad($lat2);
        $lonTo   = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        return $angle * $earthRadius;
    }










    public function show($id)
    {
        try {
            $attendance = Attendance::with(['user.employee', 'shift', 'logs'])->find($id);

            if (!$attendance) {
                return $this->respondError('Data absensi tidak ditemukan');
            }

            // LOGIKA CAN APPROVE
            $canApprove = false;
            $currentStepData = $attendance->approvalSteps()
                ->where('step', $attendance->current_step)
                ->where('status', 'pending')
                ->first();

            if ($currentStepData) {
                $canApprove = \App\Services\ApprovalDelegationService::canPerformAction(
                    $currentStepData->approver_id, 
                    $attendance->user_id
                );
            }

            $statusLabels = Attendance::statusLabels();

            $historyLogs = $attendance->logs->sortBy('created_at')->values()->map(function ($log) {
                return [
                    'id'             => $log->id,
                    'attendance_id'  => $log->attendance_id,
                    'type'           => $log->attendance_type == 'check_in' ? 'Check In' : 'Check Out',
                    'time'           => Carbon::parse($log->time)->format('H:i:s'),
                    'photo_url'      => $log->photo ? Storage::url($log->photo) : null,
                    'lat'            => $log->lat,
                    'lng'            => $log->lng,
                    'device_info'    => $log->device_info,
                    'note'           => $log->note,
                    'created_at_human' => Carbon::parse($log->created_at)->diffForHumans(),
                ];
            });

            $avatarUrl = null;
            $rawAvatar = $attendance->user?->employee?->avatar;

            if ($rawAvatar) {
                if (str_starts_with($rawAvatar, 'http')) {
                    $avatarUrl = $rawAvatar;
                } else {
                    $avatarUrl = Storage::url($rawAvatar);
                }
            }

            $data = [
                'id'            => $attendance->id,
                'date'          => Carbon::parse($attendance->date)->format('d-m-Y'),
                'employee_name' => $attendance->user->name ?? 'User Terhapus',

                'can_approve'   => $canApprove,
                'avatar'        => $avatarUrl,

                'shift_name'    => $attendance->shift->name ?? '-',
                'schedule_in'   => $attendance->shift->start_time ?? '-',
                'schedule_out'  => $attendance->shift->end_time ?? '-',
                'status'        => $statusLabels[(int)$attendance->status] ?? 'Unknown',
                'status_id'     => (int)$attendance->status,
                'logs'          => $historyLogs,
            ];

            return $this->respondSuccess($data, 'Detail absensi berhasil diambil');
        } catch (\Throwable $th) {
            return $this->respondError('Terjadi kesalahan: ' . $th->getMessage());
        }
    }

    public function summaryHistory(Request $request)
    {
        try {
            $currentUser = auth()->user();

            $targetUserId = $currentUser->id;

            if (in_array($currentUser->role, ['admin', 'superadmin']) && $request->has('user_id')) {
                $targetUserId = $request->user_id;
            }

            $targetUser = User::find($targetUserId);
            if (!$targetUser) {
                return $this->respondError('User tidak ditemukan');
            }

            $year = $request->query('year', Carbon::now()->year);
            $month = $request->query('month', Carbon::now()->month);

            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate   = Carbon::createFromDate($year, $month, 1)->endOfMonth();

            $attendances = Attendance::with('logs')
                ->where('user_id', $targetUserId)
                ->whereBetween('date', [$startDate, $endDate])
                ->whereIn('status', [
                    Attendance::STATUS_PRESENT,
                    Attendance::STATUS_LATE,
                    Attendance::STATUS_EARLY_OUT
                ])
                ->get();

            $totalAttendance = $attendances->count();

            $totalLate = $attendances->where('status', Attendance::STATUS_LATE)->count();

            $noClockOut = $attendances->filter(function ($att) {
                if (!$att->logs) return false;
                return !$att->logs->contains('attendance_type', 'check_out');
            })->count();

            $noClockIn = $attendances->filter(function ($att) {
                if (!$att->logs) return false;
                return !$att->logs->contains('attendance_type', 'check_in');
            })->count();

            $attendanceDates = $attendances->pluck('date')->toArray();

            $leaves = TimeOffRequest::where('user_id', $targetUserId)
                ->where('status', 'approved')
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate]);
                })
                ->get();

            $totalLeave = 0;
            $leaveDates = [];

            foreach ($leaves as $leave) {
                $start = Carbon::parse($leave->start_date)->max($startDate);
                $end   = Carbon::parse($leave->end_date)->min($endDate);

                $days = $start->diffInDays($end) + 1;
                $totalLeave += $days;

                $period = CarbonPeriod::create($start, $end);
                foreach ($period as $date) {
                    $leaveDates[] = $date->format('Y-m-d');
                }
            }

            $totalAbsent = 0;
            $periodMonth = CarbonPeriod::create($startDate, $endDate);

            foreach ($periodMonth as $dt) {
                $dateStr = $dt->format('Y-m-d');

                if (!$dt->isWeekend() && !in_array($dateStr, $attendanceDates) && !in_array($dateStr, $leaveDates)) {
                    if ($dt->lessThanOrEqualTo(Carbon::now())) {
                        $totalAbsent++;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Rangkuman user berhasil diambil',
                'data' => [
                    'month'       => $startDate->format('F Y'),
                    'target_user' => $targetUser->name,
                    'summary'     => [
                        'user_id'          => $targetUser->id,
                        'user_name'        => $targetUser->name,
                        'total_attendance' => $totalAttendance,
                        'total_late'       => $totalLate,
                        'no_clock_in'      => $noClockIn,
                        'no_clock_out'     => $noClockOut,
                        'total_leave'      => $totalLeave,
                        'total_absent'     => $totalAbsent
                    ]
                ],
                'code' => 200
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
                'data' => null,
                'code' => 500
            ], 500);
        }
    }



    public function dashboardSummary(Request $request)
    {
        try {
            $year  = $request->query('year', Carbon::now()->year);
            $month = $request->query('month', Carbon::now()->month);

            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate   = Carbon::createFromDate($year, $month, 1)->endOfMonth();

            $attendances = Attendance::whereBetween('date', [$startDate, $endDate])->get();

            $totalAttendance = $attendances->whereIn('status', [
                Attendance::STATUS_PRESENT,
                Attendance::STATUS_LATE,
                Attendance::STATUS_EARLY_OUT
            ])->count();

            $totalLate  = $attendances->where('status', Attendance::STATUS_LATE)->count();

            $totalLeave = $attendances->where('status', Attendance::STATUS_LEAVE)->count();

            $totalNoClockOut = $attendances->whereIn('status', [1, 3, 6])
                ->filter(fn($att) => $att->logs->where('attendance_type', 'check_out')->isEmpty())
                ->count();

            $totalNoClockIn = $attendances->whereIn('status', [1, 3, 6])
                ->filter(fn($att) => $att->logs->where('attendance_type', 'check_in')->isEmpty())
                ->count();

            $totalEmployees = \App\Models\User::count();
            $daysInMonth    = $startDate->diffInDays($endDate->isFuture() ? now() : $endDate) + 1;

            $potentialRecords = $totalEmployees * $daysInMonth;
            $existingRecords  = $attendances->count();
            $totalAlpha       = max(0, $potentialRecords - $existingRecords);

            return response()->json([
                'success' => true,
                'data' => [
                    'month'            => $startDate->format('F Y'),
                    'total_attendance' => $totalAttendance,
                    'total_late'       => $totalLate,
                    'no_clock_in'      => $totalNoClockIn,
                    'no_clock_out'     => $totalNoClockOut,
                    'total_leave'      => $totalLeave,
                    'total_absent'     => $totalAlpha
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()], 500);
        }
    }

    private function getActiveShift($userId)
    {
        $date = Carbon::now();
        $todayStr = $date->format('Y-m-d');
        $dayIndex = $date->day;
        $month = $date->month;
        $year = $date->year;

        $specialShift = \App\Models\ShiftSubmission::where('user_id', $userId)
            ->where('date', $todayStr)
            ->where('status', 'approved')
            ->with('targetShift')
            ->first();

        if ($specialShift && $specialShift->targetShift) {
            return $specialShift->targetShift;
        }

        $roster = ShiftSchedule::where('user_id', $userId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        $dayKey = (string) $dayIndex;

        if ($roster && isset($roster->schedule_data[$dayKey])) {
            $dailySchedule = $roster->schedule_data[$dayKey];

            if (isset($dailySchedule['is_off']) && $dailySchedule['is_off'] == true) {
                return 'libur';
            }

            if (isset($dailySchedule['shift_id']) && $dailySchedule['shift_id']) {
                return Shift::find($dailySchedule['shift_id']);
            }
        }


        $user = User::with('employee.shift')->find($userId);
        return $user->employee->shift ?? null;
    }

    public function getMonthlyHistory(Request $request)
    {
        $user = Auth::user();
        $employee = $user->employee;

        if (!$employee) {
            return $this->respondError('Data karyawan tidak ditemukan.');
        }

        $month = $request->query('month', Carbon::now()->month);
        $year = $request->query('year', Carbon::now()->year);

        $startDate = Carbon::createFromDate($year, $month, 1);
        $daysInMonth = $startDate->daysInMonth;
        $endDate = $startDate->copy()->endOfMonth();

        $allShifts = Shift::all()->keyBy('id');

        $attendances = $user->attendance()
            ->with('logs')
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->get()
            ->keyBy('date');

        $calendarMap = [];
        $defaultShiftId = $employee->shift_id;

        if ($employee->work_scheme === 'office') {
            $dbHolidays = Holiday::where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate]);
            })->get();

            foreach ($dbHolidays as $holiday) {
                $period = CarbonPeriod::create($holiday->start_date, $holiday->end_date);
                foreach ($period as $date) {
                    if ($date->month == $month) {
                        $calendarMap[$date->format('Y-m-d')] = [
                            'is_holiday' => true,
                            'label' => $holiday->name,
                            'shift_id' => null
                        ];
                    }
                }
            }

            $periodMonth = CarbonPeriod::create($startDate, $endDate);
            foreach ($periodMonth as $date) {
                $dateKey = $date->format('Y-m-d');
                if (isset($calendarMap[$dateKey])) continue;

                if ($date->isSunday()) {
                    $calendarMap[$dateKey] = ['is_holiday' => true, 'label' => 'Libur Akhir Pekan', 'shift_id' => null];
                } else {
                    $calendarMap[$dateKey] = ['is_holiday' => false, 'label' => '', 'shift_id' => $defaultShiftId];
                }
            }
        } else {
            $scheduleRow = ShiftSchedule::where('user_id', $user->id)
                ->where('month', $month)->where('year', $year)->first();

            if ($scheduleRow && !empty($scheduleRow->schedule_data)) {
                foreach ($scheduleRow->schedule_data as $dayIndex => $val) {
                    $dateKey = Carbon::createFromDate($year, $month, $dayIndex)->format('Y-m-d');
                    $isOff = $val['is_off'] ?? false;
                    $calendarMap[$dateKey] = [
                        'is_holiday' => $isOff,
                        'label' => $isOff ? 'Libur' : '',
                        'shift_id' => $isOff ? null : ($val['shift_id'] ?? $defaultShiftId)
                    ];
                }
            }
        }

        $historyData = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $currentDate = Carbon::createFromDate($year, $month, $day);
            $dateString = $currentDate->format('Y-m-d');

            $plan = $calendarMap[$dateString] ?? ['is_holiday' => false, 'label' => '', 'shift_id' => $defaultShiftId];

            $shiftObj = null;
            $shiftDetail = null;
            if ($plan['shift_id'] && isset($allShifts[$plan['shift_id']])) {
                $shiftObj = $allShifts[$plan['shift_id']];
                $shiftDetail = [
                    'name' => $shiftObj->name,
                    'start_time' => $shiftObj->start_time,
                    'end_time' => $shiftObj->end_time,
                    'time_range' => Carbon::parse($shiftObj->start_time)->format('H:i') . ' - ' . Carbon::parse($shiftObj->end_time)->format('H:i')
                ];
            }

            $attendance = $attendances[$dateString] ?? null;

            $clockIn = '-';
            $clockOut = '-';

            $statusBadge = 'absent';
            $badgeLabel = 'Tidak Hadir';
            $badgeColor = 'red';


            if ($attendance) {
                $logIn = $attendance->logs->where('attendance_type', 'check_in')->sortBy('time')->first();
                $logOut = $attendance->logs->where('attendance_type', 'check_out')->sortByDesc('time')->first();

                $clockIn = $logIn ? Carbon::parse($logIn->time)->format('H:i') : '-';
                $clockOut = $logOut ? Carbon::parse($logOut->time)->format('H:i') : '-';


                if ($attendance->status == \App\Models\Attendance::STATUS_LEAVE) {
                    $statusBadge = 'leave';
                    $badgeLabel = 'Cuti';
                    $badgeColor = 'blue';
                } else {
                    $statusBadge = 'present';
                    $badgeLabel = 'Hadir';
                    $badgeColor = 'green';

                    if ($shiftObj) {
                        if ($logIn) {
                            $shiftStart = Carbon::parse($shiftObj->start_time);
                            $lateThreshold = $shiftStart->copy()->addMinutes($shiftObj->tolerance_come_too_late);
                            $checkInTime = Carbon::parse($logIn->time);

                            if ($checkInTime->format('H:i:s') > $lateThreshold->format('H:i:s')) {
                                $statusBadge = 'late';
                                $badgeLabel = 'Terlambat';
                                $badgeColor = 'orange';
                            }
                        }

                        if ($logOut) {
                            $shiftEnd = Carbon::parse($shiftObj->end_time);
                            $earlyTolerance = $shiftObj->tolerance_go_home_early ?? 0;
                            $earlyThreshold = $shiftEnd->copy()->subMinutes($earlyTolerance);
                            $checkOutTime = Carbon::parse($logOut->time);

                            if ($checkOutTime->format('H:i:s') < $earlyThreshold->format('H:i:s')) {
                                if ($statusBadge !== 'late') {
                                    $statusBadge = 'early_leave';
                                    $badgeLabel = 'Pulang Awal';
                                    $badgeColor = 'gray';
                                }
                            }
                        }
                    }
                }
            } elseif ($currentDate->isFuture()) {
                $statusBadge = 'future';
                $badgeLabel = '-';
                $badgeColor = 'gray';
            } elseif ($plan['is_holiday']) {
                $statusBadge = 'holiday';
                $badgeLabel = $plan['label'];
                $badgeColor = 'red';
            }

            $historyData[] = [
                'date' => $dateString,
                'day_name' => $currentDate->locale('id')->dayName,
                'is_holiday' => $plan['is_holiday'],
                'shift' => $shiftDetail,
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,

                'status' => $statusBadge,
                'label' => $badgeLabel,
                'color' => $badgeColor,
            ];
        }

        return $this->respondSuccess([
            'meta' => [
                'month' => $month,
                'year' => $year,
                'work_scheme' => $employee->work_scheme
            ],
            'histories' => $historyData
        ]);
    }



    public function getReportData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month' => 'required|integer|min:1|max:12',
            'year'  => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->respondError($validator->errors()->first());
        }

        $month = $request->month;
        $year  = $request->year;

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate   = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        $daysInMonth = $startDate->daysInMonth;

        $users = User::with([
            'employee.department',
            'employee.position',
            'employee.job_level',
            'employee.employment_status',
        ])
            // ->where('role', '!=', 'superadmin')
            ->orderBy('name', 'asc')
            ->get();

        $attendances = Attendance::with('logs')
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy('user_id');

        $leaves = TimeOffRequest::with('timeOff')
            ->where('status', 'approved')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate]);
            })
            ->get()
            ->groupBy('user_id');

        $holidays = Holiday::where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
                ->orWhereBetween('end_date', [$startDate, $endDate]);
        })->get();

        $isNationalHolidayFunc = function ($dateStr) use ($holidays) {
            $date = Carbon::parse($dateStr);
            foreach ($holidays as $h) {
                if ($date->between($h->start_date, $h->end_date)) return true;
            }
            return false;
        };

        $reportData = $users->map(function ($user) use ($attendances, $leaves, $daysInMonth, $year, $month, $isNationalHolidayFunc) {
            $userAttendances = $attendances->get($user->id) ? $attendances->get($user->id)->keyBy('date') : collect([]);
            $userLeaves = $leaves->get($user->id) ?? collect([]);

            $dailyStatus = [];
            $summary = [
                'H' => 0,
                'A' => 0,
                'LI' => 0,
                'EO' => 0,
                'NCI' => 0,
                'NCO' => 0,
                'S' => 0,
                'C' => 0,

                'total_present' => 0,
                'total_present_workday' => 0,
                'total_present_dayoff'  => 0,

                'total_dayoff'          => 0,
                'total_national_holiday' => 0,
                'total_company_holiday' => 0,
                'total_special_holiday' => 0,

                'cuti_istri_melahirkan' => 0,
                'cuti_keguguran'        => 0,
                'cuti_melahirkan'       => 0,
                'cuti_keluarga_sakit'   => 0,
                'cuti_tahunan'          => 0,
                'libur_proporsional'    => 0,
            ];

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $currentObj = Carbon::createFromDate($year, $month, $day);
                $dateStr = $currentObj->format('Y-m-d');

                $att = $userAttendances->get($dateStr);
                $code = '';

                $isWeekend = $currentObj->isSunday();
                $isNatHoliday = $isNationalHolidayFunc($dateStr);

                $activeLeave = $userLeaves->first(function ($l) use ($currentObj) {
                    return $currentObj->between($l->start_date, $l->end_date);
                });

                if ($activeLeave) {
                    $leaveName = strtolower($activeLeave->timeOff->name ?? '');

                    if (str_contains($leaveName, 'istri melahirkan')) {
                        $summary['cuti_istri_melahirkan']++;
                        $code = 'C';
                    } elseif (str_contains($leaveName, 'keguguran') && !str_contains($leaveName, 'istri')) {
                        $summary['cuti_keguguran']++;
                        $code = 'C';
                    } elseif (str_contains($leaveName, 'melahirkan') && !str_contains($leaveName, 'istri')) {
                        $summary['cuti_melahirkan']++;
                        $code = 'C';
                    } elseif (str_contains($leaveName, 'keluarga sakit')) {
                        $summary['cuti_keluarga_sakit']++;
                        $code = 'C';
                    } elseif (str_contains($leaveName, 'tahunan')) {
                        $summary['cuti_tahunan']++;
                        $code = 'C';
                    } elseif (str_contains($leaveName, 'proporsional')) {
                        $summary['libur_proporsional']++;
                        $code = 'L';
                    } elseif (str_contains($leaveName, 'sakit')) {
                        $summary['S']++;
                        $code = 'S';
                    } else {
                        $summary['C']++;
                        $code = 'C';
                    }
                } elseif ($att) {
                    $hasIn  = $att->logs->where('attendance_type', 'check_in')->isNotEmpty();
                    $hasOut = $att->logs->where('attendance_type', 'check_out')->isNotEmpty();

                    if (!$hasIn) {
                        $code = 'NCI';
                        $summary['NCI']++;
                    } elseif (!$hasOut) {
                        $code = 'NCO';
                        $summary['NCO']++;
                    } elseif ($att->status == 3) {
                        $code = 'LI';
                        $summary['LI']++;
                    } elseif ($att->status == 6) {
                        $code = 'EO';
                        $summary['EO']++;
                    } else {
                        $code = 'H';
                        $summary['H']++;
                    }

                    $summary['total_present']++;
                    if ($isWeekend || $isNatHoliday) {
                        $summary['total_present_dayoff']++;
                    } else {
                        $summary['total_present_workday']++;
                    }
                } else {
                    if ($isNatHoliday) {
                        $code = 'L';
                        $summary['total_national_holiday']++;
                    } elseif ($isWeekend) {
                        $code = 'L';
                        $summary['total_dayoff']++;
                    } elseif ($currentObj->isFuture()) {
                        $code = '-';
                    } else {
                        $code = 'A';
                        $summary['A']++;
                    }
                }
                $dailyStatus[$day] = $code;
            }

            $summary['total_dayoff_and_holiday'] =
                $summary['total_dayoff'] +
                $summary['total_national_holiday'] +
                $summary['total_company_holiday'] +
                $summary['total_special_holiday'];

            return [
                'employee_id' => $user->employee->employee_id ?? '-',
                'name'        => $user->name,
                'department'  => $user->employee->department->name ?? '-',
                'position'    => $user->employee->position->name ?? '-',
                'job_level'   => $user->employee->job_level->name ?? '-',
                'join_date'   => $user->employee->join_date ?? '-',
                'employment_status' => $user->employee->employment_status->name ?? '-',

                'daily'       => $dailyStatus,
                'summary'     => $summary
            ];
        });

        return $this->respondSuccess($reportData);
    }

    /**
     * FITUR PENGHARGAAN
     */
    public function getRewardData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_month' => 'required|integer|min:1|max:12',
            'end_month'   => 'required|integer|min:1|max:12',
            'year'  => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->respondError($validator->errors()->first());
        }

        $startMonth = $request->start_month;
        $endMonth   = $request->end_month;
        $year       = $request->year;

        $startDate = Carbon::createFromDate($year, $startMonth, 1)->startOfMonth();
        $endDate   = Carbon::createFromDate($year, $endMonth, 1)->endOfMonth();

        if ($endDate->isFuture()) {
            $endDate = Carbon::now();
        }

        $daysInPeriod = $startDate->diffInDays($endDate) + 1;

        $users = User::with([
            'employee.department',
            'employee.position',
        ])
            // ->where('role', '!=', 'superadmin')
            ->orderBy('name', 'asc')
            ->get();

        $attendances = Attendance::with(['logs', 'shift'])
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy('user_id');

        $holidays = Holiday::where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
                ->orWhereBetween('end_date', [$startDate, $endDate]);
        })->get();

        $isNationalHolidayFunc = function ($dateStr) use ($holidays) {
            $date = Carbon::parse($dateStr);
            foreach ($holidays as $h) {
                if ($date->between($h->start_date, $h->end_date)) return true;
            }
            return false;
        };

        $approvedSubmissions = AttendanceSubmission::where('status', AttendanceSubmission::STATUS_APPROVED)
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy('user_id');

        $candidates = [];

        // Debugging Log Awal
        \Log::info("=== MEMULAI REWARD DATA DEBUG ($startMonth s/d $endMonth / $year) ===");

        foreach ($users as $user) {
            $joinDate = ($user->employee && $user->employee->join_date) ? Carbon::parse($user->employee->join_date) : $startDate;

            $relevantStartForUser = $joinDate->gt($startDate) ? $joinDate : $startDate;
            $userPotentialDays = $relevantStartForUser->diffInDays($endDate) + 1;

            // 1. Gatekeeper minimal 15 hari
            if ($userPotentialDays < 15) {
                continue;
            }

            $userAttendances = $attendances->get($user->id) ? $attendances->get($user->id)->keyBy('date') : collect([]);
            $userSubmissions = $approvedSubmissions->get($user->id) ? $approvedSubmissions->get($user->id)->groupBy('date') : collect([]);

            $totalHadir = 0; $totalTerlambat = 0; $totalPulangAwal = 0;
            $totalAlpha = 0; $totalCuti = 0; $totalMenitRajin = 0;
            $totalPelanggaranLog = 0; $totalPengajuanDiACC = 0;

            for ($i = 0; $i < $daysInPeriod; $i++) {
                $currentObj = $startDate->copy()->addDays($i);
                $dateStr = $currentObj->format('Y-m-d');

                if($currentObj->lt($joinDate->startOfDay())) {
                    continue;
                }

                $isWeekend = $currentObj->isSunday();
                $isNatHoliday = $isNationalHolidayFunc($dateStr);
                $att = $userAttendances->get($dateStr); // Mengambil data per tanggal

                if ($att) {
                    // Hitung Menit Rajin (Hanya jika Present)
                    if ($att->status == \App\Models\Attendance::STATUS_PRESENT) {
                        $totalHadir++;
                        $checkInLog = $att->logs->where('attendance_type', 'check_in')->first();
                        if ($checkInLog && $att->shift && $att->shift->start_time) {
                            $jamScan = date('H:i:s', strtotime($checkInLog->time));
                            $jamJadwal = date('H:i:s', strtotime($att->shift->start_time));
                            $carbonScan = Carbon::createFromTimeString($jamScan);
                            $carbonJadwal = Carbon::createFromTimeString($jamJadwal);

                            if ($carbonScan->lt($carbonJadwal)) {
                                $totalMenitRajin += (int) $carbonScan->diffInMinutes($carbonJadwal);
                            }
                        }
                    }

                    // Pelanggaran Log (Lupa Absen)
                    $checkInLog  = $att->logs->where('attendance_type', 'check_in')->first();
                    $checkOutLog = $att->logs->where('attendance_type', 'check_out')->first();
                    if ((!$checkInLog || !$checkOutLog) && !$currentObj->isToday()) {
                        $totalPelanggaranLog++; 
                    }

                    // Status Lainnya
                    if ($att->status == \App\Models\Attendance::STATUS_LATE) $totalTerlambat++;
                    if ($att->status == \App\Models\Attendance::STATUS_EARLY_OUT) $totalPulangAwal++;
                    if ($att->status == \App\Models\Attendance::STATUS_LEAVE) $totalCuti++;

                    // Koreksi Manual
                    $dayReqs = $userSubmissions->get($dateStr) ?? collect([]);
                    if ($dayReqs->isNotEmpty()) $totalPengajuanDiACC++;

                } else {
                    if (!$isWeekend && !$isNatHoliday && !$currentObj->isToday()) {
                        $totalAlpha++;
                    }
                }
            }

            $deduction = ($totalAlpha * 20) + ($totalTerlambat * 5) + ($totalPulangAwal * 5) + ($totalPelanggaranLog * 10) + ($totalPengajuanDiACC * 2);
            $score = 100 - $deduction;
            if ($score < 0) $score = 0;

            // 2. Definisi Perfect yang Lebih Akurat
            $isPerfectAttendance = (
                $totalAlpha == 0 &&
                $totalTerlambat == 0 &&
                $totalPulangAwal == 0 &&
                $totalPelanggaranLog == 0 &&
                $totalPengajuanDiACC == 0 &&
                $totalHadir > 0
            );

            if ($totalHadir > 0) {
                $candidates[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'department' => $user->employee?->department?->name ?? '-',
                    'position' => $user->employee?->position?->name ?? '-',
                    'avatar' => $user->employee?->avatar ? Storage::url($user->employee->avatar) : null,
                    'metrics' => [
                        'hadir' => $totalHadir,
                        'terlambat' => $totalTerlambat,
                        'pulang_awal' => $totalPulangAwal,
                        'alpha' => $totalAlpha,
                        'lupa_absen' => $totalPelanggaranLog,
                        'koreksi_manual' => $totalPengajuanDiACC,
                        'total_menit_pagi' => $totalMenitRajin
                    ],
                    'score' => $score,
                    'is_perfect' => $isPerfectAttendance
                ];
            }
        }

        // TIE-BREAKER
        usort($candidates, function ($a, $b) {
            // Tie-Breaker 1: Skor lebih tinggi
            if ($a['score'] != $b['score']) {
                return $b['score'] <=> $a['score'];
            }
            // Tie-Breaker 2: Total hadir lebih banyak
            if ($a['metrics']['hadir'] != $b['metrics']['hadir']) {
                return $b['metrics']['hadir'] <=> $a['metrics']['hadir'];
            }
            // Tie-Breaker 3: Total menit pagi lebih banyak
            return $b['metrics']['total_menit_pagi'] <=> $a['metrics']['total_menit_pagi'];
        });

        $topCandidates = array_slice($candidates, 0, 10);

        return $this->respondSuccess($topCandidates, 'Data penghargaan berhasil diambil');
    }
}
