<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use App\Models\ChangeShiftRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChangeShiftController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'date'         => 'required|date|after_or_equal:today',
            'shift_old_id' => 'required|exists:shifts,id',
            'shift_new_id' => 'required|exists:shifts,id|different:shift_old_id',
            'reason'       => 'required|string'
        ]);

        $exists = ChangeShiftRequest::where('user_id', Auth::id())
            ->where('date', $request->date)
            ->where('status', 'pending')
            ->exists();

        if ($exists) {
            return $this->respondError('Anda sudah mengajukan tukar shift untuk tanggal ini');
        }

        $data = $request->all();
        $data['user_id'] = Auth::id();
        $data['status'] = 'pending';

        $submission = ChangeShiftRequest::create($data);


        return $this->respondSuccess($submission, 'Pengajuan tukar shift berhasil');
    }

    public function approveLevel1($id)
    {
        $submission = ChangeShiftRequest::findOrFail($id);

        if ($submission->status != 'pending') {
            return $this->respondError('Pengajuan tidak valid atau sudah diproses');
        }

        $submission->update([
            'approved_1_by' => Auth::id(),
            'approved_1_at' => now()
        ]);

        return $this->respondSuccess($submission, 'Disetujui tahap 1 menunggu persetujuan Direktur');
    }

    public function approveLevel2($id)
    {
        $submission = ChangeShiftRequest::findOrFail($id);

        if (!$submission->approved_1_at) {
            return $this->respondError('Harus disetujui dulu oleh HRD (Tahap 1)');
        }

        if ($submission->status != 'pending') {
            return $this->respondError('Pengajuan sudah selesai diproses');
        }

        $submission->update([
            'approved_2_by' => Auth::id(),
            'approved_2_at' => now(),
            'status'        => 'approved'
        ]);

        return $this->respondSuccess($submission, 'Tukar shift telah disetujui');
    }

    public function reject($id)
    {
        $submission = ChangeShiftRequest::findOrFail($id);

        if ($submission->status != 'pending') {
            return $this->respondError('Tidak bisa menolak pengajuan yang sudah selesai');
        }

        $submission->update(['status' => 'rejected']);

        return $this->respondError('Pengajuan Ditolak');
    }

    public function myRequests()
    {
        $data = ChangeShiftRequest::where('user_id', Auth::id())
            ->with(['oldShift', 'newShift'])->latest()->get();
        return $this->respondSuccess($data);
    }

    public function pendingRequests()
    {
        $data = ChangeShiftRequest::where('status', 'pending')
            ->with(['user', 'oldShift', 'newShift'])->latest()->get();
        return $this->respondSuccess($data);
    }
}
