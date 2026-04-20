<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller as ApiController;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\AttendanceSubmission;
use App\Models\ChangeShiftRequest;
use App\Models\OvertimeSubmission;
use App\Models\RequestApproval;
use App\Models\Shift;
use App\Models\TimeOffRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SubmissionNotification;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ApprovalController extends ApiController
{
    public function liveApproval(Request $request)
    {
        try {
            $currentUser = auth()->user();

            $statusFilter = $request->query('status', 'pending');
            $limit        = $request->query('limit', 10);
            $search       = $request->query('search');
            $date         = $request->query('date');

            $query = Attendance::with(['user.employee', 'shift', 'logs', 'approvalSteps.approver']);

            $isSuperAdmin = in_array(strtolower($currentUser->role), ['superadmin', 'director']);

            $query->where('user_id', '!=', $currentUser->id);

            if ($statusFilter === 'pending') {
                if ($isSuperAdmin) {
                    $query->where('status', Attendance::STATUS_PENDING);
                } else {
                    $query->whereHas('approvalSteps', function ($q) use ($currentUser) {
                        $q->where(function ($sub) use ($currentUser) {
                            $sub->where('approver_id', $currentUser->id)
                                ->orWhereHas('approver.approvalDelegation', function ($del) use ($currentUser) {
                                    $del->where('delegate_to_id', $currentUser->id)->active();
                                });
                        })
                            ->where('status', 'pending')
                            ->whereColumn('step', 'attendances.current_step');
                    });
                }
            } elseif ($statusFilter === 'approved') {
                $query->whereIn('status', [
                    Attendance::STATUS_PRESENT,
                    Attendance::STATUS_LATE,
                    Attendance::STATUS_EARLY_OUT
                ]);
            } elseif ($statusFilter === 'rejected') {
                $query->where('status', Attendance::STATUS_REJECTED);
            }

            if ($date) {
                $query->whereDate('date', $date);
            }

            if ($search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'ILIKE', '%' . $search . '%');
                });
            }

            $paginatedData = $query->latest()->paginate($limit);

            $formattedCollection = $paginatedData->getCollection()->map(function ($item) {
                $logIn = $item->logs->where('attendance_type', 'check_in')->first();

                $statusLabel = 'Pending';
                if ($item->status == Attendance::STATUS_REJECTED) $statusLabel = 'Rejected';
                elseif ($item->status == Attendance::STATUS_PENDING) $statusLabel = 'Pending';
                else $statusLabel = 'Approved';

                $avatarRaw = $item->user->employee->avatar ?? null;
                $avatarUrl = null;

                if ($avatarRaw) {
                    $avatarUrl = str_starts_with($avatarRaw, 'http') ? $avatarRaw : Storage::url($avatarRaw);
                }

                return [
                    'id'               => $item->id,
                    'source_type'      => 'attendance',
                    'user_name'        => $item->user->name ?? 'Unknown',
                    'user_avatar'      => $avatarUrl,
                    'date'             => $item->date,
                    'shift_name'       => $item->shift->name ?? '-',
                    'time'             => $logIn ? Carbon::parse($logIn->time)->format('H:i') : '-',
                    'reason'           => $logIn ? $logIn->note : 'Gagal Validasi Sistem',
                    'status_label'     => $statusLabel,
                    'status_code'      => (int)$item->status,

                    'current_step'     => $item->current_step,
                    'total_steps'      => $item->total_steps,
                    'approval_history' => $item->approvalSteps->map(function ($step) {
                        return [
                            'step'          => $step->step,
                            'approver_id'   => $step->approver_id,
                            'approver_name' => $step->approver->name ?? 'Unknown',
                            'status'        => $step->status,
                            'note'          => $step->note,
                            'action_at'     => $step->action_at ? Carbon::parse($step->action_at)->isoFormat('dddd, D MMM Y | HH:mm') : null,
                        ];
                    }),
                ];
            });

            $paginatedData->setCollection($formattedCollection);

            return response()->json([
                'success' => true,
                'message' => 'Data Live Approval berhasil diambil',
                'data'    => $paginatedData
            ]);
        } catch (\Throwable $th) {
            return $this->respondError($th->getMessage());
        }
    }

    public function manualRequest(Request $request)
    {
        try {
            $currentUser = auth()->user();

            $isPersonalMode = $request->query('mode') === 'personal';
            $defaultStatus  = $isPersonalMode ? 'all' : 'pending';
            $statusFilter   = $request->query('status', $defaultStatus);
            $limit          = $request->query('limit', 10);
            $page           = $request->query('page', 1);
            $search         = $request->query('search');
            $date           = $request->query('date');

            // =========================================================================
            // JIKA TAB "RIWAYAT SAYA" DIBUKA -> GABUNGKAN LIVE & MANUAL
            // =========================================================================
            if ($isPersonalMode) {

                // 1. Ambil Live Attendance
                $liveQuery = Attendance::with(['user.employee', 'shift', 'logs', 'approvalSteps.approver'])
                    ->where('user_id', $currentUser->id);

                if ($statusFilter !== 'all') {
                    if ($statusFilter === 'pending') $liveQuery->where('status', Attendance::STATUS_PENDING);
                    elseif ($statusFilter === 'approved') $liveQuery->whereIn('status', [Attendance::STATUS_PRESENT, Attendance::STATUS_LATE, Attendance::STATUS_EARLY_OUT]);
                    elseif ($statusFilter === 'rejected') $liveQuery->where('status', Attendance::STATUS_REJECTED);
                }
                if ($date) $liveQuery->whereDate('date', $date);

                $liveData = $liveQuery->get()->map(function ($item) {
                    $logIn = $item->logs->where('attendance_type', 'check_in')->first();
                    $statusLabel = 'Pending';
                    if ($item->status == Attendance::STATUS_REJECTED) $statusLabel = 'Rejected';
                    elseif ($item->status == Attendance::STATUS_PENDING) $statusLabel = 'Pending';
                    else $statusLabel = 'Approved';

                    $avatarRaw = $item->user->employee->avatar ?? null;
                    $avatarUrl = $avatarRaw ? (str_starts_with($avatarRaw, 'http') ? $avatarRaw : Storage::url($avatarRaw)) : null;

                    return [
                        'id'               => $item->id,
                        'source_type'      => 'attendance',
                        'user_name'        => $item->user->name ?? 'Unknown',
                        'user_avatar'      => $avatarUrl,
                        'date'             => $item->date,
                        'shift_name'       => $item->shift->name ?? '-',
                        'type'             => 'Absensi Langsung (Live)',
                        'time'             => $logIn ? Carbon::parse($logIn->time)->format('H:i') : '-',
                        'reason'           => $logIn ? $logIn->note : '-',
                        'attachment'       => ($logIn && $logIn->photo) ? Storage::url($logIn->photo) : null,
                        'status_label'     => $statusLabel,
                        'status_code'      => $item->status,
                        'created_at'       => $item->created_at,
                        'current_step'     => $item->current_step,
                        'total_steps'      => $item->total_steps,
                        'approval_history' => $item->approvalSteps->map(function ($step) {
                            return [
                                'step'          => $step->step,
                                'approver_id'   => $step->approver_id,
                                'approver_name' => $step->approver->name ?? 'Unknown',
                                'status'        => $step->status,
                                'note'          => $step->note,
                                'action_at'     => $step->action_at ? Carbon::parse($step->action_at)->isoFormat('dddd, D MMM Y | HH:mm') : null,
                            ];
                        }),
                    ];
                });

                // 2. Ambil Pengajuan Manual
                $manualQuery = AttendanceSubmission::with(['user.employee', 'shift', 'approvalSteps.approver'])
                    ->where('user_id', $currentUser->id);

                if ($statusFilter !== 'all') {
                    $manualQuery->where('status', $statusFilter);
                }
                if ($date) $manualQuery->whereDate('date', $date);

                $manualData = $manualQuery->get()->map(function ($item) {
                    $avatarRaw = $item->user->employee->avatar ?? null;
                    $avatarUrl = $avatarRaw ? (str_starts_with($avatarRaw, 'http') ? $avatarRaw : Storage::url($avatarRaw)) : null;

                    return [
                        'id'               => $item->id,
                        'source_type'      => 'request',
                        'user_name'        => $item->user->name ?? 'Unknown',
                        'user_avatar'      => $avatarUrl,
                        'date'             => $item->date,
                        'shift_name'       => $item->shift->name ?? '-',
                        'type'             => 'Pengajuan Manual (' . $item->attendance_type . ')',
                        'time'             => Carbon::parse($item->time)->format('H:i'),
                        'reason'           => $item->reason,
                        'attachment'       => $item->file ? Storage::url($item->file) : null,
                        'status_label'     => ucfirst($item->status),
                        'status_code'      => $item->status,
                        'created_at'       => $item->created_at,
                        'current_step'     => $item->current_step,
                        'total_steps'      => $item->total_steps,
                        'approval_history' => $item->approvalSteps->map(function ($step) {
                            return [
                                'step'          => $step->step,
                                'approver_id'   => $step->approver_id,
                                'approver_name' => $step->approver->name ?? 'Unknown',
                                'status'        => $step->status,
                                'note'          => $step->note,
                                'action_at'     => $step->action_at ? Carbon::parse($step->action_at)->isoFormat('dddd, D MMM Y | HH:mm') : null,
                            ];
                        }),
                    ];
                });

                // 3. Gabungkan dan Pagination
                $mergedData = $liveData->concat($manualData)->sortByDesc('created_at')->values();
                $total = $mergedData->count();
                $offset = ($page - 1) * $limit;
                $items = $mergedData->slice($offset, $limit)->values();

                $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                    $items,
                    $total,
                    $limit,
                    $page,
                    [
                        'path'  => $request->url(),
                        'query' => $request->query(),
                    ]
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Data Riwayat Saya berhasil diambil',
                    'data'    => $paginator
                ]);
            }

            // =========================================================================
            // JIKA TAB "DAFTAR PERSETUJUAN MANUAL" DIBUKA OLEH ATASAN
            // =========================================================================
            $query = AttendanceSubmission::with(['user.employee', 'shift', 'approvalSteps.approver'])->latest();

            $query->where('user_id', '!=', $currentUser->id);
            $isSuperAdmin = in_array(strtolower($currentUser->role), ['superadmin', 'director']);

            if ($statusFilter === 'pending') {
                if ($isSuperAdmin) {
                    $query->where('status', 'pending');
                } else {
                    $query->whereHas('approvalSteps', function ($q) use ($currentUser) {
                        $q->where(function ($sub) use ($currentUser) {
                            $sub->where('approver_id', $currentUser->id)
                                ->orWhereHas('approver.approvalDelegation', function ($del) use ($currentUser) {
                                    $del->where('delegate_to_id', $currentUser->id)->active();
                                });
                        })
                            ->where('status', 'pending')
                            ->whereColumn('step', 'attendance_requests.current_step');
                    });
                }
            } elseif ($statusFilter !== 'all') {
                $query->where('status', $statusFilter);
            }

            if ($date) $query->whereDate('date', $date);
            if ($search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'ILIKE', '%' . $search . '%');
                });
            }

            $paginatedData = $query->paginate($limit);

            $formattedCollection = $paginatedData->getCollection()->map(function ($item) {
                $statusLabel = ucfirst($item->status);
                $avatarRaw = $item->user->employee->avatar ?? null;
                $avatarUrl = $avatarRaw ? (str_starts_with($avatarRaw, 'http') ? $avatarRaw : Storage::url($avatarRaw)) : null;

                return [
                    'id'               => $item->id,
                    'source_type'      => 'request',
                    'user_name'        => $item->user->name ?? 'Unknown',
                    'user_avatar'      => $avatarUrl,
                    'date'             => $item->date,
                    'shift_name'       => $item->shift->name ?? '-',
                    'type'             => 'Pengajuan Manual (' . $item->attendance_type . ')',
                    'time'             => Carbon::parse($item->time)->format('H:i'),
                    'reason'           => $item->reason,
                    'attachment'       => $item->file ? Storage::url($item->file) : null,
                    'status_label'     => $statusLabel,
                    'status_code'      => $item->status,
                    'current_step'     => $item->current_step,
                    'total_steps'      => $item->total_steps,
                    'approval_history' => $item->approvalSteps->map(function ($step) {
                        return [
                            'step'          => $step->step,
                            'approver_id'   => $step->approver_id,
                            'approver_name' => $step->approver->name ?? 'Unknown',
                            'status'        => $step->status,
                            'note'          => $step->note,
                            'action_at'     => $step->action_at ? Carbon::parse($step->action_at)->isoFormat('dddd, D MMM Y | HH:mm') : null,
                        ];
                    }),
                ];
            });

            $paginatedData->setCollection($formattedCollection);

            return response()->json([
                'success' => true,
                'message' => 'Data Manual Request berhasil diambil',
                'data'    => $paginatedData
            ]);
        } catch (\Throwable $th) {
            return $this->respondError($th->getMessage());
        }
    }

    public function action(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'           => 'required',
            'action'       => 'required|in:approve,reject',
            'source_type'  => 'required|in:attendance,request',
            'reason'       => 'required_if:action,reject|nullable|string',
            'is_represent' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return $this->respondError($validator->errors()->first());
        }

        try {
            DB::beginTransaction();

            $user = auth()->user();
            $action = $request->action;
            $isRepresent = $request->boolean('is_represent'); // Bawaan Anda (opsional jika frontend mengirim ini)

            // 1. Deteksi Superadmin
            $isSuperAdmin = in_array(strtolower($user->role), ['superadmin', 'director']);

            if ($request->source_type === 'attendance') {
                $data = Attendance::with('user')->find($request->id);
                $isRequestData = false;
                $statusPending = Attendance::STATUS_PENDING;
            } else {
                $data = AttendanceSubmission::with('user')->find($request->id);
                $isRequestData = true;
                $statusPending = 'pending';
            }

            if (!$data) throw new \Exception('Data tidak ditemukan');
            if ($data->status != $statusPending) throw new \Exception("Dokumen sudah diproses (Status: {$data->status})");

            // Ambil step tanpa filter approver_id
            $currentApprovalStep = $data->approvalSteps()
                ->where('step', $data->current_step)
                ->where('status', 'pending')
                ->first();

            // 2. Tolak jika BUKAN superadmin dan BUKAN gilirannya
            if (!$currentApprovalStep && !$isSuperAdmin) {
                return $this->respondError("Langkah persetujuan tidak ditemukan atau belum giliran Anda.", 403);
            }

            // 3. Validasi Otorisasi Delegasi JIKA BUKAN Superadmin
            if (!$isSuperAdmin) {
                if (!\App\Services\ApprovalDelegationService::canPerformAction($currentApprovalStep->approver_id, $data->user_id)) {
                    return $this->respondError("Anda tidak memiliki akses untuk menyetujui tahap ini.", 403);
                }
            }

            $notifTargetUser = $data->user;
            $notifLink = "/attendance/approvals/detail/{$data->id}?source_type={$request->source_type}";

            // =====================================
            // LOGIKA TOLAK (REJECT)
            // =====================================
            if ($action === 'reject') {
                if ($isSuperAdmin) {
                    // Bypass Tolak Superadmin
                    $data->approvalSteps()->where('status', 'pending')->update([
                        'status'       => 'rejected',
                        'note'         => 'Force rejected by Superadmin: ' . $request->reason,
                        'action_at'    => now(),
                        'approver_id'  => $user->id,
                        'processed_by' => $user->id
                    ]);
                } else {
                    // Tolak biasa / Delegasi
                    $currentApprovalStep->update([
                        'status'       => 'rejected',
                        'note'         => $request->reason,
                        'action_at'    => now(),
                        'processed_by' => $user->id,
                        'approver_id'  => $isRepresent ? $user->id : $currentApprovalStep->approver_id
                    ]);
                }

                $data->status = $isRequestData ? 'rejected' : Attendance::STATUS_REJECTED;
                $data->rejection_note = "Ditolak oleh {$user->name}. Alasan: {$request->reason}";
                $data->save();

                if (!$isRequestData) {
                    $this->createLog($data, 'check_in', $request->reason, 'Rejected by ' . $user->name);
                }

                if ($notifTargetUser) {
                    $notifTargetUser->notify(new SubmissionNotification(
                        'Pengajuan Absensi Ditolak',
                        "Pengajuan absensi tgl {$data->date} telah ditolak.",
                        $notifLink,
                        'rejected'
                    ));
                }

                DB::commit();
                return $this->respondSuccess(null, 'Pengajuan Absensi berhasil ditolak.');
            }

            // =====================================
            // LOGIKA SETUJUI (APPROVE)
            // =====================================
            if ($action === 'approve') {
                $isFinalApproval = $isSuperAdmin || ($data->current_step == $data->total_steps) || $isRepresent;

                if ($isSuperAdmin) {
                    // Bypass Setujui
                    $data->approvalSteps()->where('status', 'pending')->update([
                        'status'       => 'approved',
                        'action_at'    => now(),
                        // 'approver_id'  => $user->id,
                        'processed_by' => $user->id,
                        'note'         => 'Force approved by Superadmin'
                    ]);
                    $data->current_step = $data->total_steps;
                } else {
                    // Normal Setujui
                    $currentApprovalStep->update([
                        'status'       => 'approved',
                        'action_at'    => now(),
                        'processed_by' => $user->id,
                        'approver_id'  => $isRepresent ? $user->id : $currentApprovalStep->approver_id,
                        'note'         => $isRepresent ? "Diambil alih (Bypass) oleh {$user->name}" : null
                    ]);
                }

                if ($isFinalApproval) {
                    // Bersihkan sisa step jika represent bypass manual (optional legacy logic)
                    if ($isRepresent && $data->current_step < $data->total_steps) {
                        $data->approvalSteps()->where('step', '>', $data->current_step)->delete();
                        $data->current_step = $data->total_steps;
                    }

                    $data->status = $isRequestData ? 'approved' : Attendance::STATUS_PRESENT;

                    if (!$isRequestData) {
                        $data->is_location_valid = true;
                        $this->createLog($data, null, null, "Approved Final by {$user->name}");
                    }
                    $data->save();

                    // EFEK SAMPING: Sinkronisasi Absensi
                    if ($isRequestData) {
                        $this->syncRequestToAttendance($data);
                    }

                    if ($notifTargetUser) {
                        $notifTargetUser->notify(new SubmissionNotification(
                            'Pengajuan Absensi Disetujui',
                            "Pengajuan absensi tgl {$data->date} telah disetujui Final.",
                            $notifLink,
                            'approved'
                        ));
                    }

                    DB::commit();
                    return $this->respondSuccess(null, 'Absensi Disetujui Final & Diperbarui.');
                } else {
                    $data->current_step += 1;
                    $data->save();

                    $data->load('user.employee');
                    $photoUrl = $data->user->employee->photo ? Storage::url($data->user->employee->photo) : null;

                    $nextStepData = $data->approvalSteps()->where('step', $data->current_step)->first();
                    $nextApprover = $nextStepData ? $nextStepData->approver : null;

                    if ($nextApprover) {
                        \App\Services\ApprovalDelegationService::sendParallelNotification(
                            $nextApprover,
                            new SubmissionNotification(
                                'Butuh Persetujuan: Absensi',
                                "{$user->name} telah menyetujui tahap sebelumnya. Mohon tinjau absensi {$data->user->name}.",
                                $notifLink,
                                'attendance',
                                $photoUrl
                            )
                        );
                    }

                    DB::commit();
                    return $this->respondSuccess(null, "Disetujui. Melanjutkan ke tahap " . $data->current_step);
                }
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->respondError('Gagal memproses persetujuan: ' . $th->getMessage());
        }
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'request_ids'   => 'required|array',
            'request_ids.*' => 'integer',
            'type'          => 'required|string|in:leave,attendance,live_attendance,overtime,change_shift',
            'action'        => 'required|string|in:approve,reject',
        ]);

        $user = auth()->user();
        $userRole = str_replace(' ', '', strtolower($user->role));
        $isSuperAdmin = in_array($userRole, ['superadmin', 'director']);

        // Pemetaan Tipe untuk Judul & Pesan Notifikasi (Bahasa Indonesia)
        $typeLabel = match($request->type) {
            'leave'           => 'Cuti',
            'attendance'      => 'Absensi (Manual)',
            'live_attendance' => 'Absensi (Live)',
            'overtime'        => 'Lembur',
            'change_shift'    => 'Tukar Shift',
            default           => ucfirst($request->type)
        };

        // 1. PISAHKAN MODEL BERDASARKAN TIPE REQUEST
        $modelClass = match ($request->type) {
            'leave'           => \App\Models\LeaveSubmission::class,
            'attendance'      => \App\Models\AttendanceSubmission::class,
            'live_attendance' => \App\Models\Attendance::class, // Model untuk Live Scan
            'overtime'        => \App\Models\OvertimeSubmission::class,
            'change_shift'    => \App\Models\ShiftSubmission::class,
        };

        DB::beginTransaction();
        try {
            $processedCount = 0;
            
            // Tentukan status "pending" yang benar (Live pakai konstanta integer 1)
            $statusPending = ($request->type === 'live_attendance') ? \App\Models\Attendance::STATUS_PENDING : 'pending';

            foreach ($request->request_ids as $id) {
                // Muat data beserta relasi user untuk notifikasi
                $requestRecord = $modelClass::with('user.employee')->find($id);

                // Validasi keberadaan data dan status pending (menggunakan != agar fleksibel int vs string)
                if (!$requestRecord || $requestRecord->status != $statusPending) continue;

                $approvalRecord = $requestRecord->approvalSteps()
                    ->where('step', $requestRecord->current_step)
                    ->where('status', 'pending')
                    ->first();

                // Cek Otorisasi (Superadmin atau Approver/Penerima Delegasi)
                if (!$isSuperAdmin) {
                    if (!$approvalRecord || !\App\Services\ApprovalDelegationService::canPerformAction($approvalRecord->approver_id, $requestRecord->user_id)) {
                        continue;
                    }
                }

                $isFinalAction = $isSuperAdmin || ($requestRecord->current_step == $requestRecord->total_steps);
                $detailLink = $this->getDetailLink($request->type, $requestRecord->id);

                // --- A. LOGIKA TOLAK (REJECT) ---
                if ($request->action === 'reject') {
                    $note = $isSuperAdmin ? 'Force rejected by Superadmin (Bulk)' : 'Rejected via Bulk Action';
                    
                    $requestRecord->approvalSteps()->where('status', 'pending')->update([
                        'status'       => 'rejected',
                        'processed_by' => $user->id,
                        'note'         => $isSuperAdmin ? $note : $request->reason,
                        'action_at'    => now()
                    ]);

                    // Tentukan status reject (Live = 3, Manual/Lainnya = 'rejected')
                    $rejectStatus = ($request->type === 'live_attendance') ? \App\Models\Attendance::STATUS_REJECTED : 'rejected';
                    
                    $requestRecord->update([
                        'status'         => $rejectStatus,
                        'rejection_note' => "Ditolak via Bulk Action oleh {$user->name}"
                    ]);

                    // Notifikasi Penolakan
                    $requestRecord->user->notify(new SubmissionNotification(
                        "Pengajuan {$typeLabel} Ditolak",
                        "Pengajuan {$typeLabel} Anda telah ditolak oleh {$user->name}.",
                        $detailLink,
                        'rejected'
                    ));
                }

                // --- B. LOGIKA SETUJUI (APPROVE) ---
                else if ($request->action === 'approve') {
                    if ($isSuperAdmin) {
                        $requestRecord->approvalSteps()->where('status', 'pending')->update([
                            'status'       => 'approved',
                            'processed_by' => $user->id,
                            'note'         => 'Force approved by Superadmin (Bulk)',
                            'action_at'    => now()
                        ]);
                        $requestRecord->current_step = $requestRecord->total_steps;
                    } else {
                        if ($approvalRecord) {
                            $approvalRecord->update([
                                'status'       => 'approved',
                                'processed_by' => $user->id,
                                'action_at'    => now()
                            ]);
                        }
                    }

                    if ($isFinalAction) {
                        // Update status Final berdasarkan jenis model
                        if ($request->type === 'live_attendance') {
                            $requestRecord->update([
                                'status' => \App\Models\Attendance::STATUS_PRESENT, // Nilai 4
                                'is_location_valid' => true
                            ]);
                            // Buat log audit untuk Live Scan
                            $this->createLog($requestRecord, null, null, "Approved via Bulk Action by " . $user->name);
                        } else {
                            $requestRecord->update(['status' => 'approved']);
                            // Jalankan sinkronisasi (Potong cuti, update matrix shift, dll)
                            $this->handleFinalSync($request->type, $requestRecord, $user->id);
                        }

                        // Notifikasi Final
                        $requestRecord->user->notify(new SubmissionNotification(
                            "Pengajuan {$typeLabel} Disetujui Final",
                            "Pengajuan {$typeLabel} Anda telah disetujui sepenuhnya.",
                            $detailLink,
                            'approved'
                        ));
                    } else {
                        // Lanjut ke tahap berikutnya
                        $requestRecord->increment('current_step');
                        
                        $nextStep = $requestRecord->approvalSteps()->where('step', $requestRecord->current_step)->first();
                        if ($nextStep && $nextStep->approver) {
                            $photoUrl = $requestRecord->user->employee?->photo ? Storage::url($requestRecord->user->employee->photo) : null;
                            
                            \App\Services\ApprovalDelegationService::sendParallelNotification(
                                $nextStep->approver,
                                new SubmissionNotification(
                                    "Butuh Persetujuan: {$typeLabel}",
                                    "{$user->name} telah menyetujui tahap sebelumnya. Mohon tinjau pengajuan {$typeLabel} dari {$requestRecord->user->name}.",
                                    $detailLink,
                                    $request->type,
                                    $photoUrl
                                )
                            );
                        }
                    }
                }
                $processedCount++;
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => "Berhasil memproses $processedCount pengajuan."]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Sistem Error: ' . $th->getMessage()], 500);
        }
    }

    /**
     * Helper untuk link detail berdasarkan tipe
     */
    private function getDetailLink($type, $id) 
    {
        return match($type) {
            'leave'        => "/leave/approvals/detail/{$id}",
            'attendance'   => "/attendance/approvals/detail/{$id}?source_type=request",
            'overtime'     => "/overtime/approvals/detail/{$id}",
            'change_shift' => "/shift/approvals/detail/{$id}",
            default        => "#"
        };
    }

    /**
     * Helper untuk logika sinkronisasi data final
     */
    private function handleFinalSync($type, $record, $adminId) 
    {
        if ($type === 'leave') {
            $record->load('leave');
            if ($record->leave && $record->leave->is_deduct_quota) {
                $start = Carbon::parse($record->start_date);
                $end = Carbon::parse($record->end_date);
                $days = $start->diffInDays($end) + 1;
                $balance = \App\Models\LeaveBalance::firstOrCreate(['user_id' => $record->user_id, 'year' => $start->year]);
                $balance->increment('used_quota', $days);
            }
        } elseif ($type === 'attendance') {
            $this->syncRequestToAttendance($record);
        } elseif ($type === 'change_shift') {
            $date = Carbon::parse($record->date);
            $schedule = \App\Models\ShiftSchedule::firstOrNew(['user_id' => $record->user_id, 'month' => $date->month, 'year' => $date->year]);
            $data = $schedule->schedule_data ?? [];
            $data[(string)$date->day] = ['is_off' => false, 'shift_id' => $record->shift_new_id];
            $schedule->schedule_data = $data;
            $schedule->save();
        }
    }

    private function translateType($type) 
    {
        return match($type) {
            'leave'        => 'Cuti',
            'attendance'   => 'Absensi',
            'overtime'     => 'Lembur',
            'change_shift' => 'Tukar Shift',
            default        => ucfirst($type)
        };
    }   

    private function syncRequestToAttendance($submission)
    {
        $attendance = Attendance::firstOrNew([
            'user_id' => $submission->user_id,
            'date'    => $submission->date
        ]);

        $attendance->shift_id = $submission->shift_id;
        $attendance->status   = Attendance::STATUS_PRESENT;
        $attendance->is_location_valid = true;
        $attendance->save();

        AttendanceLog::create([
            'attendance_id'   => $attendance->id,
            'attendance_type' => $submission->attendance_type,
            'time'            => $submission->time,
            'photo'           => $submission->file,
            'lat'             => null,
            'lng'             => null,
            'device_info'     => 'Manual Request (Approved)',
            'note'            => 'Koreksi Manual: ' . $submission->reason
        ]);
    }

    private function createLog($attendance, $type = null, $note = null, $deviceInfo = 'Admin Action')
    {
        $lastLog = $attendance->logs()->latest()->first();
        $finalNote = $note ?? ($lastLog ? $lastLog->note : '-');

        AttendanceLog::create([
            'attendance_id'   => $attendance->id,
            'attendance_type' => $type ?? ($lastLog ? $lastLog->attendance_type : 'check_in'),
            'time'            => now()->format('H:i:s'),
            'lat'             => $lastLog ? $lastLog->lat : null,
            'lng'             => $lastLog ? $lastLog->lng : null,
            'photo'           => $lastLog ? $lastLog->photo : null,
            'device_info'     => $deviceInfo,
            'note'            => $finalNote
        ]);
    }

    public function show(Request $request, $id)
    {
        $sourceType = $request->query('source_type');
        $data = null;

        if ($sourceType === 'attendance') {
            $attendance = Attendance::with(['user.employee', 'shift', 'logs'])->find($id);

            if ($attendance) {
                $approvalSteps = \App\Models\RequestApproval::where('requestable_id', $id)
                    ->where('requestable_type', 'App\Models\Attendance')
                    ->with('approver', 'processor')
                    ->orderBy('step', 'asc')
                    ->get();

                $log = $attendance->logs->sortBy('created_at')->first();
                $statusMap = [
                    Attendance::STATUS_PRESENT => 'Approved',
                    Attendance::STATUS_PENDING => 'Pending',
                    Attendance::STATUS_REJECTED => 'Rejected',
                    Attendance::STATUS_LATE => 'Late',
                    Attendance::STATUS_EARLY_OUT => 'Early Out'
                ];

                $avatarRaw = $attendance->user->employee->avatar ?? null;
                $avatarUrl = null;
                if ($avatarRaw) {
                    $avatarUrl = str_starts_with($avatarRaw, 'http') ? $avatarRaw : Storage::url($avatarRaw);
                }

                $data = [
                    'id'                => $attendance->id,
                    'source_type'       => 'attendance',
                    'user_id'           => $attendance->user->employee->employee_id ?? $attendance->user->employee->nip ?? '-',
                    'user_name'         => $attendance->user->name ?? 'Unknown',
                    'user_avatar'       => $avatarUrl,
                    'date'              => Carbon::parse($attendance->date)->translatedFormat('l, d F Y'),
                    'time'              => $log ? Carbon::parse($log->time)->format('H:i') : '-',
                    'status'            => $statusMap[$attendance->status] ?? 'Unknown',
                    'status_label'      => $statusMap[$attendance->status] ?? 'Unknown',
                    'reason'            => $log ? $log->note : 'Lokasi/Wajah tidak valid',
                    'rejection_note'    => $attendance->rejection_note ?? null,
                    'lat'               => $log ? $log->lat : null,
                    'lng'               => $log ? $log->lng : null,
                    'photo_url'         => ($log && $log->photo) ? Storage::url($log->photo) : null,
                    'attachment'        => null,
                    'createdAt'         => Carbon::parse($attendance->created_at)->translatedFormat('l, d F Y | H:i'),
                    'created_at_human'  => Carbon::parse($attendance->created_at)->diffForHumans(),
                    'current_step'      => $attendance->current_step,
                    'total_steps'       => $attendance->total_steps,
                    'approval_history'  => $approvalSteps->map(function ($step) {
                        return [
                            'step'              => $step->step,
                            'approver_id'       => $step->approver_id,
                            'approver_name'     => $step->approver->name ?? 'Unknown',
                            'status'            => $step->status,
                            'note'              => $step->note,
                            'action_at'         => $step->action_at ? Carbon::parse($step->action_at)->isoFormat('dddd, D MMM Y | HH:mm') : null,
                            'processed_by_name' => $step->processor->name ?? null,
                        ];
                    }),
                ];
            }
        } elseif ($sourceType === 'request') {
            $req = AttendanceSubmission::with(['user.employee'])->find($id);

            if ($req) {
                $approvalSteps = \App\Models\RequestApproval::where('requestable_id', $id)
                    ->whereIn('requestable_type', [
                        'App\Models\AttendanceSubmission',
                        'App\Models\AttendanceRequest',
                        'attendance_requests',
                        'attendance_submissions'
                    ])
                    ->with('approver', 'processor')
                    ->orderBy('step', 'asc')
                    ->get();

                $statusLabel = ucfirst($req->status);
                $avatarRaw = $req->user->employee->avatar ?? null;
                $avatarUrl = null;
                if ($avatarRaw) {
                    $avatarUrl = str_starts_with($avatarRaw, 'http') ? $avatarRaw : Storage::url($avatarRaw);
                }

                $data = [
                    'id'                => $req->id,
                    'source_type'       => 'request',
                    'user_id'           => $req->user->employee->employee_id ?? $req->user->employee->nip ?? '-',
                    'user_name'         => $req->user->name ?? 'Unknown',
                    'user_avatar'       => $avatarUrl,
                    'date'              => Carbon::parse($req->date)->translatedFormat('l, d F Y'),
                    'time'              => Carbon::parse($req->time)->format('H:i'),
                    'status'            => ucfirst($req->status),
                    'status_label'      => $statusLabel,
                    'reason'            => $req->reason,
                    'rejection_note'    => $req->rejection_note ?? null,
                    'lat'               => null,
                    'lng'               => null,
                    'photo_url'         => null,
                    'attachment'        => $req->file ? Storage::url($req->file) : null,
                    'createdAt'         => Carbon::parse($req->created_at)->translatedFormat('l, d F Y | H:i'),
                    'created_at_human'  => Carbon::parse($req->created_at)->diffForHumans(),
                    'current_step'      => $req->current_step,
                    'total_steps'       => $req->total_steps,
                    'approval_history'  => $approvalSteps->map(function ($step) {
                        return [
                            'step'              => $step->step,
                            'approver_id'       => $step->approver_id,
                            'approver_name'     => $step->approver->name ?? 'Unknown',
                            'status'            => $step->status,
                            'note'              => $step->note,
                            'action_at'         => $step->action_at ? Carbon::parse($step->action_at)->isoFormat('dddd, D MMM Y | HH:mm') : null,
                            'processed_by_name' => $step->processor->name ?? null,
                        ];
                    }),
                ];
            }
        }

        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $canApprove = false;
        $submissionForAccess = ($sourceType === 'attendance') ? $attendance : $req;

        if ($submissionForAccess) {
            // Bersihkan spasi dari role agar 'Super Admin' tetap terbaca 'superadmin'
            $user = auth()->user();
            $userRole = str_replace(' ', '', strtolower($user->role));
            $isSuperAdmin = in_array($userRole, ['superadmin', 'director']);

            $stepData = $submissionForAccess->approvalSteps()
                ->where('step', $submissionForAccess->current_step)
                ->where('status', 'pending')
                ->first();

            // Cek otorisasi
            if ($submissionForAccess->status === 'pending') {
                if ($isSuperAdmin) {
                    $canApprove = true;
                } elseif ($stepData) {
                    $canApprove = \App\Services\ApprovalDelegationService::canPerformAction(
                        $stepData->approver_id,
                        $submissionForAccess->user_id
                    );
                }
            }
        }

        $data['can_approve'] = $canApprove;

        return response()->json(['success' => true, 'data' => $data]);
    }
}
