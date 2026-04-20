<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller as ApiController;
use App\Models\TimeOff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class TimeOffController extends ApiController
{
    public function index()
    {

        $timeOffs = TimeOff::orderBy('is_active', 'desc')->orderBy('name', 'asc')->get();
        return $this->respondSuccess($timeOffs);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|unique:time_offs,name',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->respondError($validator->errors()->first(), 422);
        }

        try {
            $timeOff = TimeOff::create([
                'name' => $request->name,
                'is_active' => $request->input('is_active', true),
            ]);

            return $this->respondSuccess($timeOff, 'Jenis cuti berhasil ditambahkan.');
        } catch (\Throwable $th) {
            return $this->respondError($th->getMessage());
        }
    }

    public function show($id)
    {
        $timeOff = TimeOff::find($id);
        if (!$timeOff) return $this->respondError('Data tidak ditemukan', 404);

        return $this->respondSuccess($timeOff);
    }

    public function update(Request $request, $id)
    {
        $timeOff = TimeOff::find($id);
        if (!$timeOff) return $this->respondError('Data tidak ditemukan', 404);

        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:100|unique:time_offs,name,' . $id,
            'is_active' => 'boolean', 
        ]);

        if ($validator->fails()) {
            return $this->respondError($validator->errors()->first(), 422);
        }

        try {
            
            $payload = [
                'name' => $request->name,
            ];

            if ($request->has('is_active')) {
                $payload['is_active'] = $request->boolean('is_active');
            }

            $timeOff->update($payload);

            return $this->respondSuccess($timeOff, 'Jenis cuti berhasil diperbarui.');
        } catch (\Throwable $th) {
            return $this->respondError($th->getMessage());
        }
    }

    public function destroy($id)
    {
        $timeOff = TimeOff::find($id);
        if (!$timeOff) return $this->respondError('Data tidak ditemukan', 404);

        try {
            $timeOff->delete();
            return $this->respondSuccess(null, 'Jenis cuti berhasil dihapus.');
        } catch (\Throwable $th) {
            return $this->respondError($th->getMessage());
        }
    }
}
