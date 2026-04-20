<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller as ApiController;
use Illuminate\Http\Request;
use App\Exports\EmployeeBulkUpdateExport;
use App\Imports\EmployeeBulkUpdateImport;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeBulkUpdateController extends ApiController
{
    public function export(Request $request)
    {
        $request->validate([
            'employee_ids' => 'required|array',
            'category'     => 'required|in:general,emergency'
        ]);


        $category = $request->category;
        $employeeIds = $request->employee_ids;


        $kategori = ucfirst($category);
        $tanggal = date('d-M-Y'); // Hasil: 14-Mar-2026
        $fileName = "Bulk_Edit_Karyawan_{$kategori}_{$tanggal}.xlsx";

        return Excel::download(new EmployeeBulkUpdateExport($employeeIds, $category), $fileName);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file'     => 'required|file|mimes:xlsx,xls|max:5120',
            'category' => 'required|in:general,emergency'
        ]);

        try {
            Excel::import(new EmployeeBulkUpdateImport($request->category), $request->file('file'));

            return $this->respondSuccess(null, 'Data karyawan berhasil diupdate secara massal.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->respondError($e->getMessage(), 422);
        } catch (\Throwable $th) {
            return $this->respondError('Terjadi kesalahan saat import data: ' . $th->getMessage(), 500);
        }
    }
}
