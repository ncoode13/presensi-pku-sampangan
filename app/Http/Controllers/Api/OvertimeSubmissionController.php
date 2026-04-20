<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller as ApiController;
use App\Http\Resources\OvertimeSubmissionResource;
use App\Models\OvertimeSubmission;
use App\Models\RequestApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\ImageService;
use App\Notifications\SubmissionNotification;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class OvertimeSubmissionController extends ApiController
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $userRole = strtolower($user->role);

        $scope = $request->query('scope', 'my');
        $status = $request->query('status', 'all');
        $limit = $request->query('limit', 10);
        $search = $request->query('search');
        $date = $request->query('date');

        $query = OvertimeSubmission::with(['user.employee', 'shift', 'approvalSteps.approver']);
        $table = 'overtime_requests';

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

        if ($status && $status !== 'all' && $status !== 'pending') {
            $query->where($table . '.status', $status);
        }

        if ($date) {
            $query->whereDate($table . '.date', $date);
        }

        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%");
            });
        }

        $data = $query->latest($table . '.created_at')->paginate($limit);
        return OvertimeSubmissionResource::collection($data);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date'                 => 'required|date',
            'shift_id'             => 'required|exists:shifts,id',
            'duration_before'      => 'nullable|numeric|min:0',
            'rest_duration_before' => 'nullable|numeric|min:0',
            'duration_after'       => 'nullable|numeric|min:0',
            'rest_duration_after'  => 'nullable|numeric|min:0',
            'reason'               => 'required|string',
            'file'                 => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->respondError($validator->errors()->first(), 422);
        }

        $user = Auth::user();

        $approvalLines = $user->approvalLines;
        if ($approvalLines->isEmpty()) {
            return $this->respondError("Aturan persetujuan (Approver) belum diatur oleh HRD. Silakan hubungi admin.", 403);
        }

        $totalDuration = ($request->duration_before ?? 0) + ($request->duration_after ?? 0);
        if ($totalDuration <= 0) {
            return $this->respondError('Durasi lembur tidak boleh kosong.', 422);
        }

        try {
            DB::beginTransaction();

            $filePath = null;
            if ($request->hasFile('file')) {
                $imageService = new ImageService();
                $filePath = $imageService->compressAndUpload($request->file('file'), 'overtimes');
            }

            $submission = OvertimeSubmission::create([
                'user_id'              => $user->id,
                'status'               => 'pending',
                'file'                 => $filePath,
                'date'                 => $request->date,
                'shift_id'             => $request->shift_id,
                'duration_before'      => $request->duration_before ?? 0,
                'rest_duration_before' => $request->rest_duration_before ?? 0,
                'duration_after'       => $request->duration_after ?? 0,
                'rest_duration_after'  => $request->rest_duration_after ?? 0,
                'reason'               => $request->reason,
                'current_step'         => 1,
                'total_steps'          => $approvalLines->count()
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
                $title   = 'Pengajuan Lembur Baru (Tahap 1)';
                $message = "{$submission->user->name} mengajukan lembur untuk tanggal {$submission->date}.";
                $link    = "/overtime/approvals/detail/{$submission->id}";

                \App\Services\ApprovalDelegationService::sendParallelNotification(
                    $firstApprover,
                    new SubmissionNotification($title, $message, $link, 'overtime', $photoUrl)
                );
            }

            DB::commit();
            return $this->respondSuccess(new OvertimeSubmissionResource($submission), 'Pengajuan lembur berhasil dikirim.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->respondError('Gagal menyimpan data lembur: ' . $th->getMessage(), 500);
        }
    }

    public function show($id)
    {
        $submission = OvertimeSubmission::with(['user.employee', 'shift', 'approvalSteps.approver'])->find($id);
        if (!$submission) return $this->respondError('Data pengajuan lembur tidak ditemukan', 404);

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

        return $this->respondSuccess(new OvertimeSubmissionResource($submission));
    }

    public function action(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'reason' => 'required_if:action,reject|nullable|string'
        ]);

        $overtime = OvertimeSubmission::findOrFail($id);
        if ($overtime->user_id === auth()->id()) {
            return $this->respondError("Anda tidak diperbolehkan menyetujui pengajuan Anda sendiri.", 403);
        }
        $user = auth()->user();
        $action = $request->action;

        // 1. Cek apakah user adalah superadmin
        $isSuperadmin = strtolower($user->role) === 'superadmin';

        if ($overtime->status !== 'pending') {
            return $this->respondError("Pengajuan ini sudah berstatus: {$overtime->status}", 400);
        }

        // Cari step approval saat ini khusus untuk user biasa
        $currentApprovalStep = $overtime->approvalSteps()
            ->where('step', $overtime->current_step)
            ->where('status', 'pending')
            ->first();

        // 2. Akses Ditolak JIKA BUKAN SUPERADMIN DAN BUKAN GILIRANNYA
        if (!$currentApprovalStep && !$isSuperadmin) {
            return $this->respondError("Anda tidak memiliki akses atau belum giliran Anda untuk menyetujui dokumen ini.", 403);
        }

        // Cek Otorisasi Delegasi jika BUKAN Superadmin
        if (!$isSuperadmin && !\App\Services\ApprovalDelegationService::canPerformAction($currentApprovalStep->approver_id)) {
            return $this->respondError("Anda tidak memiliki akses untuk menyetujui tahap ini.", 403);
        }

        $notifTargetUser = $overtime->user;
        $notifLink = "/overtime/approvals/detail/{$overtime->id}";

        try {
            DB::beginTransaction();

            // ==========================================
            // LOGIKA REJECT (TOLAK)
            // ==========================================
            if ($action === 'reject') {
                if ($isSuperadmin) {
                    // Jika Superadmin, tolak semua step yang masih pending
                    $overtime->approvalSteps()->where('status', 'pending')->update([
                        'status'       => 'rejected',
                        // 'approver_id'  => $user->id, // Timpa nama atasan dgn Superadmin
                        'processed_by' => $user->id, // Audit trail delegasi Anda
                        'note'         => 'Force rejected by Superadmin: ' . $request->reason,
                        'action_at'    => now()
                    ]);
                } else {
                    // Normal reject untuk atasan biasa / delegasi
                    $currentApprovalStep->update([
                        'status'       => 'rejected',
                        'note'         => $request->reason,
                        'processed_by' => $user->id, // Audit trail delegasi Anda
                        'action_at'    => now()
                    ]);
                }

                $overtime->update([
                    'status'         => 'rejected',
                    'rejection_note' => "Ditolak oleh {$user->name} " . ($isSuperadmin ? "(Bypass Superadmin)" : "pada tahap {$overtime->current_step}") . ". Alasan: {$request->reason}"
                ]);

                if ($notifTargetUser) {
                    $notifTargetUser->notify(new SubmissionNotification(
                        'Pengajuan Lembur Ditolak',
                        "Pengajuan lembur Anda untuk tgl {$overtime->date} telah ditolak oleh {$user->name}. Alasan: {$request->reason}",
                        $notifLink,
                        'rejected'
                    ));
                }

                DB::commit();
                return $this->respondSuccess(null, 'Pengajuan Lembur berhasil ditolak.');
            }

            // ==========================================
            // LOGIKA APPROVE (TERIMA)
            // ==========================================
            if ($action === 'approve') {
                // Status ini final jika dia Superadmin ATAU jika dia approver di tahap terakhir
                $isFinalApproval = $isSuperadmin || ($overtime->current_step == $overtime->total_steps);

                if ($isSuperadmin) {
                    // Jika Superadmin, setujui semua step yang masih pending secara paksa
                    $overtime->approvalSteps()->where('status', 'pending')->update([
                        'status'       => 'approved',
                        // 'approver_id'  => $user->id, // Timpa nama atasan dgn Superadmin
                        'processed_by' => $user->id, // Audit trail delegasi Anda
                        'note'         => 'Force approved by Superadmin',
                        'action_at'    => now()
                    ]);
                    $overtime->update(['current_step' => $overtime->total_steps]);
                } else {
                    // Normal approve untuk atasan biasa / delegasi
                    $currentApprovalStep->update([
                        'status'       => 'approved',
                        'processed_by' => $user->id, // Audit trail delegasi Anda
                        'action_at'    => now()
                    ]);
                }

                // --- JIKA INI ADALAH ACC FINAL ---
                if ($isFinalApproval) {
                    $overtime->update(['status' => 'approved']);

                    if ($notifTargetUser) {
                        $notifTargetUser->notify(new SubmissionNotification(
                            'Pengajuan Lembur Disetujui Final',
                            "Pengajuan lembur Anda untuk tgl {$overtime->date} telah disetujui sepenuhnya" . ($isSuperadmin ? " (di-ACC langsung oleh Superadmin)." : "."),
                            $notifLink,
                            'approved'
                        ));
                    }

                    DB::commit();
                    return $this->respondSuccess(null, 'Lembur Disetujui Final.');
                }
                // --- JIKA BELUM FINAL (Lanjut ke Atasan Berikutnya) ---
                else {
                    $nextStep = $overtime->current_step + 1;
                    $overtime->update(['current_step' => $nextStep]);

                    $nextStepData = $overtime->approvalSteps()->where('step', $nextStep)->first();
                    $nextStepApprover = $nextStepData ? $nextStepData->approver : null;

                    if ($nextStepApprover) {
                        $overtime->load('user.employee');
                        $photoUrl = $overtime->user->employee->photo ? Storage::url($overtime->user->employee->photo) : null;
                        $title = 'Butuh Persetujuan: Lembur';
                        $message = "{$user->name} telah menyetujui tahap sebelumnya. Mohon tinjau lembur dari {$overtime->user->name}.";

                        // Mengirim notifikasi ke Approver asli DAN delegasinya (Fitur Anda)
                        \App\Services\ApprovalDelegationService::sendParallelNotification(
                            $nextStepApprover,
                            new SubmissionNotification($title, $message, $notifLink, 'overtime', $photoUrl)
                        );
                    }

                    DB::commit();
                    return $this->respondSuccess(null, "Disetujui. Melanjutkan ke tahap " . $overtime->current_step);
                }
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->respondError('Gagal memproses approval lembur: ' . $e->getMessage(), 500);
        }
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

        $overtimes = OvertimeSubmission::with([
            'user.employee.department',
            'user.employee.position',
            'user.employee.job_level',
            'user.employee.employment_status',
        ])
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->where('status', 'approved')
            ->orderBy('date', 'asc')
            ->get();

        $reportData = $overtimes->map(function ($ovt) {
            $employee = $ovt->user->employee ?? null;

            $totalMinutes = ($ovt->duration_before ?? 0) + ($ovt->duration_after ?? 0);
            $durationInHours = $totalMinutes > 0 ? round($totalMinutes / 60, 9) : 0;
            $overtimePaymentHours = $durationInHours;
            $multiplier = $durationInHours;

            return [
                'employee_id'       => $employee->employee_id ?? '-',
                'full_name'         => $ovt->user->name ?? '-',
                'department'        => $employee->department->name ?? '-',
                'job_position'      => $employee->position->name ?? '-',
                'job_level'         => $employee->job_level->name ?? '-',
                'employment_status' => $employee->employment_status->name ?? '-',
                'grade'             => $employee->grade ?? '-',

                'date'              => $ovt->date,
                'overtime_duration' => $durationInHours,

                'overtime_payment'    => $overtimePaymentHours,
                'overtime_multiplier' => $multiplier,
                'overtime_rate'       => 0,
                'total_payment'       => 0,
            ];
        });

        return $this->respondSuccess($reportData);
    }
}
