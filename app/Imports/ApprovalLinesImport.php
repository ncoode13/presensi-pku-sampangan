<?php

namespace App\Imports;

use App\Models\ApprovalLine;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Illuminate\Support\Facades\DB;

class ApprovalLinesImport implements ToCollection, WithStartRow
{
    protected $usersByEmail;
    protected $usersByName;

    public function __construct()
    {
        $this->usersByEmail = User::pluck('id', 'email')->mapWithKeys(function ($id, $email) {
            return [strtolower(trim($email)) => $id];
        })->toArray();

        $this->usersByName = User::pluck('id', 'name')->mapWithKeys(function ($id, $name) {
            return [strtolower(trim($name)) => $id];
        })->toArray();
    }

    public function startRow(): int
    {
        return 1;
    }

    public function collection(Collection $rows)
    {
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => 'File Excel kosong atau tidak terbaca.'
            ]);
        }

        $header = $rows[0];
        $emailHeader = strtolower(trim($header[4] ?? ''));

        if (!str_contains($emailHeader, 'email')) {
            throw ValidationException::withMessages([
                'file' => "Format file tidak valid. Pastikan Anda mengupload file hasil download dari sistem tanpa mengubah urutan kolom."
            ]);
        }

        $dataRows = $rows->slice(1);
        $processedCount = 0;

        DB::beginTransaction();

        try {
            foreach ($dataRows as $row) {
                $emailKaryawan = strtolower(trim($row[4] ?? ''));

                if (!$emailKaryawan || !isset($this->usersByEmail[$emailKaryawan])) {
                    continue;
                }

                $processedCount++;
                $karyawanId = $this->usersByEmail[$emailKaryawan];

                ApprovalLine::where('user_id', $karyawanId)->delete();

                $approverNames = [
                    strtolower(trim($row[5] ?? '')),
                    strtolower(trim($row[6] ?? '')),
                    strtolower(trim($row[7] ?? '')),
                ];

                $step = 1;
                foreach ($approverNames as $nameAppr) {
                    if (empty($nameAppr) || $nameAppr === '-') continue;

                    if (isset($this->usersByName[$nameAppr])) {
                        ApprovalLine::create([
                            'user_id'     => $karyawanId,
                            'approver_id' => $this->usersByName[$nameAppr],
                            'step'        => $step
                        ]);
                        $step++;
                    }
                }
            }

            if ($processedCount === 0) {
                throw ValidationException::withMessages([
                    'file' => "File Excel terbaca, namun TIDAK ADA data Email karyawan yang cocok dengan database sistem."
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
