<?php

namespace App\Exports;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ShiftScheduleTemplateExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
{
    protected $startDate;
    protected $endDate;
    protected $employeeIds;
    protected $holidays;
    protected $datePeriod;
    protected $schedules;
    protected $masterShifts;

    private $staticColumnCount = 8;

    public function __construct($startDate, $endDate, $employeeIds = [])
    {
        $this->startDate = Carbon::parse($startDate)->startOfDay();
        $this->endDate = Carbon::parse($endDate)->endOfDay();
        $this->employeeIds = $employeeIds;

        $this->datePeriod = CarbonPeriod::create($this->startDate, $this->endDate);

        // 1. Ambil data Libur Nasional
        $this->holidays = Holiday::where(function ($query) {
            $query->whereBetween('start_date', [$this->startDate, $this->endDate])
                ->orWhereBetween('end_date', [$this->startDate, $this->endDate]);
        })->get();

        // 2. Ambil Master Shift untuk referensi nama shift
        $this->masterShifts = Shift::pluck('name', 'id');

        // 3. Ambil Jadwal Custom yang sudah pernah diedit dari Database
        $startMonth = $this->startDate->month;
        $startYear = $this->startDate->year;

        $this->schedules = ShiftSchedule::whereIn('user_id', $this->employeeIds)
            ->where('month', $startMonth)
            ->where('year', $startYear)
            ->get()
            ->keyBy('user_id');
    }

    public function collection()
    {
        $query = Employee::with([
            'user',
            'position',
            'jobLevel',
            'department',
            'employment_status',
            'shift'
        ])->orderBy('user_id');

        if (!empty($this->employeeIds)) {
            $query->whereIn('user_id', $this->employeeIds);
        }

        return $query->get();
    }

    public function headings(): array
    {
        $headers = [
            'Employee ID',
            'Employee Name',
            'Organization',
            'Job Position',
            'Job Level',
            'Employment Status',
            'Work Scheme',
            'Join Date',
        ];

        foreach ($this->datePeriod as $date) {
            $headers[] = $date->format('Y-m-d');
        }

        return $headers;
    }

    public function map($employee): array
    {
        $defaultShiftName = $employee->shift ? (string) $employee->shift->name : '';

        $row = [
            $employee->nip,
            $employee->user->name ?? '-',
            $employee->department->name ?? '-',
            $employee->position->name ?? '-',
            $employee->jobLevel->name ?? '-',
            $employee->employment_status->name ?? '-',
            ucfirst($employee->work_scheme ?? '-'),
            $employee->join_date ?? '-',
        ];

        // Ambil riwayat editan schedule karyawan ini
        $userSchedule = $this->schedules->get($employee->user_id);
        $schedData = [];
        if ($userSchedule) {
            $schedData = is_string($userSchedule->schedule_data)
                ? json_decode($userSchedule->schedule_data, true)
                : $userSchedule->schedule_data;
        }

        // Isi Cell Excel sesuai Tanggal yang dipilih
        foreach ($this->datePeriod as $currentDate) {
            $cellValue = '';
            $currentObj = $currentDate->copy()->startOfDay();
            $dayKey = (string) $currentObj->day;

            // CEK: Apakah ada jadwal hasil editan (Custom) di tanggal ini?
            if (is_array($schedData) && isset($schedData[$dayKey])) {
                $dayData = $schedData[$dayKey];

                if (isset($dayData['is_off']) && $dayData['is_off'] == true) {
                    $cellValue = 'Dayoff';
                } elseif (!empty($dayData['shift_id'])) {
                    // Ambil nama shift dari relasi ID
                    $cellValue = $this->masterShifts[$dayData['shift_id']] ?? $defaultShiftName;
                } else {
                    $cellValue = $defaultShiftName;
                }
            }
            // JIKA TIDAK ADA EDITAN, GUNAKAN DEFAULT SHIFT BAWAAN
            else {
                if (strtolower($employee->work_scheme) === 'office') {
                    $holidayData = $this->holidays->first(function ($holiday) use ($currentObj) {
                        return $currentObj->between($holiday->start_date->copy()->startOfDay(), $holiday->end_date->copy()->endOfDay());
                    });

                    if ($holidayData) {
                        $cellValue = 'National Holiday';
                    } elseif ($currentObj->isSunday()) {
                        $cellValue = 'Dayoff';
                    } else {
                        $cellValue = $defaultShiftName ?: 'Office';
                    }
                } else {
                    $cellValue = $defaultShiftName;
                }
            }

            $row[] = $cellValue;
        }

        return $row;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
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
                $sheet = $event->sheet;
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();
                $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

                $redStyle = [
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFEBEB']],
                    'font' => ['color' => ['argb' => 'FFFF0000'], 'bold'  => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ];

                $borderStyle = [
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]],
                ];

                $sheet->getStyle('A1:' . $highestColumn . $highestRow)->applyFromArray($borderStyle);

                $startCol = $this->staticColumnCount + 1;

                // Warnai merah untuk hari minggu & libur
                for ($col = $startCol; $col <= $highestColumnIndex; $col++) {
                    $colString = Coordinate::stringFromColumnIndex($col);
                    $headerValue = $sheet->getCell($colString . '1')->getValue();
                    $isRedDate = false;

                    try {
                        $date = Carbon::createFromFormat('Y-m-d', $headerValue)->startOfDay();
                        if ($date->isSunday()) {
                            $isRedDate = true;
                        } else {
                            $isHoliday = $this->holidays->filter(function ($holiday) use ($date) {
                                return $date->between($holiday->start_date->copy()->startOfDay(), $holiday->end_date->copy()->endOfDay());
                            })->isNotEmpty();
                            if ($isHoliday) $isRedDate = true;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }

                    if ($isRedDate) {
                        $sheet->getStyle($colString . '1:' . $colString . $highestRow)->applyFromArray($redStyle);
                    }
                }
            },
        ];
    }
}
