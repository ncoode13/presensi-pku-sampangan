<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class ShiftScheduleImport implements ToCollection, WithStartRow, WithCalculatedFormulas
{
    protected $month;
    protected $year;
    protected $shifts;
    protected $employees;

    public function __construct($month, $year)
    {
        $this->month = $month;
        $this->year = $year;

        $this->shifts = Shift::all()->pluck('id', 'name')->mapWithKeys(function ($item, $key) {
            return [strtolower(trim($key)) => $item];
        });

        $this->employees = Employee::whereNotNull('nip')->pluck('user_id', 'nip');
    }

    public function startRow(): int
    {
        return 1; // Mulai dari row 1 untuk baca Header
    }

    public function collection(Collection $rows)
    {
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['file' => 'File Excel kosong atau tidak terbaca.']);
        }

        $header = $rows[0];

        // 1. Validasi Format Kolom Pertama (NIP)
        $firstColumnRaw = $header[0] ?? '';
        $firstColumn = strtolower(trim($firstColumnRaw));
        $allowedHeaders = ['nip', 'nomor', 'employee id', 'id', 'user id', 'no'];

        $isValidHeader = false;
        foreach ($allowedHeaders as $keyword) {
            if (str_contains($firstColumn, $keyword)) {
                $isValidHeader = true;
                break;
            }
        }

        if (!$isValidHeader) {
            throw ValidationException::withMessages(['file' => "Format file tidak valid. Header kolom pertama harus 'NIP' atau 'Employee ID'."]);
        }

        // 2. Scan Header Tanggal Secara Dinamis (Format: Y-m-d)
        $dateHeaders = [];
        foreach ($header as $index => $value) {
            if (\Carbon\Carbon::hasFormat($value, 'Y-m-d')) {
                $dateHeaders[$index] = $value;
            }
        }

        if (empty($dateHeaders)) {
            throw ValidationException::withMessages(['file' => "Tidak ada header kolom bertanggal dengan format YYYY-MM-DD. Pastikan jangan mengubah header baris pertama."]);
        }

        // 3. Looping Baris Karyawan
        $dataRows = $rows->slice(1);
        $processedCount = 0;

        foreach ($dataRows as $row) {
            $nip = $row[0];
            if (!$nip || !isset($this->employees[$nip])) continue;

            $processedCount++;
            $userId = $this->employees[$nip];

            // Cari atau buat Data Bulanan
            $scheduleRow = ShiftSchedule::firstOrNew([
                'user_id' => $userId,
                'month'   => $this->month,
                'year'    => $this->year,
            ]);

            $currentScheduleData = $scheduleRow->schedule_data ?? [];
            if (is_string($currentScheduleData)) {
                $currentScheduleData = json_decode($currentScheduleData, true);
            }
            if (!is_array($currentScheduleData)) {
                $currentScheduleData = [];
            }

            // Update hanya tanggal yang ada di Excel! (Biarkan tanggal lain utuh)
            foreach ($dateHeaders as $colIndex => $dateStr) {
                $carbonDate = Carbon::parse($dateStr);

                // PENTING: Hanya simpan jika bulan dan tahunnya cocok dengan yang di-request Frontend
                if ($carbonDate->month != $this->month || $carbonDate->year != $this->year) {
                    continue;
                }

                $day = $carbonDate->day;
                $cellValue = isset($row[$colIndex]) ? trim($row[$colIndex]) : '';
                $cellLower = strtolower($cellValue);

                if (in_array($cellLower, ['dayoff', 'national holiday', 'libur', 'off', '-', 'l'])) {
                    $isOff = true;
                    $shiftId = null;
                } elseif (isset($this->shifts[$cellLower])) {
                    $isOff = false;
                    $shiftId = $this->shifts[$cellLower];
                } else {
                    $isOff = false;
                    $shiftId = null; // Default jika ga dikenali
                }

                $currentScheduleData[(string)$day] = [
                    'is_off' => $isOff,
                    'shift_id' => $shiftId
                ];
            }

            // Simpan ke database
            $scheduleRow->schedule_data = $currentScheduleData;
            $scheduleRow->status = 'draft';
            $scheduleRow->created_by = Auth::id() ?? 1;
            $scheduleRow->save();
        }

        if ($processedCount === 0) {
            throw ValidationException::withMessages(['file' => "NIP di Excel tidak ada yang cocok dengan NIP di database."]);
        }
    }
}
