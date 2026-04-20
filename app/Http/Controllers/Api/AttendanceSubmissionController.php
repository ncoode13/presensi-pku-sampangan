<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\AttendanceSubmission;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\ImageService;
use Illuminate\Support\Facades\Storage;
use App\Notifications\SubmissionNotification;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

class AttendanceSubmissionController extends Controller
{


    public function index(Request $request)
    {
        try {
            $currentUser = auth()->user();
            $statusFilter = $request->query('status', 'all');
            $search = $request->query('search');

            $isPersonalMode = $request->query('mode') === 'personal';

            $liveQuery = Attendance::with(['user', 'shift', 'logs']);

            $liveQuery->whereNotIn('status', [2, 3, 4]);

            $manualQuery = AttendanceSubmission::with(['user', 'shift']);

            if ($currentUser->role !== 'admin' || $isPersonalMode) {
                $liveQuery->where('user_id', $currentUser->id);
                $manualQuery->where('user_id', $currentUser->id);
            } else {
            $manualQuery->where('user_id', '!=', $currentUser->id)
                ->whereHas('approvalSteps', function ($q) use ($currentUser) {
                    $q->where(function($sub) use ($currentUser) {
                        $sub->where('approver_id', $currentUser->id)
                            ->orWhereHas('approver.delegation', function($del) use ($currentUser) {
                                $del->where('delegate_to_id', $currentUser->id)->active();
                            });
                    })
                    ->where('status', 'pending')
                    ->whereColumn('step', 'attendance_requests.current_step');
                });
            }

            if ($statusFilter === 'pending') {
                $liveQuery->where('status', Attendance::STATUS_PENDING);
            } elseif ($statusFilter === 'approved') {
                $liveQuery->where('status', Attendance::STATUS_PRESENT);
            } elseif ($statusFilter === 'rejected') {
                $liveQuery->where('status', Attendance::STATUS_REJECTED);
            } else {
                $liveQuery->whereIn('status', [
                    Attendance::STATUS_PRESENT,
                    Attendance::STATUS_PENDING,
                    Attendance::STATUS_REJECTED
                ]);
            }

            if ($search) {
                $liveQuery->where(function ($q) use ($search) {
                    $q->whereHas('user', function ($subQ) use ($search) {
                        $subQ->where('name', 'ILIKE', '%' . $search . '%')
                            ->orWhereHas('employee', function ($subQ2) use ($search) {
                                $subQ2->where('nip', 'ILIKE', '%' . $search . '%');
                            });
                    });
                });
            }

            $liveAttendances = $liveQuery->latest()->get()->map(function ($item) {
                $log = $item->logs->sortBy('created_at')->first();
                $statusMap = [
                    Attendance::STATUS_PRESENT => 'Approved',
                    Attendance::STATUS_PENDING => 'Pending',
                    Attendance::STATUS_REJECTED => 'Rejected',
                ];
                $statusLabel = $statusMap[$item->status] ?? 'Unknown';

                $avatarUrl = null;
                if ($item->user && $item->user->avatar) {
                    if (str_starts_with($item->user->avatar, 'http')) {
                        $avatarUrl = $item->user->avatar;
                    } else {
                        $avatarUrl = Storage::url($item->user->avatar);
                    }
                }

                $attachmentUrl = ($log && $log->photo) ? Storage::url($log->photo) : null;

                return [
                    'id'            => $item->id,
                    'user_name'     => $item->user->name ?? 'Unknown',
                    'user_avatar'   => $avatarUrl,
                    'date'          => $item->date,
                    'shift_name'    => $item->shift->name ?? '-',
                    'type'          => 'Absensi Langsung',
                    'attendance_type' => $log->attendance_type ?? 'check_in',
                    'time'          => $log ? Carbon::parse($log->time)->format('H:i') : '-',
                    'reason'        => $log->note ?? 'Lokasi/Wajah tidak sesuai',
                    'attachment'    => $attachmentUrl,
                    'status_label'  => $statusLabel,
                    'status'        => strtolower($statusLabel),
                    'source_type'   => 'attendance',
                    'status_code'   => 'Live',
                    'lat'           => $log->lat ?? null,
                    'lng'           => $log->lng ?? null,
                ];
            });

            $manualQuery = AttendanceSubmission::with(['user', 'shift']);

            if ($currentUser->role !== 'admin' || $isPersonalMode) {
                $manualQuery->where('user_id', $currentUser->id);
            }

            if ($statusFilter !== 'all') {
                $manualQuery->where('status', $statusFilter);
            }

            if ($search) {
                $manualQuery->where(function ($q) use ($search) {
                    $q->whereHas('user', function ($subQ) use ($search) {
                        $subQ->where('name', 'ILIKE', '%' . $search . '%')
                            ->orWhereHas('employee', function ($subQ2) use ($search) {
                                $subQ2->where('nip', 'ILIKE', '%' . $search . '%');
                            });
                    });
                });
            }

            $manualRequests = $manualQuery->latest()->get()->map(function ($item) {
                $avatarUrl = null;
                if ($item->user && $item->user->avatar) {
                    if (str_starts_with($item->user->avatar, 'http')) {
                        $avatarUrl = $item->user->avatar;
                    } else {
                        $avatarUrl = Storage::url($item->user->avatar);
                    }
                }

                $attachmentUrl = $item->file ? Storage::url($item->file) : null;
                return [
                    'id'            => $item->id,
                    'user_name'     => $item->user->name ?? 'Unknown',
                    'user_avatar'   => $avatarUrl,
                    'date'          => $item->date,
                    'shift_name'    => $item->shift->name ?? '-',
                    'type'          => 'Pengajuan Manual',
                    'attendance_type' => $item->attendance_type,
                    'time'          => Carbon::parse($item->time)->format('H:i'),
                    'reason'        => $item->reason,
                    'attachment'    => $attachmentUrl,
                    'status_label'  => ucfirst($item->status),
                    'status'        => $item->status,
                    'source_type'   => 'request',
                    'status_code'   => 'Request',
                    'lat'           => null,
                    'lng'           => null,
                ];
            });

            $mergedData = $liveAttendances
                ->concat($manualRequests)
                ->sortByDesc('date')
                ->values();

            return response()->json([
                'success' => true,
                'message' => 'Data approval berhasil diambil',
                'data'    => $mergedData
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data: ' . $th->getMessage()
            ], 500);
        }
    }

