<?php

namespace App\Http\Controllers\Api;

use App\Models\AttendanceLocation;
use Illuminate\Http\Request;
// use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Controller as ApiController;
use Illuminate\Support\Facades\Validator;

class AttendanceLocationController extends ApiController
{
    public function index() {
        $locations = AttendanceLocation::latest()->get();
        return response()->json([
            'status' => 'success',
            'data' => $locations
        ]);
    }

    public function store(Request $request) {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $location = AttendanceLocation::create([
            'name' => $request->name,
            'address' => $request->address,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'radius' => $request->radius,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Lokasi berhasil ditambahkan',
            'data' => $location
        ], 201);

    }

    public function show($id) {
        $location = AttendanceLocation::find($id);

        if($location) {
            return response()->json([
                'status' => 'success',
                'data' => $location
            ]);
        }
    }

    public function update(Request $request, $id) {
        $location = AttendanceLocation::find($id);
        if (!$location) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lokasi tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'address' => 'sometimes|required|string|max:500',
            'latitude' => 'sometimes|required|numeric',
            'longitude' => 'sometimes|required|numeric',
            'radius' => 'sometimes|required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $location->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Lokasi berhasil diperbarui',
            'data' => $location
        ]);
    }

    public function destroy($id) {
        $location = AttendanceLocation::find($id);
        if (!$location) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lokasi tidak ditemukan'
            ], 404);
        }

        $location->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Lokasi berhasil dihapus'
        ]);
    }
}
