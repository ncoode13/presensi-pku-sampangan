<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use App\Models\LeaveBalance;
use Carbon\Carbon;

class LeaveSubmissionResource extends JsonResource
{
    public function toArray($request)
    {
        $statusLabel = ucfirst($this->status);

        $avatarPath = $this->user->employee->avatar ?? null;

        $leaveYear = $this->start_date ? Carbon::parse($this->start_date)->year : now()->year;

        $balanceData = LeaveBalance::where('user_id', $this->user->id)
            ->where('year', $leaveYear)
            ->first();

        $totalQuota = $balanceData->total_quota ?? 12;
        $usedQuota  = $balanceData->used_quota ?? 0;
        $sisaCuti   = $totalQuota - $usedQuota;

        return [
            'id' => $this->id,
            'source_type' => 'leave',

            'user_name'   => $this->user->name ?? 'Unknown',
            'userName'    => $this->user->name ?? 'Unknown',
            'user_avatar' => $avatarPath ? Storage::url($avatarPath) : null,
            'user_id'     => $this->user->id,
            'employee_id' => $this->user->employee->employee_id ?? '-',

            'date'        => $this->start_date,
            'start_date'  => $this->start_date,
            'end_date'    => $this->end_date,

            'duration'    => ($this->start_date && $this->end_date)
                ? Carbon::parse($this->start_date)->diffInDays($this->end_date) + 1 . ' Hari'
                : '-',

            'leave_type'  => $this->leave->name ?? '-',
            'reason'      => $this->reason,
            'attachment'  => $this->file ? Storage::url($this->file) : null,

            'status'       => $this->status,
            'status_label' => $statusLabel,

            'current_step' => $this->current_step,
            'total_steps'  => $this->total_steps,
            'rejection_note' => $this->rejection_note,
            'can_approve' => $this->can_approve ?? false,

            'approval_history' => $this->approvalSteps->map(function ($step) {
                return [
                    'step'          => $step->step,
                    'approver_id'   => $step->approver_id,
                    'approver_name' => $step->approver->name ?? 'Unknown',
                    'status'        => $step->status,
                    'note'          => $step->note,
                    'action_at'     => $step->action_at ? Carbon::parse($step->action_at)->isoFormat('dddd, D MMM Y | HH:mm') : null,
                    'processed_by_name' => $step->processor->name ?? null,
                ];
            }),

            'balance_info' => [
                'year'      => $leaveYear,
                'total'     => $totalQuota,
                'used'      => $usedQuota,
                'remaining' => $sisaCuti,
            ],

            'created_at_human' => $this->created_at->diffForHumans(),
            'createdAt'        => $this->created_at->isoFormat('dddd, D MMM Y | HH:mm'),
        ];
    }
}
