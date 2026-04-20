<?php

namespace App\Exports;

use App\Models\User;
use App\Models\DiklatEvent;
use App\Models\DiklatSetting;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithMapping;
use Carbon\Carbon;

class DiklatCategorySheet implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize, WithEvents, WithColumnFormatting, WithMapping
{
    protected $year;
    protected $title;
    protected $filter;
    protected $events;
    protected $globalTarget;

    public function __construct($year, $title, $filter)
    {
        $this->year = $year;
        $this->title = $title;
        $this->filter = $filter;
        
        $this->events = DiklatEvent::whereYear('date', $this->year)
            ->orderBy('date', 'asc')
            ->get();

        $this->globalTarget = (int) (DiklatSetting::where('key', 'target_jpl_tahunan')->value('value') ?? 20);
        
        Carbon::setLocale('id');
    }

    public function title(): string
    {
        return $this->title;
    }

    public function headings(): array
    {
        // Baris 1: No, NIP, Nama, (Bulan yang akan di-merge), Total, Keterangan
        $row1 = ['No', 'NIP', 'Nama Lengkap'];
        foreach ($this->events as $event) {
            $row1[] = Carbon::parse($event->date)->translatedFormat('F');
        }
        $row1[] = 'Total JPL';
        $row1[] = 'Keterangan';

        $row2 = ['', '', ''];
        foreach ($this->events as $event) {
            $row2[] = $event->title;
        }
        $row2[] = '';
        $row2[] = '';

        return [$row1, $row2];
    }

    public function map($user_row): array
    {
        if (isset($user_row[1]) && $user_row[1] !== '-') {
            $user_row[1] = $user_row[1] . " "; 
        }
        return $user_row;
    }

    public function columnFormats(): array
    {
        return [
            'B' => '@',
        ];
    }

    public function collection()
    {   
        $filterValue = $this->filter;
        
        $query = User::with(['employee.position', 'diklatAttendances'])
            ->whereHas('employee');

        // LOGIKA FILTER OPSI 1 & OPSI 2
        switch ($filterValue) {
            // Opsi 1
            case 'office':
            case 'shift':
                $query->whereHas('employee', function($q) use ($filterValue) {
                    $q->where('work_scheme', $filterValue);
                });
                break;

            // Opsi 2
            case 'magang':
                $query->whereHas('employee.employment_status', function($q) {
                    $q->where('name', 'ILIKE', '%magang%');
                });
                break;

            case 'spesialis':
                $query->where(function($q) {
                    $q->where('name', 'ILIKE', 'dr.%') // Nama depan dr.
                      ->where('name', 'ILIKE', '%Sp.%'); // Mengandung Sp.
                });
                break;

            case 'cleaning':
                $query->whereHas('employee', function($q) {
                    $q->where('work_scheme', 'not_found'); 
                });
                break;

            case 'civitas':
                // Civitas: Bukan Spesialis DAN Bukan Magang
                $query->whereNot(function($q) {
                    $q->where('name', 'ILIKE', 'dr.%')->where('name', 'ILIKE', '%Sp.%');
                })->whereDoesntHave('employee.employment_status', function($q) {
                    $q->where('name', 'ILIKE', '%magang%');
                });
                break;
        }

        $users = $query->orderBy('name', 'asc')->get();

        return $users->map(function ($user, $index) {
            $row = [
                $index + 1,
                $user->employee->nip ?? '-', 
                $user->name,
            ];

            $totalUserJpl = 0;
            $attendedIds = $user->diklatAttendances->pluck('diklat_event_id')->toArray();

            foreach ($this->events as $event) {
                $row[] = in_array($event->id, $attendedIds) ? $event->jpl : 0;
                if (in_array($event->id, $attendedIds)) {
                    $totalUserJpl += $event->jpl;
                }
            }

            $row[] = $totalUserJpl;
            $row[] = $totalUserJpl >= $this->globalTarget ? 'Terpenuhi' : 'Tidak Terpenuhi';

            return $row;
        });
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Mulai dari kolom D (index ke-4) karena A, B, C adalah No, NIP, Nama
                $startColIndex = 4; 
                
                // Hitung jumlah event per bulan
                $monthlyCounts = [];
                foreach ($this->events as $diklat) {
                    $monthName = Carbon::parse($diklat->date)->translatedFormat('F');
                    if (!isset($monthlyCounts[$monthName])) {
                        $monthlyCounts[$monthName] = 0;
                    }
                    $monthlyCounts[$monthName]++;
                }

                $currentCol = $startColIndex;
                foreach ($monthlyCounts as $month => $count) {
                    if ($count > 1) {
                        $fromCol = $this->getNameFromNumber($currentCol);
                        $toCol = $this->getNameFromNumber($currentCol + $count - 1);
                        
                        // Merge baris pertama untuk Bulan
                        $sheet->mergeCells("{$fromCol}1:{$toCol}1");
                    }
                    $currentCol += $count;
                }

                // Merge baris 1 & 2 untuk kolom statis (No, NIP, Nama, Total, Keterangan)
                $staticCols = ['A', 'B', 'C'];
                foreach ($staticCols as $col) {
                    $sheet->mergeCells("{$col}1:{$col}2");
                }
                
                // Merge kolom Total JPL dan Keterangan di akhir
                $lastEventColNum = $startColIndex + count($this->events) - 1;
                $totalJplCol = $this->getNameFromNumber($lastEventColNum + 1);
                $ketCol = $this->getNameFromNumber($lastEventColNum + 2);
                
                $sheet->mergeCells("{$totalJplCol}1:{$totalJplCol}2");
                $sheet->mergeCells("{$ketCol}1:{$ketCol}2");
            },
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true], 'alignment' => ['horizontal' => 'center']],
            2 => ['font' => ['bold' => true, 'size' => 9], 'alignment' => ['horizontal' => 'center']],
        ];
    }

    // Helper untuk mengubah angka kolom menjadi huruf (1=A, 2=B, dst)
    private function getNameFromNumber($num) {
        $numeric = $num % 26;
        $letter = chr(65 + ($numeric == 0 ? 25 : $numeric - 1));
        $num2 = intval(($num - 1) / 26);
        if ($num2 > 0) {
            return $this->getNameFromNumber($num2) . $letter;
        } else {
            return $letter;
        }
    }
}