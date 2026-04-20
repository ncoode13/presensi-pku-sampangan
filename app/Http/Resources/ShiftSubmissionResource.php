<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class ShiftSubmissionResource extends JsonResource
{
    public function toArray($request)
    {
        $statusLabel = ucfirst($this->status);

        $avatarRaw = $this->user->employee->avatar ?? $this->user->employee->photo ?? null;
        $avatarUrl = null;

        if ($avatarRaw) {
            if (str_starts_with($avatarRaw, 'http')) {
                $avatarUrl = $avatarRaw;
            } else {
                $avatarUrl = Storage::url($avatarRaw);
            }
        }

        $attachmentUrl = $this->attachment ? Storage::url($this->attachment) : null;

        return [
            'id'          => $this->id,
            'source_type' => 'shift',
            'user_name'   => $this->user->name ?? 'Unknown',
            'userName'    => $this->user->name ?? 'Unknown',
            'user_avatar' => $avatarUrl,
            'employee_id' => $this->user?->employee?->employee_id ?? '-',

            'date'        => Carbon::parse($this->date)->isoFormat('dddd, D MMMM Y'),
            'date_raw'    => $this->date,

            'current_shift_name' => $this->currentShift->name ?? '-',
            'current_shift_time' => $this->currentShift ?
                Carbon::parse($this->currentShift->start_time)->format('H:i') . ' - ' . Carbon::parse($this->currentShift->end_time)->format('H:i') : '-',

            'target_shift_name'  => $this->targetShift->name ?? '-',
            'target_shift_time'  => $this->targetShift ?
                Carbon::parse($this->targetShift->start_time)->format('H:i') . ' - ' . Carbon::parse($this->targetShift->end_time)->format('H:i') : '-',

            'reason'      => $this->reason,
            'attachment'  => $attachmentUrl,

            'status'         => $this->status,
            'status_label'   => $statusLabel,

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
