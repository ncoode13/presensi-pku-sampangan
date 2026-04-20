<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class DiklatAttendanceImport implements ToCollection
{
    public $userIds = [];
    public $notFoundNames = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            // Skip baris pertama (header)
            if ($index === 0) continue;

            // Karena file kamu pakai spasi sebagai delimiter, 
            // library memecah "Ini Direktur" jadi array ["Ini", "Direktur"]
            // Kita gabungkan lagi jadi satu string utuh
            $rowData = $row->filter()->toArray(); 
            $fullName = implode(' ', $rowData);

            if (empty($fullName) || strtolower($fullName) === 'Nama Karyawan') {
                continue;
            }

            $cleanName = trim($fullName);
            
            // Cari di database
            $user = User::where('name', 'ILIKE', $cleanName)->first();

            if ($user) {
                $this->userIds[] = (int) $user->id;
            } else {
                $this->notFoundNames[] = $cleanName;
            }
        }
    }
}