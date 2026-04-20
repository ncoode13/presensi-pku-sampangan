<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller as ApiController;
use App\Http\Resources\LeaveSubmissionResource;
use App\Models\Attendance;
use App\Models\LeaveBalance;
use App\Models\LeaveSubmission;
use App\Models\RequestApproval;
use App\Models\TimeOff;
use App\Services\ImageService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Notifications\SubmissionNotification;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class LeaveSubmissionController extends ApiController
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $userRole = strtolower($user->role);

        $scope = $request->query('scope', 'my');
        $status = $request->query('status', 'all');
        $search = $request->query('search');
        $date = $request->query('date');
        $limit = $request->query('limit', 10);

        $query = LeaveSubmission::with(['user.employee', 'leave', 'approvalSteps.approver']);
        $table = 'time_off_requests';

        if ($scope === 'my') {
            $query->where($table . '.user_id', $user->id);
        } elseif ($scope === 'approval') {
            $query->where($table . '.user_id', '!=', $user->id);
            if ($userRole === 'superadmin') {
                if ($status === 'pending') {
                    $query->where($table . '.status', 'pending');
                }
            } elseif ($userRole === 'admin') {
                $query->whereHas('approvalSteps', function ($q) use ($user, $table, $status) {
                    $q->where(function ($sub) use ($user) {
                        $sub->where('approver_id', $user->id) // approver asli
                            ->orWhereHas('approver.approvalDelegation', function ($del) use ($user) { // penerima delegasi
                                $del->where('delegate_to_id', $user->id)->active();
                            });
                    });

                    if ($status === 'pending') {
                        $q->where('status', 'pending')
                            ->whereColumn('step', "$table.current_step");
                    }
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($status !== 'all' && $status !== 'pending') {
            $query->whereRaw("LOWER($table.status) = ?", [strtolower($status)]);
        }

        if (!empty($search)) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%");
            });
        }

        if (!empty($date)) {
            $query->whereDate($table . '.start_date', '<=', $date)
                ->whereDate($table . '.end_date', '>=', $date);
        }

        $leaves = $query->latest($table . '.created_at')->paginate($limit);

        return LeaveSubmissionResource::collection($leaves);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'time_off_id' => 'required|exists:time_offs,id',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'reason'      => 'required|string',
            'file'        => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048'
        ]);

        if ($validator->fails()) {
            return $this->respondError($validator->errors()->first(), 422);
        }

        $user = Auth::user();

        $approvalLines = $user->approvalLines;
        if ($approvalLines->isEmpty()) {
            return $this->respondError("Aturan persetujuan (Approver) belum diatur oleh HRD. Silakan hubungi admin.", 403);
        }

        $start = Carbon::parse($request->start_date);
        $end   = Carbon::parse($request->end_date);
        $daysRequested = $start->diffInDays($end) + 1;

        $timeOff = TimeOff::findOrFail($request->time_off_id);

        if ($timeOff->is_deduct_quota) {
            $currentYear = now()->year;
            $balance = LeaveBalance::firstOrCreate(
                ['user_id' => $user->id, 'year' => $currentYear],
                ['total_quota' => 12, 'used_quota' => 0]
            );

            $sisaCuti = $balance->total_quota - $balance->used_quota;

            if ($daysRequested > $sisaCuti) {
                return $this->respondError("Sisa cuti tahunan tidak mencukupi. Sisa: $sisaCuti hari, Diajukan: $daysRequested hari.", 422);
            }
        }

        try {
            DB::beginTransaction();

            $filePath = null;
            if ($request->hasFile('file')) {
                $imageService = new ImageService();
                $filePath = $imageService->compressAndUpload($request->file('file'), 'leaves');
            }

            $submission = LeaveSubmission::create([
                'user_id'      => $user->id,
                'time_off_id'  => $request->time_off_id,
                'start_date'   => $request->start_date,
                'end_date'     => $request->end_date,
                'reason'       => $request->reason,
                'file'         => $filePath,
                'status'       => 'pending',
                'current_step' => 1,
                'total_steps'  => $approvalLines->count()
            ]);

            foreach ($approvalLines as $line) {
                $submission->approvalSteps()->create([
                    'approver_id' => $line->approver_id,
                    'step'        => $line->step,
                    'status'      => 'pending'
                ]);
            }

            $firstApprover = $submission->approvalSteps()->where('step', 1)->first()->approver;

            if ($firstApprover) {
                $submission->load('user.employee');
                $photoUrl = $submission->user->employee->photo ? Storage::url($submission->user->employee->photo) : null;
                $title   = 'Pengajuan Cuti Baru (Tahap 1)';
                $message = "{$submission->user->name} mengajukan cuti {$timeOff->name}. Menunggu persetujuan Anda.";
                $link    = "/leave/approvals/detail/{$submission->id}";

                \App\Services\ApprovalDelegationService::sendParallelNotification(
                    $firstApprover,
                    new SubmissionNotification($title, $message, $link, 'leave', $photoUrl)
                );
            }

            DB::commit();
            return $this->respondSuccess(new LeaveSubmissionResource($submission), 'Pengajuan cuti berhasil dikirim.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->respondError('Gagal menyimpan data cuti: ' . $th->getMessage(), 500);
        }
    }

    public function action(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'reason' => 'required_if:action,reject|nullable|string'
        ]);

        $leave = LeaveSubmission::findOrFail($id);
        if ($leave->user_id === auth()->id()) {
            return $this->respondError("Anda tidak diperbolehkan menyetujui pengajuan Anda sendiri.", 403);
        }

        $user = auth()->user();
        $action = $request->action;
        $isSuperadmin = strtolower($user->role) === 'superadmin';

        if ($leave->status !== 'pending') {
            return $this->respondError("Pengajuan ini sudah berstatus: {$leave->status}", 400);
        }

        $currentApprovalStep = $leave->approvalSteps()
            ->where('step', $leave->current_step)
            ->where('status', 'pending')
            ->first();

        // Tolak jika BUKAN superadmin DAN bukan gilirannya
        if (!$currentApprovalStep && !$isSuperadmin) {
            return $this->respondError("Anda tidak memiliki akses atau belum giliran Anda untuk menyetujui dokumen ini.", 403);
        }

        // Cek otorisasi delegasi jika BUKAN superadmin
        if (!$isSuperadmin && !\App\Services\ApprovalDelegationService::canPerformAction($currentApprovalStep->approver_id)) {
            return $this->respondError("Anda tidak memiliki akses untuk menyetujui tahap ini.", 403);
        }

        $notifTargetUser = $leave->user;
        $notifLink = "/leave/approvals/detail/{$leave->id}";

        try {
            DB::beginTransaction();

            // ==========================================
            // LOGIKA REJECT (TOLAK)
            // ==========================================
            if ($action === 'reject') {
                if ($isSuperadmin) {
                    // GABUNGAN: Logika Superadmin (semua step) + Logika Delegasi Anda (processed_by)
                    $leave->approvalSteps()->where('status', 'pending')->update([
                        'status'       => 'rejected',
                        // 'approver_id'  => $user->id,
                        'processed_by' => $user->id,
                        'note'         => 'Force rejected by Superadmin: ' . $request->reason,
                        'action_at'    => now()
                    ]);
                } else {
                    // GABUNGAN: Logika Normal + Logika Delegasi Anda (processed_by)
                    $currentApprovalStep->update([
                        'status'       => 'rejected',
                        'note'         => $request->reason,
                        'processed_by' => $user->id,
                        'action_at'    => now()
                    ]);
                }

                $leave->update([
                    'status'         => 'rejected',
                    'rejection_note' => "Ditolak oleh {$user->name} " . ($isSuperadmin ? "(Bypass Superadmin)" : "pada tahap {$leave->current_step}") . ". Alasan: {$request->reason}"
                ]);

                if ($notifTargetUser) {
                    $notifTargetUser->notify(new SubmissionNotification(
                        'Pengajuan Cuti Ditolak',
                        "Pengajuan cuti Anda telah ditolak oleh {$user->name}. Alasan: {$request->reason}",
                        $notifLink,
                        'rejected'
                    ));
                }

                DB::commit();
                return $this->respondSuccess(null, 'Pengajuan berhasil ditolak.');
            }

            // ==========================================
            // LOGIKA APPROVE (TERIMA)
            // ==========================================
            if ($action === 'approve') {
                // Status ini final jika dia Superadmin ATAU jika dia approver di tahap terakhir
                $isFinalApproval = $isSuperadmin || ($leave->current_step == $leave->total_steps);

                if ($isSuperadmin) {
                    // GABUNGAN: Logika Superadmin (semua step) + Logika Delegasi Anda (processed_by)
                    $leave->approvalSteps()->where('status', 'pending')->update([
                        'status'       => 'approved',
                        // 'approver_id'  => $user->id,
                        'processed_by' => $user->id,
                        'note'         => 'Force approved by Superadmin',
                        'action_at'    => now()
                    ]);
                    $leave->update(['current_step' => $leave->total_steps]);
                } else {
                    // GABUNGAN: Logika Normal + Logika Delegasi Anda (processed_by)
                    $currentApprovalStep->update([
                        'status'       => 'approved',
                        'processed_by' => $user->id,
                        'action_at'    => now()
                    ]);
                }

                // --- JIKA INI ADALAH ACC FINAL ---
                if ($isFinalApproval) {
                    $leave->update(['status' => 'approved']);

                    $leave->load('leave');
                    if ($leave->leave && $leave->leave->is_deduct_quota) {
                        $start = Carbon::parse($leave->start_date);
                        $end   = Carbon::parse($leave->end_date);
                        $daysTaken = $start->diffInDays($end) + 1;

                        $balance = LeaveBalance::firstOrCreate(
                            ['user_id' => $leave->user_id, 'year' => $start->year],
                            ['total_quota' => 12, 'used_quota' => 0]
                        );
                        $balance->used_quota += $daysTaken;
                        $balance->save();
                    }

                    $this->generateAttendanceFromLeave($leave, $user->id);

                    if ($notifTargetUser) {
                        $pesanTambahan = ($leave->leave && $leave->leave->is_deduct_quota) ? "dan saldo cuti telah dipotong." : "(Tidak memotong saldo cuti tahunan).";
                        $notifTargetUser->notify(new SubmissionNotification(
                            'Pengajuan Cuti Disetujui Final',
                            "Pengajuan cuti Anda telah disetujui sepenuhnya " . ($isSuperadmin ? "(di-ACC langsung oleh Superadmin) " : "") . $pesanTambahan,
                            $notifLink,
                            'approved'
                        ));
                    }

                    DB::commit();
                    return $this->respondSuccess(null, 'Disetujui Final. Pengajuan selesai diproses.');
                }
                // --- JIKA BELUM FINAL (Lanjut ke Atasan Berikutnya) ---
                else {
                    $leave->update(['current_step' => $leave->current_step + 1]);

                    $nextStepData = $leave->approvalSteps()->where('step', $leave->current_step)->first();
                    $nextStepApprover = $nextStepData ? $nextStepData->approver : null;

                    if ($nextStepApprover) {
                        $leave->load('user.employee');
                        $photoUrl = $leave->user->employee->photo ? Storage::url($leave->user->employee->photo) : null;
                        $title = 'Butuh Persetujuan: Cuti';
                        $message = "{$user->name} telah menyetujui tahap sebelumnya. Mohon tinjau pengajuan dari {$leave->user->name}.";

                        // Fitur Notifikasi Paralel Anda
                        \App\Services\ApprovalDelegationService::sendParallelNotification(
                            $nextStepApprover,
                            new SubmissionNotification($title, $message, $notifLink, 'leave', $photoUrl)
                        );
                    }

                    DB::commit();
                    return $this->respondSuccess(null, "Disetujui. Melanjutkan ke tahap " . $leave->current_step);
                }
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->respondError('Gagal memproses approval: ' . $e->getMessage(), 500);
        }
    }

    private function generateAttendanceFromLeave($leaveSubmission, $adminId)
    {
        $period = CarbonPeriod::create($leaveSubmission->start_date, $leaveSubmission->end_date);
        $user = \App\Models\User::with('employee')->find($leaveSubmission->user_id);
        $shiftId = $user->employee?->shift_id ?? 1;

        foreach ($period as $date) {
            Attendance::updateOrCreate(
                [
                    'user_id' => $leaveSubmission->user_id,
                    'date'    => $date->format('Y-m-d'),
                ],
                [
                    'shift_id'               => $shiftId,
                    'attendance_location_id' => null,
                    'status'                 => 4,
                    'is_location_valid'      => true,
                    'approved_1_by'          => $adminId,
                    'approved_1_at'          => now(),
                ]
            );
        }
    }

    public function show($id)
    {
        try {
            $submission = LeaveSubmission::with(['user', 'leave', 'approvalSteps.approver'])->find($id);

            if (!$submission) {
                return $this->respondError('Data pengajuan cuti tidak ditemukan', 404);
            }

            // 1. Ambil data user yang sedang login
            $user = auth()->user();
            $isSuperadmin = strtolower($user->role) === 'superadmin';

            $canApprove = false;

            // 2. Cek apakah status pengajuan masih pending
            if ($submission->status === 'pending') {

                // 3. Jika dia Superadmin, langsung beri akses TRUE (Bypass)
                if ($isSuperadmin) {
                    $canApprove = true;
                } else {
                    // 4. Jika bukan superadmin, cek apakah ini giliran dia / delegasinya
                    $currentStepData = $submission->approvalSteps()
                        ->where('step', $submission->current_step)
                        ->where('status', 'pending')
                        ->first();

                    if ($currentStepData) {
                        $canApprove = \App\Services\ApprovalDelegationService::canPerformAction(
                            $currentStepData->approver_id,
                            $submission->user_id
                        );
                    }
                }
            }

            $submission->can_approve = $canApprove;

            return $this->respondSuccess(new \App\Http\Resources\LeaveSubmissionResource($submission));
        } catch (\Throwable $th) {
            return $this->respondError('Gagal mengambil detail cuti: ' . $th->getMessage());
        }
    }

    public function getMyBalance()
    {
        $user = auth()->user();
        $currentYear = now()->year;

        $balance = \App\Models\LeaveBalance::firstOrCreate(
            ['user_id' => $user->id, 'year' => $currentYear],
            ['total_quota' => 12, 'used_quota' => 0]
        );

        $sisa = $balance->total_quota - $balance->used_quota;

        return $this->respondSuccess([
            'year'        => $currentYear,
            'total_quota' => $balance->total_quota,
            'used_quota'  => $balance->used_quota,
            'remaining'   => $sisa
        ], 'Informasi saldo cuti tahunan.');
    }
}