    public function store(Request $request, ImageService $imageService)
    {
        $validator = Validator::make($request->all(), [
            'date'            => 'required|date',
            'time'            => 'required',
            'attendance_type' => 'required|in:check_in,check_out',
            'reason'          => 'required|string',
            'photo'           => 'nullable|image|max:2048',
            'shift_id'        => 'required_if:attendance_type,check_in',
        ]);

        if ($validator->fails()) {
            return $this->respondError($validator->errors()->first());
        }

        // Pastikan fungsi ini ada, jika tidak, hapus 3 baris di bawah ini
        if (method_exists($this, 'validateUploadFile')) {
            $fileError = $this->validateUploadFile($request->all());
            if ($fileError) return $this->respondError($fileError);
        }

        $currentUser = Auth::user();

        // 1. CEK APAKAH KARYAWAN PUNYA ATURAN PERSETUJUAN (APPROVER)
        $approvalLines = $currentUser->approvalLines;
        if ($approvalLines->isEmpty()) {
            return $this->respondError("Aturan persetujuan (Approver) belum diatur oleh HRD. Silakan hubungi admin.", 403);
        }

        try {
            return DB::transaction(function () use ($request, $imageService, $currentUser, $approvalLines) {

                $photoPath = null;

                if ($request->hasFile('photo')) {
                    $photoPath = $imageService->compressAndUpload(
                        $request->file('photo'),
                        'attendance_submissions'
                    );
                }

                // 2. SIMPAN PENGAJUAN BESERTA TOTAL TAHAPANNYA
                $submission = AttendanceSubmission::create([
                    'user_id'         => $currentUser->id,
                    'shift_id'        => $request->shift_id,
                    'attendance_type' => $request->attendance_type,
                    'date'            => $request->date,
                    'time'            => $request->time,
                    'reason'          => $request->reason,
                    'file'            => $photoPath,
                    'status'          => AttendanceSubmission::STATUS_PENDING,
                    'current_step'    => 1, // Set mulai dari tahap 1
                    'total_steps'     => $approvalLines->count() // Set total tahap sesuai jumlah approver
                ]);

                // 3. BUAT ANTREAN PERSETUJUAN (SIMPAN KE TABEL request_approvals)
                foreach ($approvalLines as $line) {
                    $submission->approvalSteps()->create([
                        'approver_id' => $line->approver_id,
                        'step'        => $line->step,
                        'status'      => 'pending'
                    ]);
                }

                // 4. KIRIM NOTIFIKASI HANYA KE APPROVER TAHAP 1 (Bukan ke semua admin)
                $firstApprover = $submission->approvalSteps()->where('step', 1)->first()->approver ?? null;

                if ($firstApprover) {
                    $submission->load('user.employee');
                    $photoUrl = $submission->user->employee->photo ? Storage::url($submission->user->employee->photo) : null;

                    $title   = 'Pengajuan Koreksi Absensi Baru';
                    $message = "{$submission->user->name} mengajukan koreksi absen untuk tanggal {$submission->date}. Menunggu persetujuan Anda.";
                    $link    = "/attendance/approvals/detail/{$submission->id}?source_type=request";

                    $firstApprover->notify(new SubmissionNotification($title, $message, $link, 'attendance', $photoUrl));
                }

                return $this->respondSuccess($submission, 'Pengajuan koreksi berhasil dikirim. Menunggu persetujuan.');
            });
        } catch (\Throwable $th) {
            return $this->respondError($th->getMessage());
        }
    }

