<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class ShiftResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'   => $this->id,
            'name' => $this->name,
            'start_time' => Carbon::parse($this->start_time)->format('H:i'),
            'end_time'   => Carbon::parse($this->end_time)->format('H:i'),

            'tolerance_late'  => $this->tolerance_come_too_late,
            'tolerance_early' => $this->tolerance_go_home_early,

            'work_hours' => $this->calculateDuration(),
        ];
    }

    private function calculateDuration()
    {
        $start = Carbon::parse($this->start_time);
        $end   = Carbon::parse($this->end_time);

        if ($end->lessThan($start)) {
            $end->addDay();
        }

        return $start->diffInHours($end) . ' Jam';
    }
}
