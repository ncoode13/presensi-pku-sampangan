<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $personal = $this->relationLoaded('personal') && $this->personal
            ? $this->personal
            : ($this->relationLoaded('user') && $this->user ? $this->user->personal : null);

        $emergencyContact = $personal && $personal->relationLoaded('emergencyContact') ? $personal->emergencyContact : null;

        // --- MAPPING AGAMA ---
        $religionMap = [
            '1' => 'Katolik',
            '2' => 'Islam',
            '3' => 'Kristen',
            '4' => 'Budha',
            '5' => 'Hindu',
            '6' => 'Khonghucu',
            '7' => 'Lainnya',
        ];

        // --- MAPPING STATUS PERNIKAHAN ---
        $maritalMap = [
            'single'   => 'Belum Kawin',
            'maried'   => 'Kawin',
            'widow'    => 'Janda',
            'widower'  => 'Duda',
        ];

        // --- MAPPING JENIS KELAMIN ---
        $genderMap = [
            'male'   => 'Laki-laki',
            'female' => 'Perempuan',
        ];

        $lengthOfService = '-';
        if ($this->join_date) {
            $joinDate = Carbon::parse($this->join_date);
            $diff = $joinDate->diffInDays(now());

            if ($diff < 1) {
                $lengthOfService = 'Hari Pertama';
            } else {
                $diffObj = $joinDate->diff(now());
                $parts = [];
                if ($diffObj->y > 0) $parts[] = $diffObj->y . ' Tahun';
                if ($diffObj->m > 0) $parts[] = $diffObj->m . ' Bulan';
                if ($diffObj->d > 0) $parts[] = $diffObj->d . ' Hari';
                $lengthOfService = implode(', ', $parts);
            }
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'employee_id' => $this->employee_id,
            'nip' => $this->nip,
            'unit_id' => $this->unit_id,

            'unit' => $this->whenLoaded('unit', function () {
                return [
                    'id' => $this->unit->id,
                    'name' => $this->unit->name,
                ];
            }),

            'full_name' => $this->whenLoaded('user', fn() => $this->user->name),

            'join_date' => $this->join_date,
            'join_date_formatted' => $this->join_date ? Carbon::parse($this->join_date)->translatedFormat('d F Y') : null,
            'length_of_service' => $lengthOfService,

            'photo' => $this->photo ? Storage::url($this->photo) : null,
            'avatar' => $this->avatar ? Storage::url($this->avatar) : null,
            'email' => $this->whenLoaded('user', fn() => $this->user->email),
            'attachment' => $this->attachment ? Storage::url($this->attachment) : null,
            'work_scheme' => $this->work_scheme,
            'work_scheme_label' => $this->work_scheme === 'office' ? 'Non-Shift (Kantor)' : 'Shift (Pelayanan)',

            'personal_info' => [
                'first_name' => $personal?->first_name,
                'last_name' => $personal?->last_name,
                'place_of_birth' => $personal?->place_of_birth,
                'birth_date' => $personal?->birth_date
                    ? Carbon::parse($personal->birth_date)->translatedFormat('d F Y')
                    : null,

                // PERBAIKAN GENDER
                'gender' => $genderMap[strtolower($personal?->gender)] ?? $personal?->gender,

                'phone' => $personal?->phone,
                'address' => $personal?->address,

                // PERBAIKAN MARITAL STATUS
                'marital_status' => $personal && $personal->relationLoaded('maritalStatus')
                    ? ($maritalMap[strtolower($personal->maritalStatus?->name)] ?? $personal->maritalStatus?->name)
                    : ($maritalMap[strtolower($personal?->marital_status)] ?? $personal?->marital_status),

                'blood_type'     => $personal?->blood_type,

                // PERBAIKAN RELIGION
                'religion'       => $religionMap[$personal?->religion] ?? $personal?->religion,

                'nik'            => $personal?->nik,
                'npwp'           => $personal?->npwp,
                'postal_code'    => $personal?->postal_code,
            ],

            'department' => $this->whenLoaded('department', fn() => $this->department->name),
            'position' => $this->whenLoaded('position', fn() => $this->position->name),
            'level' => $this->whenLoaded('job_level', fn() => $this->job_level->name),
            'status' => $this->whenLoaded('employment_status', fn() => $this->employment_status->name),

            'shift' => $this->whenLoaded('shift', function () {
                return [
                    'name' => $this->shift->name,
                    'id' => $this->shift->id,
                    'start_time' => $this->shift->start_time,
                    'end_time' => $this->shift->end_time,
                ];
            }),

            'group' => $this->group,
            'rank' => $this->rank,

            'emergency_contact' => $emergencyContact ? [
                'name' => $emergencyContact->name,
                'phone' => $emergencyContact->phone,
                'relationship' => $emergencyContact->relationLoaded('relationship') && $emergencyContact->relationship ? $emergencyContact->relationship->name : null,
                'relationship_id' => $emergencyContact->relationship_id,
            ] : null,

            'today_attendance' => $this->attendance_today ?? null,
            'office_locations' => $this->office_locations,
        ];
    }
}
