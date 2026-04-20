<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller as ApiController;
use App\Models\DiklatEvent;
use App\Models\DiklatAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Imports\DiklatAttendanceImport;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelFormat;

class DiklatEventController extends ApiController
{
    public function index()
    {
        $events = DiklatEvent::with('category')
            ->withCount('attendances')
            ->latest('date')
            ->get();

        return $this->respondSuccess($events);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'diklat_category_id' => 'required|exists:diklat_categories,id',
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'jpl' => 'required|integer|min:1',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) return $this->respondError($validator->errors()->first(), 422);

        $event = DiklatEvent::create([
            'diklat_category_id' => $request->diklat_category_id,
            'title' => $request->title,
            'date' => $request->date,
            'jpl' => $request->jpl,
            'description' => $request->description,
            'status' => Carbon::parse($request->date)->isFuture() ? 'upcoming' : 'completed'
        ]);

        return $this->respondSuccess($event, 'Event berhasil dibuat.');
    }

    public function show($id)
    {
        $event = DiklatEvent::with('category')->find($id);
        if (!$event) return $this->respondError('Event tidak ditemukan');
        return $this->respondSuccess($event);
    }

    public function update(Request $request, $id)
    {
        $event = DiklatEvent::find($id);
        if (!$event) return $this->respondError('Event tidak ditemukan');

        $validator = Validator::make($request->all(), [
            'diklat_category_id' => 'required|exists:diklat_categories,id',
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'jpl' => 'required|integer|min:1',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) return $this->respondError($validator->errors()->first(), 422);

        $event->update($request->all());
        
        // Update status berdasarkan tanggal baru
        $event->update([
            'status' => Carbon::parse($request->date)->isFuture() ? 'upcoming' : 'completed'
        ]);

        return $this->respondSuccess($event, 'Event berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $event = DiklatEvent::find($id);
        if (!$event) return $this->respondError('Event tidak ditemukan');
        
        // Opsional: Cek jika sudah ada absensi, apakah boleh dihapus?
        // if ($event->attendances()->exists()) {
        //    return $this->respondError('Event tidak bisa dihapus karena sudah memiliki data absensi.');
        // }

        $event->delete();
        return $this->respondSuccess(null, 'Event berhasil dihapus.');
    }

    // Tambahkan method ini di dalam DiklatEventController

    public function eventAttendanceDetail(Request $request, $id)
    {
        $event = DiklatEvent::with(['category'])->find($id);
        if (!$event) return $this->respondError('Event tidak ditemukan');

        $search = $request->query('search');
        $perPage = $request->query('per_page', 10);

        $query = \App\Models\User::with(['employee.department', 'employee.position'])
            ->orderBy('name', 'asc');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                ->orWhereHas('employee', function($e) use ($search) {
                    $e->where('nip', 'ILIKE', "%{$search}%");
                });
            });
        }

        $userList = $query->paginate($perPage);
        $attendedUserIds = \Illuminate\Support\Facades\DB::table('diklat_attendances')
            ->where('diklat_event_id', $id)
            ->pluck('user_id')
            ->toArray();

        return $this->respondSuccess([
            'event' => $event,
            'attended_user_ids' => $attendedUserIds,
            'user_list' => $userList
        ]);
    }

    public function markAttendance(Request $request, $id)
    {
        $event = DiklatEvent::find($id);
        if (!$event) return $this->respondError('Event tidak ditemukan');

        $validator = Validator::make($request->all(), [
            'user_ids' => 'present|array',
        ]);

        if ($validator->fails()) return $this->respondError($validator->errors()->first(), 422);

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($request, $id, $event) {
                \App\Models\DiklatAttendance::where('diklat_event_id', $id)->delete();

                $data = [];
                $adminId = auth()->id();
                foreach ($request->user_ids as $userId) {
                    $data[] = [
                        'diklat_event_id' => $id,
                        'user_id' => $userId,
                        'marked_by' => $adminId,
                        'marked_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (count($data) > 0) {
                    \App\Models\DiklatAttendance::insert($data);
                }

                $event->update(['status' => 'completed']);
            });

            return $this->respondSuccess(null, 'Absensi berhasil disimpan.');
        } catch (\Throwable $th) {
            return $this->respondError('Gagal menyimpan absensi: ' . $th->getMessage());
        }
    }

    public function downloadImportTemplate()
    {
        $fileName = 'template_import_absensi.csv';
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');
            
            fputs($file, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
            
            // Header kolom
            fputcsv($file, ['Nama Karyawan']); 
            
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function importAttendance(Request $request, $id)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv,txt|max:2048'
        ]);

        $import = new DiklatAttendanceImport();
        
        Excel::import($import, $request->file('file'));

        $foundIds = array_unique($import->userIds);
        
        return $this->respondSuccess([
            'imported_user_ids' => $foundIds,
            'not_found' => $import->notFoundNames,
            'count' => count($foundIds)
        ], 'File berhasil diproses.');
    }
}