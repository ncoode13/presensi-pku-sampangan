<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller as ApiController;
use App\Http\Resources\EmployeeResource;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use App\Models\Personal;
use App\Models\EmergencyContact;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Services\ImageService;
use Illuminate\Support\Facades\Storage;
use App\Events\UserCreated;

class EmployeeController extends ApiController
{
    public function index(Request $request)
    {   
        $limit = $request->query('limit', 10);
        $keyword = $request->query('search');
        $currentUser = Auth::user();

        $query = Employee::query()
            ->with([
                'user.personal.maritalStatus',
                'user.personal.emergencyContact.relationship',
                'department',
                'position',
                'job_level',
                'shift',
                'employment_status'
            ]);

        if ($currentUser->role === 'superadmin') {
        } else if (in_array($currentUser->role, ['admin', 'user'])) {
            $query->whereHas('user', function ($q) use ($currentUser) {
                $q->whereIn('id', function ($subQuery) {
                    $subQuery->select('user_id')
                        ->from('approval_lines')
                        ->where('approver_id', Auth::id());
                });
            });
        }

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('nip', 'LIKE', '%' . $keyword . '%')
                    ->orWhereHas('user', function ($subQuery) use ($keyword) {
                        $subQuery->where('name', 'LIKE', '%' . $keyword . '%')
                            ->orWhere('email', 'LIKE', '%' . $keyword . '%');
                    });
            });
        }

        $employees = $query->orderBy('created_at', 'desc')
            ->paginate($limit);

        return $this->respondSuccess(
            EmployeeResource::collection($employees)->response()->getData(true)
        );
    }

    public function store(Request $request, ImageService $imageService)
    {
        if (!in_array(Auth::user()->role, ['admin', 'superadmin'])) {
            return $this->respondError('You do not have access to create data', 403);
        }

        $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'place_of_birth' => 'required|string',
            'birth_date' => 'required|date',
            'gender' => 'required|string',
            'marital_status' => 'required',
            'blood_type' => 'required',
            'religion' => 'required',
            'phone' => 'required',
            'nik' => 'required',
            'npwp' => 'required',
            'postal_code' => 'required',
            'address' => 'required',

            'department_id' => 'required|exists:departments,id',
            'position_id' => 'required|exists:positions,id',
            'job_level_id' => 'required|exists:job_levels,id',
            'shift_id' => 'required|exists:shifts,id',
            'employment_status_id' => 'required|exists:employment_statuses,id',
            'work_scheme'   => 'required|in:office,shift',
            'nip' => 'required|unique:employees,nip',
            'join_date' => 'required|date',
            'end_date' => 'nullable|date',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'is_ppa' => 'required|boolean',

            'group'  => 'required|string',
            'rank'   => 'required|string',

            'emergency_contact_name' => 'required|string',
            'emergency_contact_phone' => 'required|string',
            'emergency_contact_relationship_id' => 'required|exists:relationships,id',
        ]);

        $photoPath = null;
        $avatarPath = null;

        try {
            if ($request->hasFile('photo')) {

                $uploaded = $imageService->uploadProfileWithAvatar(
                    $request->file('photo'),
                    'employees'
                );

                $photoPath = $uploaded['photo'];
                $avatarPath = $uploaded['avatar'];
            }
        } catch (\Throwable $e) {
            return $this->respondError('Gagal memproses gambar: ' . $e->getMessage(), 500);
        }

        try {
            $defaultPassword = "user1234";

            $result = DB::transaction(function () use ($request, $photoPath, $avatarPath, $defaultPassword) {

                $user = User::create([
                    'name' => $request->first_name . ' ' . $request->last_name,
                    'username' => Str::slug($request->first_name . $request->last_name) . rand(100, 999),
                    'email' => $request->email,
                    'password' => Hash::make($defaultPassword),
                    'role' => 'user'
                ]);

                $personal = Personal::create([
                    'user_id' => $user->id,
                    'email' => $request->email,
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'place_of_birth' => $request->place_of_birth,
                    'birth_date' => $request->birth_date,
                    'gender' => $request->gender,
                    'marital_status' => $request->marital_status,
                    'blood_type' => $request->blood_type,
                    'religion' => $request->religion,
                    'phone' => $request->phone,
                    'nik' => $request->nik,
                    'npwp' => $request->npwp,
                    'postal_code' => $request->postal_code,
                    'address' => $request->address,
                ]);

                EmergencyContact::create([
                    'personal_id' => $personal->id,
                    'name' => $request->emergency_contact_name,
                    'phone' => $request->emergency_contact_phone,
                    'relationship_id' => $request->emergency_contact_relationship_id,
                ]);

                $employee = Employee::create([
                    'user_id'       => $user->id,
                    'nip'           => $request->nip,
                    'employee_id'   => 'EMP-' . date('Y') . '-' . strtoupper(Str::random(4)),
                    'department_id' => $request->department_id,
                    'position_id'   => $request->position_id,
                    'job_level_id'  => $request->job_level_id,
                    'shift_id'      => $request->shift_id,
                    'employment_status_id' => $request->employment_status_id,
                    'work_scheme' => $request->work_scheme,
                    'join_date'     => $request->join_date,
                    'end_date'      => $request->end_date,
                    'photo'         => $photoPath,
                    'avatar'        => $avatarPath,
                    'is_ppa'        => $request->is_ppa,
                    'unit_id'       => $request->unit_id,
                    'group'         => $request->group,
                    'rank'          => $request->rank,

                ]);

                return $employee->load([
                    'user.personal.emergencyContact.relationship',
                    'department',
                    'position',
                    'job_level',
                    'shift',
                    'employment_status'
                ]);
            });

            event(new UserCreated($result->user, $defaultPassword));

            return $this->respondSuccess(new EmployeeResource($result), 'Data pegawai berhasil ditambahkan');
        } catch (\Throwable $th) {
            return $this->respondError('Gagal menyimpan data ke database: ' . $th->getMessage(), 500);
        }
    }

    public function update(Request $request, $id, ImageService $imageService)
    {
        // 1. Pengecekan Akses (Hanya Admin / Superadmin / Director / SDI yang bisa update)
        if (!in_array(auth()->user()->role, ['admin', 'superadmin', 'director'])) {
            return $this->respondError('You do not have access to update data', 403);
        }

        $employee = Employee::with('user.personal')->find($id);

        if (!$employee) {
            return $this->respondError('Data pegawai tidak ditemukan', 404);
        }

        $request->validate([
            'first_name'     => 'sometimes|string',
            'last_name'      => 'sometimes|string',
            'email'          => 'sometimes|email|unique:users,email,' . $employee->user_id,
            'place_of_birth' => 'sometimes|string',
            'birth_date'     => 'sometimes|date',
            'gender'         => 'sometimes|string',

            'emergency_contact_name' => 'sometimes|string',
            'emergency_contact_phone' => 'sometimes|string',
            'emergency_contact_relationship_id' => 'sometimes|exists:relationships,id',

            'department_id'        => 'sometimes|exists:departments,id',
            'position_id'          => 'sometimes|exists:positions,id',
            'job_level_id'         => 'sometimes|exists:job_levels,id',
            'shift_id'             => 'sometimes|exists:shifts,id',
            'employment_status_id' => 'sometimes|exists:employment_statuses,id',
            'work_scheme'          => 'required|in:office,shift',
            'nip'                  => 'sometimes|unique:employees,nip,' . $id,
            'join_date'            => 'sometimes|date',
            'end_date'             => 'nullable|date',

            'photo'                => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'attachment'           => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',

            'is_ppa'               => 'sometimes|boolean',
            'group'                => 'sometimes|string',
            'rank'                 => 'sometimes|string',
        ]);

        try {
            $result = DB::transaction(function () use ($request, $employee, $imageService) {

                $userData = [];
                if ($request->has('first_name') || $request->has('last_name')) {
                    $firstName = $request->first_name ?? $employee->user->personal->first_name;
                    $lastName = $request->last_name ?? $employee->user->personal->last_name;
                    $userData['name'] = $firstName . ' ' . $lastName;
                }
                if ($request->has('email')) {
                    $userData['email'] = $request->email;
                }
                if (!empty($userData)) {
                    $employee->user->update($userData);
                }

                $personalFields = [
                    'email',
                    'first_name',
                    'last_name',
                    'place_of_birth',
                    'birth_date',
                    'gender',
                    'marital_status',
                    'blood_type',
                    'religion',
                    'phone',
                    'nik',
                    'npwp',
                    'postal_code',
                    'address'
                ];
                $personalDataToUpdate = $request->only($personalFields);

                $fieldsToIgnore = ['npwp', 'nik', 'address', 'postal_code', 'phone', 'place_of_birth'];
                foreach ($fieldsToIgnore as $field) {
                    if (isset($personalDataToUpdate[$field])) {
                        if ($personalDataToUpdate[$field] === '-' || trim($personalDataToUpdate[$field]) === '' || is_null($personalDataToUpdate[$field])) {
                            unset($personalDataToUpdate[$field]);
                        }
                    }
                }

                if (!empty($personalDataToUpdate) && $employee->user->personal) {
                    $employee->user->personal->update($personalDataToUpdate);
                }

                if ($employee->user->personal) {
                    $ecData = [];
                    if ($request->has('emergency_contact_name')) $ecData['name'] = $request->emergency_contact_name;
                    if ($request->has('emergency_contact_phone')) $ecData['phone'] = $request->emergency_contact_phone;
                    if ($request->has('emergency_contact_relationship_id')) $ecData['relationship_id'] = $request->emergency_contact_relationship_id;

                    if (!empty($ecData)) {
                        EmergencyContact::updateOrCreate(
                            ['personal_id' => $employee->user->personal->id],
                            $ecData
                        );
                    }
                }

                $employeeFields = [
                    'nip',
                    'department_id',
                    'position_id',
                    'job_level_id',
                    'shift_id',
                    'employment_status_id',
                    'join_date',
                    'end_date',
                    'is_ppa',
                    'work_scheme',
                    'group',
                    'rank'
                ];
                $employeeDataToUpdate = $request->only($employeeFields);

                if ($request->hasFile('photo')) {
                    $uploaded = $imageService->uploadProfileWithAvatar(
                        $request->file('photo'),
                        'employees'
                    );

                    $employeeDataToUpdate['photo'] = $uploaded['photo'];
                    $employeeDataToUpdate['avatar'] = $uploaded['avatar'];

                    if ($employee->photo && Storage::disk('public')->exists($employee->photo)) {
                        Storage::disk('public')->delete($employee->photo);
                    }
                    if ($employee->avatar && Storage::disk('public')->exists($employee->avatar)) {
                        Storage::disk('public')->delete($employee->avatar);
                    }
                }

                if ($request->hasFile('attachment')) {
                    $path = $imageService->compressAttachment(
                        $request->file('attachment'),
                        'employee_attachments'
                    );

                    if ($employee->attachment && Storage::disk('public')->exists($employee->attachment)) {
                        Storage::disk('public')->delete($employee->attachment);
                    }

                    $employeeDataToUpdate['attachment'] = $path;
                }

                if (!empty($employeeDataToUpdate)) {
                    $employee->update($employeeDataToUpdate);
                }

                return $employee->load([
                    'user.personal.emergencyContact.relationship',
                    'department',
                    'position',
                    'job_level',
                    'shift',
                    'employment_status'
                ]);
            });

            return $this->respondSuccess(new EmployeeResource($result), 'Data pegawai berhasil diperbarui');
        } catch (\Throwable $th) {
            return $this->respondError('Gagal memperbarui data: ' . $th->getMessage(), 500);
        }
    }

    public function show($id)
    {
        $employee = Employee::with([
            'user.personal.emergencyContact.relationship',
            'department',
            'position',
            'job_level',
            'shift',
            'employment_status'
        ])->find($id);

        if (!$employee) {
            return $this->respondError('Data pegawai tidak ditemukan', 404);
        }

        return $this->respondSuccess(new EmployeeResource($employee));
    }

    public function me()
    {
        $user = auth()->user();

        if (!$user) {
            return $this->respondError('Unauthorized', 401);
        }

        $employee = Employee::with([
            'user.personal.emergencyContact.relationship',
            'department',
            'position',
            'job_level',
            'shift',
            'employment_status'
        ])
            ->where('user_id', $user->id)
            ->first();

        if (!$employee) {
            return $this->respondError('Data kepegawaian belum tersedia untuk akun ini.', 404);
        }

        $attendance = Attendance::with('logs')
            ->where('user_id', $user->id)
            ->whereDate('date', now()->format('Y-m-d'))
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

        return $this->respondSuccess(new EmployeeResource($employee));
    }

    public function generateAccount(Request $request, $id)
    {
        if (!in_array(auth()->user()->role, ['admin', 'superadmin'])) {
            return $this->respondError('You do not have access to generate account', 403);
        }

        $employee = Employee::with(['user.personal'])->findOrFail($id);

        if (str_contains($employee->user->email, 'pku_')) {
            return $this->respondError('Akun ini sudah aktif/sudah digenerate sebelumnya.', 400);
        }

        $emailFromPersonal = $employee->user->personal ? $employee->user->personal->email : null;
        $emailFix = $request->email ?? $emailFromPersonal;

        if (!$emailFix) {
            return $this->respondError('Email tidak ditemukan. Mohon update data Personal atau input email manual', 422);
        }

        $emailExist = User::where('email', $emailFix)
            ->where('id', '!=', $employee->user_id)
            ->exists();

        if ($emailExist) {
            return $this->respondError('Email ' . $emailFix . ' sudah digunakan user lain.', 422);
        }

        try {
            DB::transaction(function () use ($employee, $emailFix) {
                $employee->user->update([
                    'email'    => $emailFix,
                    'username' => $employee->nip,
                    'password' => Hash::make($employee->nip),
                ]);
            });

            return $this->respondSuccess([
                'name' => $employee->user->name,
                'email' => $emailFix,
                'username' => $employee->nip,
                'default_password' => $employee->nip,
            ], 'Akun berhasil diaktifkan.');
        } catch (\Throwable $th) {
            return $this->respondError('Gagal mengaktifkan akun: ' . $th->getMessage(), 500);
        }
    }

    // Tambahkan method ini di bawah method generateAccount()

    public function getOptions()
    {
        // Ambil data karyawan seringan mungkin (hanya ID, User ID, NIP, dan Relasi ke tabel User untuk nama)
        $employees = Employee::select('id', 'user_id', 'nip')
            ->with(['user:id,name']) // Hanya panggil kolom id dan name dari tabel users
            ->get()
            ->map(function ($emp) {
                return [
                    'user_id'   => $emp->user_id,
                    'id'        => $emp->id,
                    'nip'       => $emp->nip,
                    'full_name' => $emp->user ? $emp->user->name : 'Unknown', // Ambil nama dari relasi user
                ];
            });

        return $this->respondSuccess($employees, 'Berhasil mengambil data opsi karyawan');
    }
    // <-- Ini kurung tutup terakhir dari class EmployeeController
}
