<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;

class ApprovalLinesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
{
    public function collection()
    {
        return User::with(['employee.department', 'employee.position', 'approvalLines.approver'])
            ->whereHas('employee')
            ->orderBy('id')
            ->get();
    }

    public function headings(): array
    {
        return [
            'NIP',
            'Employee Name',
            'Department',
            'Position',
            'Email',
            'Approver 1 (Nama)',
            'Approver 2 (Nama)',
            'Approver 3 (Nama)'
        ];
    }

    public function map($user): array
    {
        $approver1 = '';
        $approver2 = '';
        $approver3 = '';

        foreach ($user->approvalLines as $line) {
            if ($line->step === 1) $approver1 = $line->approver->name ?? '';
            if ($line->step === 2) $approver2 = $line->approver->name ?? '';
            if ($line->step === 3) $approver3 = $line->approver->name ?? '';
        }

        return [
            $user->employee->nip ?? '-',
            $user->name ?? '-',
            $user->employee->department->name ?? '-',
            $user->employee->position->name ?? '-',
            $user->email,
            $approver1,
            $approver2,
            $approver3,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 11],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFEEEEEE'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                $borderStyle = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FFCCCCCC'],
                        ],
                    ],
                ];
                $sheet->getStyle('A1:' . $highestColumn . $highestRow)->applyFromArray($borderStyle);

                $sheet->getProtection()->setPassword('secret123');
                $sheet->getProtection()->setSheet(true);

                if ($highestRow >= 2) {
                    $sheet->getStyle('F2:H' . $highestRow)
                        ->getProtection()
                        ->setLocked(Protection::PROTECTION_UNPROTECTED);
                }
            },
        ];
    }
}
