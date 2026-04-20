<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller as ApiController;
use App\Http\Resources\ShiftResource;
use App\Http\Resources\ShiftSubmissionResource;
use App\Models\Shift;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ShiftController extends ApiController
{
    public function index()
    {
        $shifts = Shift::latest()->get();
        return $this->respondSuccess(ShiftResource::collection($shifts));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'                    => 'required|string|max:50',
            'start_time'              => 'required|date_format:H:i',
            'end_time'                => 'required|date_format:H:i',
            'tolerance_come_too_late' => 'nullable|integer|min:0',
            'tolerance_go_home_early' => 'nullable|integer|min:0',
        ], [
            'start_time.date_format' => 'Format jam mulai harus JJ:MM (Contoh: 08:00)',
            'end_time.date_format'   => 'Format jam selesai harus JJ:MM (Contoh: 17:00)',
        ]);

        if ($validator->fails()) {
            return $this->respondError($validator->errors()->first(), 422);
        }

        try {
            $shift = Shift::create($request->all());
            return $this->respondSuccess(new ShiftSubmissionResource($shift), 'Shift berhasil dibuat.');
        } catch (\Throwable $th) {
            return $this->respondError($th->getMessage());
        }
    }

    public function show($id)
    {
        $shift = Shift::find($id);
        if (!$shift) return $this->respondError('Shift tidak ditemukan', 404);

        return $this->respondSuccess(new ShiftResource($shift));
    }

    public function update(Request $request, $id)
    {
        $shift = Shift::find($id);
        if (!$shift) return $this->respondError('Shift tidak ditemukan', 404);

        $validator = Validator::make($request->all(), [
            'name'                    => 'required|string|max:50',
            'start_time'              => 'required|date_format:H:i',
            'end_time'                => 'required|date_format:H:i',
            'tolerance_come_too_late' => 'nullable|integer|min:0',
            'tolerance_go_home_early' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->respondError($validator->errors()->first(), 422);
        }

        try {
            $shift->update($request->all());
            return $this->respondSuccess(new ShiftSubmissionResource($shift), 'Shift berhasil diperbarui.');
        } catch (\Throwable $th) {
            return $this->respondError($th->getMessage());
        }
    }

    public function destroy($id)
    {
        $shift = Shift::find($id);
        if (!$shift) return $this->respondError('Shift tidak ditemukan', 404);

        $isUsedByEmployee = Employee::where('shift_id', $id)->exists();

        if ($isUsedByEmployee) {
            return $this->respondError('Gagal menghapus! Masih ada karyawan yang menggunakan shift ini. Silakan pindahkan shift karyawan terlebih dahulu.', 409);
        }

        $isUsedByRequest = \App\Models\ShiftSubmission::where(function ($q) use ($id) {
            $q->where('shift_new_id', $id)
                ->orWhere('shift_old_id', $id);
        })
            ->where('status', 'pending')
            ->exists();

        if ($isUsedByRequest) {
            return $this->respondError('Gagal menghapus! Ada pengajuan tukar shift yang sedang mengarah ke shift ini.', 409);
        }

        try {
            $shift->delete();
            return $this->respondSuccess(null, 'Shift berhasil dihapus.');
        } catch (\Throwable $th) {
            return $this->respondError($th->getMessage());
        }
    }
}
