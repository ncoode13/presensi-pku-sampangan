<?php

namespace App\Exports;

use App\Models\Employee;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class EmployeeBulkUpdateExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $employeeIds;
    protected $category;

    public function __construct($employeeIds, $category)
    {
        $this->employeeIds = $employeeIds;
        $this->category = $category;
    }

    public function collection()
    {
        return Employee::with([
            'user.personal.emergencyContact.relationship',
            'department',
            'position',
            'job_level',
            'employment_status'
        ])
            ->whereIn('id', $this->employeeIds)
            ->get();
    }

    public function headings(): array
    {
        if ($this->category === 'general') {
            return [
                'NIP',
                'First Name',
                'Last Name',
                'Email',
                'NIK (16 Digit)',
                'Address',
                'Place of Birth',
                'Date of Birth (YYYY-MM-DD)',
                'Phone Number',
                'Gender',
                'Marital Status',
                'Religion',
                'Organization Name',
                'Job Position',
                'Job Level',
                'Grade',
                'Class',
                'Employment Status',
                'Join Date (YYYY-MM-DD)',
                'End Date (YYYY-MM-DD)',
                'NPWP',
                'Length of Service'
            ];
        }

        return [
            'NIP',
            'Employee Name',
            'Contact Name',
            'Relationship',
            'Contact Phone'
        ];
    }

    public function map($employee): array
    {
        $personal = $employee->user->personal ?? null;

        if ($this->category === 'general') {
            $los = '-';
            if ($employee->join_date) {
                $los = Carbon::parse($employee->join_date)->diff(now())->format('%y Tahun, %m Bulan');
            }

            return [
                $employee->nip,
                $personal->first_name ?? '-',
                $personal->last_name ?? '-',
                $employee->user->email ?? '-',
                $personal->nik ?? '-',
                $personal->address ?? '-',
                $personal->place_of_birth ?? '-',
                $personal->birth_date ? Carbon::parse($personal->birth_date)->format('Y-m-d') : '-',
                $personal->phone ?? '-',
                $personal->gender ?? '-',
                $personal->marital_status ?? '-',
                $personal->religion ?? '-',
                $employee->department->name ?? '-',
                $employee->position->name ?? '-',
                $employee->job_level->name ?? '-',
                $employee->group ?? '-',
                $employee->rank ?? '-',
                $employee->employment_status->name ?? '-',
                $employee->join_date ? Carbon::parse($employee->join_date)->format('Y-m-d') : '-',
                $employee->end_date ? Carbon::parse($employee->end_date)->format('Y-m-d') : '-',
                $personal->npwp ?? '-',
                $los
            ];
        }

        $emergency = $personal ? $personal->emergencyContact : null;
        return [
            $employee->nip,
            $employee->user->name ?? '-',
            $emergency->name ?? '-',
            $emergency->relationship->name ?? '-',
            $emergency->phone ?? '-'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF0284C7'],
                ],
            ],
        ];
    }
}
