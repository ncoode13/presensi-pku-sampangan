<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller as ApiController;
use App\Http\Controllers\Controller;
use App\Models\ApprovalLine;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ApprovalLineController extends ApiController
{
    public function show($userId)
    {
        $user = User::findOrFail($userId);

        $approvalLines = ApprovalLine::with('approver:id,name,role')
            ->where('user_id', $userId)
            ->orderBy('step', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => "Data Approver untuk {$user->name}",
            'data'    => $approvalLines
        ]);
    }

    public function update(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        $validator = Validator::make($request->all(), [
            'approvers'   => 'required|array',
            'approvers.*' => 'required|exists:users,id|different:' . $userId
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            DB::beginTransaction();

            ApprovalLine::where('user_id', $userId)->delete();

            $step = 1;
            foreach ($request->approvers as $approverId) {
                ApprovalLine::create([
                    'user_id'     => $userId,
                    'approver_id' => $approverId,
                    'step'        => $step
                ]);
                $step++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Jalur persetujuan untuk {$user->name} berhasil diperbarui."
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan jalur persetujuan: ' . $e->getMessage()
            ], 500);
        }
    }
}
