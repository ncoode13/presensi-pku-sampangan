<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class OvertimeSubmissionResource extends JsonResource
{
    public function toArray($request)
    {
        $statusLabel = ucfirst($this->status);
        $avatarPath = $this->user->employee->avatar ?? null;

        return [
            'id' => $this->id,
            'source_type' => 'overtime',

            'user_name'   => $this->user->name ?? 'Unknown',
            'userName'    => $this->user->name ?? 'Unknown',
            'user_avatar' => $avatarPath ? Storage::url($avatarPath) : null,
            'user_id'     => $this->user->id,
            'employee_id' => $this->user->employee->employee_id ?? '-',

            'date'        => $this->date,
            'date_raw'    => $this->date,

            'duration'    => ($this->duration_before + $this->duration_after) . ' Menit',
            'shift_name'  => $this->shift->name ?? '-',

            'reason'      => $this->reason,
            'attachment'  => $this->file ? Storage::url($this->file) : null,

            'status'       => $this->status,
            'status_label' => $statusLabel,

            'current_step'   => $this->current_step,
            'total_steps'    => $this->total_steps,
            'rejection_note' => $this->rejection_note,
            'can_approve'    => $this->can_approve ?? false,

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

            'created_at_human' => $this->created_at->diffForHumans(),
            'createdAt'        => $this->created_at->isoFormat('dddd, D MMM Y | HH:mm'),
        ];
    }
}
