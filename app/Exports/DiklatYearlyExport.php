<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DiklatYearlyExport implements WithMultipleSheets
{
    protected $year;

    public function __construct(int $year)
    {
        $this->year = $year;
    }

    public function sheets(): array
    {
        // return [
        //     // Sheet 1: Filter untuk 'office'
        //     new DiklatCategorySheet($this->year, 'Karyawan Office', 'office'),
        //     // Sheet 2: Filter untuk 'shift'
        //     new DiklatCategorySheet($this->year, 'Karyawan Shift', 'shift'),
        // ];

        return [
            new DiklatCategorySheet($this->year, 'Civitas', 'civitas'),
            new DiklatCategorySheet($this->year, 'dr. Spesialis', 'spesialis'),
            new DiklatCategorySheet($this->year, 'Cleaning Service', 'cleaning'),
            new DiklatCategorySheet($this->year, 'Magang', 'magang'),
        ];
    }
}