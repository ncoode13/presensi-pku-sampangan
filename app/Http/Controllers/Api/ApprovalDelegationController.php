<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller as ApiController;
use App\Models\ApprovalDelegation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApprovalDelegationController extends ApiController
{
    public function show($userId)
    {
        $delegation = ApprovalDelegation::with('delegateTo:id,name')
            ->where('user_id', $userId)
            ->first();

        return $this->respondSuccess($delegation);
    }

    /**
     * Simpan atau Update Delegasi (Support POST & PUT)
     */
    public function store(Request $request)
    {
        return $this->saveOrUpdate($request);
    }

    public function update(Request $request, $id)
    {
        $request->merge(['user_id' => $id]);
        return $this->saveOrUpdate($request);
    }

    private function saveOrUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'delegate_to_id' => 'required|exists:users,id|different:user_id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return $this->respondError($validator->errors()->first(), 422);
        }

        $delegation = ApprovalDelegation::updateOrCreate(
            ['user_id' => $request->user_id],
            [
                'delegate_to_id' => $request->delegate_to_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'is_active' => true
            ]
        );

        return $this->respondSuccess($delegation, 'Data delegasi berhasil disimpan.');
    }

    public function destroy($userId)
    {
        ApprovalDelegation::where('user_id', $userId)->delete();
        return $this->respondSuccess(null, 'Delegasi berhasil dihapus.');
    }
}