<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Exports\ApprovalLinesExport;
use App\Http\Controllers\Api\Controller as ApiController;
use App\Imports\ApprovalLinesImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ApprovalLineBulkController extends ApiController
{
    public function index()
    {
        $users = User::with(['employee.department', 'approvalLines.approver', 'approvalDelegation.delegateTo'])
            ->whereHas('employee')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $users
        ]);
    }

    public function export()
    {
        return Excel::download(new ApprovalLinesExport, 'Template_Jalur_Approval.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:5120'
        ]);

        try {
            Excel::import(new ApprovalLinesImport, $request->file('file'));

            return response()->json([
                'success' => true,
                'message' => 'Data Approver berhasil diperbarui!'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimpor file: Terjadi kesalahan pada sistem.'
            ], 500);
        }
    }
}