    public function myRequest(Request $request)
    {
        $userId = Auth::id() ?? $request->user_id ?? 1;

        $submissions = AttendanceSubmission::where('user_id', $userId)
            ->with(['shift', 'user'])
            ->latest()
            ->get()
            ->map(function ($item) {

                $avatarUrl = null;
                if ($item->user && $item->user->avatar) {
                    if (str_starts_with($item->user->avatar, 'http')) {
                        $avatarUrl = $item->user->avatar;
                    } else {
                        $avatarUrl = Storage::url($item->user->avatar);
                    }
                }

                $attachmentUrl = $item->file ? Storage::url($item->file) : null;


                return [
                    'id'                => $item->id,
                    'user_name'         => $item->user->name ?? 'Unknown',
                    'user_avatar'       => $avatarUrl,
                    'date'              => $item->date,
                    'shift_name'        => $item->shift->name ?? '-',
                    'type'              => 'Pengajuan Manual',
                    'attendance_type'   => $item->attendance_type,
                    'time'              => $item->time,
                    'reason'            => $item->reason,
                    'attachment'        => $attachmentUrl,

                    'status_label'      => ucfirst($item->status),

                    'source_type'       => 'request',
                    'status_code'       => 'Request',
                ];
            });

        if ($submissions->isEmpty()) {
            return $this->respondSuccess([], 'Belum ada riwayat pengajuan.');
        }

        return $this->respondSuccess($submissions, 'Riwayat pengajuan koreksi absen saya.');
    }

    public function approveHRD($id)
    {
        $submission = AttendanceSubmission::with('user')->find($id);

        if (!$submission) return $this->respondError('Data tidak ditemukan');

        if ($submission->status != AttendanceSubmission::STATUS_PENDING) {
            return $this->respondError('Pengajuan ini tidak bisa diapprove HRD (Status: ' . $submission->status . ')');
        }

        $notifTargetUser = $submission->user;
        $notifLink = "/attendance/approvals/detail/{$submission->id}?source_type=request";
        $notifType = 'attendance';

        $currentStep = $submission->approvalSteps()->where('step', 1)->where('status', 'pending')->first();
        if (!$currentStep || !\App\Services\ApprovalDelegationService::canPerformAction($currentStep->approver_id, $submission->user_id)) {
            return $this->respondError("Anda tidak memiliki akses delegasi untuk menyetujui tahap ini.", 403);
        }

        $user = Auth::user();

        try {
            return DB::transaction(function () use ($submission, $notifTargetUser, $notifLink, $notifType) {
                $adminId = Auth::id() ?? 1;

                $submission->update([
                    'status'        => AttendanceSubmission::STATUS_APPROVED_HRD,
                    'approved_1_by' => $adminId,
                    'approved_1_at' => now(),
                ]);

                $currentStep->update([
                'status'       => 'approved',
                'action_at'    => now(),
                'processed_by' => $user->id
                ]);

                $nextStep = $submission->approvalSteps()->where('step', 2)->first();
                if ($nextStep && $nextStep->approver) {
                    \App\Services\ApprovalDelegationService::sendParallelNotification(
                        $nextStep->approver,
                        new SubmissionNotification(
                            'Pengajuan Koreksi Absensi',
                            'untuk tanggal ' . Carbon::parse($submission->date)->isoFormat('D MMMM Y') . " oleh {$submission->user->name} membutuhkan persetujuan Anda.",
                            "/attendance/approvals/detail/{$submission->id}?source_type=request",
                            'attendance'
                        )
                    );
                }

                return $this->respondSuccess($submission, 'Persetujuan Tahap 1 (HRD) Berhasil. Menunggu Direktur.');
            });
        } catch (\Throwable $th) {
            return $this->respondError($th->getMessage());
        }
    }

