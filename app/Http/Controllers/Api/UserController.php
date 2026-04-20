<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller as ApiController;
use App\Http\Resources\EmployeeResource;
use App\Models\Attendance;
use App\Models\User;
use App\Models\Personal;
use App\Models\Employee;
use App\Models\ShiftSubmission;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends ApiController
{
    public function show($id)
    {
        $user = User::with('personal', 'employee')->find($id);

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User tidak ditemukan'], 404);
        }

        return response()->json(['success' => true, 'data' => $user]);
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User tidak ditemukan'], 404);
        }

        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email,' . $id,
            'role' => 'required|in:admin,superadmin,user',
            'password' => 'nullable|min:6'
        ]);

        try {
            DB::transaction(function () use ($request, $user) {
                $dataToUpdate = [
                    'name' => $request->name,
                    'email' => $request->email,
                    'role' => $request->role,
                ];

                if ($request->filled('password')) {
                    $dataToUpdate['password'] = Hash::make($request->password);
                }

                $user->update($dataToUpdate);
            });

            return response()->json([
                'success' => true,
                'message' => 'Data user berhasil diperbarui',
                'data' => $user
            ]);
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => 'Gagal update: ' . $th->getMessage()], 500);
        }
    }

    public function updateUserRole(Request $request, $id)
    {
        $currentUser = auth()->user();

        if ($currentUser->role !== 'superadmin') {
            return response()->json(['success' => false, 'message' => 'Fitur ini hanya tersedia untuk superadmin'], 403);
        }

        $targetUser = User::find($id);

        if (!$targetUser) {
            return response()->json(['success' => false, 'message' => 'User tidak ditemukan'], 404);
        }

        $request->validate([
            'role' => 'required|in:admin,superadmin,user',
        ]);

        try {
            $oldRole = $targetUser->role;

            $targetUser->update(['role' => $request->role]);

            return response()->json([
                'success' => true,
                'message' => 'Role user berhasil diubah dari ' . $oldRole . ' menjadi ' . $request->role,
                'data' => [
                    'user_id' => $targetUser->id,
                    'name' => $targetUser->name,
                    'email' => $targetUser->email,
                    'old_role' => $oldRole,
                    'new_role' => $targetUser->role,
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => 'Gagal update role: ' . $th->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User tidak ditemukan'], 404);
        }

        try {
            $user->delete();
            return response()->json(['success' => true, 'message' => 'User berhasil dihapus']);
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => 'Gagal hapus: ' . $th->getMessage()], 500);
        }
    }

    public function updateProfile(Request $request, ImageService $imageService)
    {
        $user = auth()->user();

        $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'required|string',
            'address' => 'required|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        try {

            DB::transaction(function () use ($request, $user) {

                $fullName = $request->first_name . ' ' . $request->last_name;
                $user->update([
                    'email' => $request->email,
                    'name' => $fullName
                ]);

                Personal::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'email' => $request->email,
                        'first_name' => $request->first_name,
                        'last_name' => $request->last_name,
                        'phone' => $request->phone,
                        'address' => $request->address,
                    ]
                );


                $employee = Employee::where('user_id', $user->id)->first();

                if ($employee && $request->hasFile('photo')) {

                    $imageService = new ImageService();

                    $uploaded = $imageService->uploadProfileWithAvatar(
                        $request->file('photo'),
                        'employees'
                    );

                    if ($employee->photo && Storage::disk('public')->exists($employee->photo)) {
                        Storage::disk('public')->delete($employee->photo);
                    }
                    if ($employee->avatar && Storage::disk('public')->exists($employee->avatar)) {
                        Storage::disk('public')->delete($employee->avatar);
                    }

                    $employee->update([
                        'photo' => $uploaded['photo'],
                        'avatar' => $uploaded['avatar']
                    ]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Profil berhasil diperbarui',
                'data' => new EmployeeResource($user->employee->load('user.personal'))
            ]);
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => 'Gagal update: ' . $th->getMessage()], 500);
        }
    }
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Password lama salah'], 400);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return response()->json(['success' => true, 'message' => 'Password berhasil diubah']);
    }

    public function resetPasswordByAdmin(Request $request, $id)
    {
        $currentUser = auth()->user();

        if (!in_array($currentUser->role, ['admin', 'superadmin'])) {
            return $this->respondError('Anda tidak memiliki akses. Fitur ini khusus admin');
        }

        $targetUser = User::find($id);

        if (!$targetUser) {
            return response()->json(['success' => false, 'message' => 'User tidak ditemukan.'], 404);
        }

        try {
            $defaultPassword = 'user1234';

            $targetUser->update([
                'password' => Hash::make($defaultPassword)
            ]);

            return $this->respondSuccess($defaultPassword, 'Password user berhasil direset');
        } catch (\Throwable $th) {
            return $this->respondError('Gagal reset password');
        }
    }

    public function me()
    {
        $user = auth()->user();

        if (!$user) {
            return $this->respondError('Unauthorized', 401);
        }

        $employee = Employee::with([
            'user.personal.maritalStatus', // Tambahkan ini
            'user.personal.emergencyContact.relationship', // Tambahkan ini
            'personal.maritalStatus', // Tambahkan ini (berjaga-jaga jika pakai $this->personal)
            'personal.emergencyContact.relationship', // Tambahkan ini
            'department',
            'position',
            'job_level',
            'shift',
            'employment_status',
        ])
            ->where('user_id', $user->id)
            ->first();

        if (!$employee) {
            return $this->respondError('Data kepegawaian belum tersedia untuk akun ini.', 404);
        }

        // OPTIMASI 1: Panggil tanggal cukup 1 kali dengan format toDateString() yang lebih cepat
        $today = now()->toDateString();

        $shiftSubmission = ShiftSubmission::where('user_id', $user->id)
            ->where('date', $today)
            ->where('status', 'approved')
            ->with('targetShift')
            ->first();

        if ($shiftSubmission && $shiftSubmission->targetShift) {
            $employee->setRelation('shift', $shiftSubmission->targetShift);
        }

        // OPTIMASI 2: Ambil log absen TAPI hanya kolom yang dibutuhkan saja agar hemat RAM Server
        $attendance = Attendance::with(['logs' => function ($query) {
            // Wajib menyertakan id dan attendance_id agar relasi Laravel tidak error
            $query->select('id', 'attendance_id', 'attendance_type', 'time');
        }])
            ->where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        $attendanceToday = null;

        if ($attendance) {
            $checkInLog  = $attendance->logs->firstWhere('attendance_type', 'check_in');
            $checkOutLog = $attendance->logs->firstWhere('attendance_type', 'check_out');

            $attendanceToday = [
                'id'        => $attendance->id,
                'status'    => (int)$attendance->status,
                'date'      => $attendance->date,
                'clock_in'  => $checkInLog ? $checkInLog->time : null,
                'clock_out' => $checkOutLog ? $checkOutLog->time : null,
            ];
        }

        $employee->attendance_today = $attendanceToday;

        $employee->office_locations = \App\Models\AttendanceLocation::select('id', 'name', 'latitude', 'longitude', 'radius')->get();

        return $this->respondSuccess(new EmployeeResource($employee));
    }
}
