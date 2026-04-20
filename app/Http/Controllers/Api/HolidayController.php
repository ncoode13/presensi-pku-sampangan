<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller as ApiController;
use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class HolidayController extends ApiController
{
    // GET: List Data
    public function index(Request $request)
    {
        $search = $request->query('search');
        $year = $request->query('year', date('Y'));

        $query = Holiday::query();

        // Filter Tahun (Berdasarkan start_date)
        if ($year) {
            $query->whereYear('start_date', $year);
        }

        // Fitur Pencarian
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $holidays = $query->orderBy('start_date', 'asc')->get();

        return $this->respondSuccess($holidays);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'note' => 'nullable|string',
        ]);

        $holiday = Holiday::create($request->all());

        return $this->respondSuccess($holiday, 'Hari libur berhasil ditambahkan');
    }

    // PUT: Update
    public function update(Request $request, $id)
    {
        $holiday = Holiday::find($id);
        if (!$holiday) return $this->respondError('Data tidak ditemukan', 404);

        $request->validate([
            'name' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'note' => 'nullable|string',
        ]);

        $holiday->update($request->all());

        return $this->respondSuccess($holiday, 'Hari libur berhasil diperbarui');
    }

    // DELETE: Hapus
    public function destroy($id)
    {
        $holiday = Holiday::find($id);
        if (!$holiday) return $this->respondError('Data tidak ditemukan', 404);

        $holiday->delete(); // Soft Delete

        return $this->respondSuccess(null, 'Hari libur berhasil dihapus');
    }

    // --- FITUR SPESIAL: SYNC DARI API LUAR ---
    public function syncNationalHolidays(Request $request)
    {
        $year = $request->input('year', date('Y'));

        try {
            $response = Http::withoutVerifying()->get("https://libur.deno.dev/api", ['year' => $year]);
            $externalData = $response->json() ?? [];

            DB::transaction(function () use ($externalData) {
                foreach ($externalData as $item) {
                    Holiday::updateOrCreate(
                        [
                            'start_date' => $item['date'], 
                            'name'       => $item['name']
                        ],
                        [
                            'end_date'   => $item['date'],
                            'note'       => 'Imported: Libur Nasional/Cuti Bersama'
                        ]
                    );
                }
            });

            return $this->respondSuccess(null, "Sinkronisasi tahun {$year} berhasil.");
        } catch (\Throwable $th) {
            return $this->respondError('Gagal: ' . $th->getMessage());
        }
    }
}
