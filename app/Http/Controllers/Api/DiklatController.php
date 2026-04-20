<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller as ApiController;
use App\Models\DiklatAttendance;
use App\Models\DiklatCategory;
use App\Models\DiklatEvent;
use App\Models\User;
use App\Models\DiklatSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Exports\DiklatYearlyExport;
use Maatwebsite\Excel\Facades\Excel;

class DiklatController extends ApiController
{
    public function index(Request $request)
    {
        $year = $request->query('year', now()->year);
        $search = $request->query('search');
        $perPage = $request->query('per_page', 10);

        $globalTarget = (int) \App\Models\DiklatSetting::where('key', 'target_jpl_tahunan')
            ->value('value') ?? 20;

        $query = User::query()
            ->with(['employee.department'])
            ->withSum(['diklatAttendances as total_jpl' => function ($q) use ($year) {
                $q->whereHas('event', function ($ev) use ($year) {
                    $ev->whereYear('date', $year);
                })->join('diklat_events', 'diklat_attendances.diklat_event_id', '=', 'diklat_events.id')
                    ->select(\DB::raw('COALESCE(sum(diklat_events.jpl), 0)'));
            }], 'id')
            ->orderBy('name', 'asc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhereHas('employee.department', function ($d) use ($search) {
                        $d->where('name', 'ILIKE', "%{$search}%");
                    });
            });
        }

        $users = $query->paginate($perPage);

        $users->getCollection()->transform(function ($user) use ($globalTarget) {
            $totalJpl = (int) $user->total_jpl;
            $percentage = $globalTarget > 0 ? round(($totalJpl / $globalTarget) * 100, 1) : 0;

            return [
                'id' => $user->id,
                'name' => $user->name,
                'department' => $user->employee->department->name ?? '-',
                'total_jpl' => $totalJpl,
                'target_jpl' => $globalTarget,
                'percentage' => $percentage,
                'is_eligible' => $percentage == 100
            ];
        });

        return $this->respondSuccess($users);
    }

    public function getAllUserIds(Request $request)
    {
        $search = $request->query('search');

        $query = User::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhereHas('employee', function ($e) use ($search) {
                        $e->where('nip', 'ILIKE', "%{$search}%");
                    });
            });
        }

        $allIds = $query->pluck('id')->toArray();

        return $this->respondSuccess($allIds);
    }

    public function getCategories()
    {
        return $this->respondSuccess(DiklatCategory::all());
    }


    public function storeCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:diklat_categories,name',
            'target_jpl_year' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) return $this->respondError($validator->errors()->first(), 422);

        $category = DiklatCategory::create($request->all());
        return $this->respondSuccess($category, 'Kategori baru berhasil ditambahkan.');
    }

    public function updateCategory(Request $request, $id)
    {
        $category = DiklatCategory::find($id);
        if (!$category) return $this->respondError('Kategori tidak ditemukan');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:diklat_categories,name,' . $id,
            'target_jpl_year' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) return $this->respondError($validator->errors()->first(), 422);

        $category->update($request->all());
        return $this->respondSuccess($category, 'Kategori berhasil diperbarui.');
    }

    public function deleteCategory($id)
    {
        $category = DiklatCategory::find($id);
        if (!$category) return $this->respondError('Kategori tidak ditemukan');

        $isUsed = \App\Models\DiklatEvent::where('diklat_category_id', $id)->exists();
        if ($isUsed) {
            return $this->respondError('Kategori tidak bisa dihapus karena masih digunakan oleh beberapa event diklat.', 422);
        }

        $category->delete();
        return $this->respondSuccess(null, 'Kategori berhasil dihapus.');
    }

    public function updateGlobalTarget(Request $request)
    {
        $request->validate([
            'value' => 'required|integer|min:1'
        ], [
            'value.required' => 'Nilai target harus diisi',
            'value.integer' => 'Target harus berupa angka',
            'value.min' => 'Target minimal adalah 1 JPL',
        ]);

        $setting = DiklatSetting::updateOrCreate(
            ['key' => 'target_jpl_tahunan'],
            ['value' => $request->value]
        );

        return $this->respondSuccess(
            $setting,
            'Target JPL tahunan berhasil diperbarui menjadi ' . $request->value . ' JPL.'
        );
    }

    public function getGlobalTarget()
    {
        $setting = DiklatSetting::firstOrCreate(
            ['key' => 'target_jpl_tahunan'],
            ['value' => 20]
        );

        return $this->respondSuccess($setting);
    }

    public function export(Request $request)
    {
        $year = $request->query('year', now()->year);
        $fileName = 'Rekap_JPL_Karyawan_' . $year . '.xlsx';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        return Excel::download(new DiklatYearlyExport((int)$year), $fileName);
    }
}