    public function approveDirector($id)
    {
        $submission = AttendanceSubmission::find($id);

        if (!$submission) return $this->respondError('Data tidak ditemukan');

        if ($submission->status != AttendanceSubmission::STATUS_APPROVED_HRD) {
            return $this->respondError('Pengajuan belum disetujui HRD atau sudah selesai.');
        }

        $notifTargetUser = $submission->user;
        $notifLink = "/attendance/approvals/detail/{$submission->id}?source_type=request";
        $notifType = 'attendance';

        $currentStep = $submission->approvalSteps()->where('step', 2)->where('status', 'pending')->first();
        if (!$currentStep || !\App\Services\ApprovalDelegationService::canPerformAction($currentStep->approver_id, $submission->user_id)) {
            return $this->respondError("Anda tidak memiliki akses delegasi untuk menyetujui tahap ini.", 403);
        }

        $user = Auth::user();

        try {
            return DB::transaction(function () use ($submission, $notifTargetUser, $notifLink, $notifType) {
                $adminId = Auth::id() ?? 1;

                $this->createAttendanceFromSubmission($submission, $adminId);

                $submission->update([
                    'status'        => AttendanceSubmission::STATUS_APPROVED,
                    'approved_2_by' => $adminId,
                    'approved_2_at' => now(),
                ]);

                $currentStep->update([
                    'status'       => 'approved',
                    'action_at'    => now(),
                    'processed_by' => $user->id
                ]);

                if ($notifTargetUser && $notifTargetUser->id !== $adminId) {
                    $notifTitle = 'Pengajuan Koreksi Absensi Disetujui';
                    $notifMessage = "Pengajuan koreksi absensi Anda untuk tanggal {$submission->date} telah disetujui oleh {$user->name}.";
                    $notifTargetUser->notify(new SubmissionNotification(
                        $notifTitle,
                        $notifMessage,
                        $notifLink,
                        'approved'
                    ));
                }

                return $this->respondSuccess($submission, 'Persetujuan Tahap 2 (Direktur) Berhasil. Data Absensi Resmi Dibuat.');
            });
        } catch (\Throwable $th) {
            return $this->respondError($th->getMessage());
        }
    }
    public function pendingRequests()
    {
        $pending = AttendanceSubmission::where('status', 'pending')
            ->with('user')
            ->latest()
            ->get();
        $approved = AttendanceSubmission::where('status', 'approved')
            ->with('user')
            ->latest()
            ->get();

        return $this->respondSuccess([
            'pending' => $pending,
            'approved' => $approved
        ], 'List pengajuan Koreksi Absensi');
    }

    public function reject($id)
    {
        $submission = AttendanceSubmission::find($id);

        if (!$submission) return $this->respondError('Data tidak ditemukan');

        if (in_array($submission->status, [AttendanceSubmission::STATUS_APPROVED, AttendanceSubmission::STATUS_REJECTED])) {
            return $this->respondError('Tidak bisa menolak pengajuan yang sudah selesai');
        }

        $notifTargetUser = $submission->user;
        $notifLink = "/attendance/approvals/detail/{$submission->id}?source_type=request";
        $notifType = 'attendance';

        $user = auth()->user();

        $currentStep = $submission->approvalSteps()
        ->where('step', $submission->current_step)
        ->where('status', 'pending')
        ->first();

        if (!$currentStep || !\App\Services\ApprovalDelegationService::canPerformAction($currentStep->approver_id, $submission->user_id)) {
            return $this->respondError("Anda tidak memiliki akses untuk menolak pengajuan ini.", 403);
        }

        $user = Auth::user();

        try {
            return DB::transaction(function () use ($submission, $notifTargetUser, $notifLink, $notifType) {
                $rejecterId = Auth::id() ?? 1;

                $submission->update([
                    'status' => AttendanceSubmission::STATUS_REJECTED,
                    'approved_1_by' => $submission->approved_1_by ?? $rejecterId,
                    'approved_1_at' => $submission->approved_1_at ?? now(),
                ]);

                if ($notifTargetUser) {
                    $notifTitle = 'Pengajuan Koreksi Absensi Ditolak';
                    $notifMessage = "Pengajuan koreksi absensi Anda untuk tanggal {$submission->date} telah ditolak oleh {$user->name}.";
                    $notifTargetUser->notify(new SubmissionNotification(
                        $notifTitle,
                        $notifMessage,
                        $notifLink,
                        'rejected'
                    ));
                }

                return $this->respondSuccess($submission, 'Pengajuan berhasil ditolak.');
            });
        } catch (\Throwable $th) {
            return $this->respondError($th->getMessage());
        }
    }


    private function createAttendanceFromSubmission($submission, $adminId)
    {
        $shift = Shift::find($submission->shift_id);
        if (!$shift) throw new \Exception("Shift tidak valid.");

        if ($submission->attendance_type == 'check_in') {

            $shiftTime   = Carbon::parse($submission->date . ' ' . $shift->start_time);
            $maxTime     = $shiftTime->addMinutes($shift->tolerance_come_too_late);
            $requestTime = Carbon::parse($submission->date . ' ' . $submission->time);

            $statusAbsensi = $requestTime->greaterThan($maxTime)
                ? Attendance::STATUS_LATE
                : Attendance::STATUS_PRESENT;

            $existing = Attendance::where('user_id', $submission->user_id)
                ->where('date', $submission->date)->first();

            $updateData = [
                'user_id'                => $submission->user_id,
                'shift_id'               => $submission->shift_id,
                'attendance_location_id' => null,
                'date'                   => $submission->date,
                'status'                 => $statusAbsensi,
                'type'                   => 'Manual',
                'is_location_valid'      => true,
                'approved_1_by'          => $submission->approved_1_by,
                'approved_2_by'          => $adminId,
                'approved_1_at'          => $submission->approved_1_at,
                'approved_2_at'          => now(),
            ];

            if ($existing) {
                $existing->update($updateData);
                $attendance = $existing;
            } else {
                $attendance = Attendance::create($updateData);
            }
            $attendanceId = $attendance->id;

            $existingLog = AttendanceLog::where('attendance_id', $attendanceId)
                ->where('attendance_type', 'check_in')
                ->first();

            $logData = [
                'attendance_id'   => $attendanceId,
                'attendance_type' => $submission->attendance_type,
                'time'            => $submission->time,
                'photo'           => $submission->file,
                'device_info'     => 'Manual Request (Corrected)',
                'note'            => 'Koreksi Jam Masuk: ' . $submission->reason,
                'lat' => null,
                'lng' => null,
            ];

            if ($existingLog) {
                $existingLog->update($logData);
            } else {
                AttendanceLog::create($logData);
            }
        } else {
            $attendance = Attendance::where('user_id', $submission->user_id)->where('date', $submission->date)->first();
            if (!$attendance) throw new \Exception("Gagal Finalisasi: User belum Check-In.");

            $currentStatus = $attendance->status;
            $checkOutTime  = Carbon::now();
            $shiftEndTime  = Carbon::parse($shift->end_time);

            $minOutTime = $shiftEndTime->copy()->subMinutes($shift->go_home_early);

            if ($checkOutTime->lessThan($minOutTime)) {
                if ($currentStatus == Attendance::STATUS_PRESENT) {
                    $currentStatus = Attendance::STATUS_EARLY_OUT;
                }
            }

            $attendance->update([
                'approved_2_by' => $adminId,
                'approved_2_at' => now(),
                'status'        => $currentStatus
            ]);

            AttendanceLog::create([
                'attendance_id'   => $attendance->id,
                'attendance_type' => $submission->attendance_type,
                'time'            => $submission->time,
                'photo'           => $submission->file,
                'device_info'     => 'Manual Request (Approved)',
                'note'            => 'Koreksi Pulang: ' . $submission->reason,
                'lat' => null,
                'lng' => null,
            ]);
        }
    }
}
