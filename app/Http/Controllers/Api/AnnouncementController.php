<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller as ApiController;
use App\Models\Announcement;
use App\Models\AnnouncementCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AnnouncementController extends ApiController
{
    /**
     * UNTUK ADMIN: Menampilkan semua pengumuman
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search');

        $announcements = Announcement::with(['category', 'creator'])
            ->when($search, function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate($perPage);
            
        return $this->respondSuccess($announcements);
    }

    /**
     * UNTUK KARYAWAN (DASHBOARD): Menampilkan pengumuman sesuai kriteria
     */
    public function myAnnouncements()
    {
        $user = Auth::user();
        $employee = $user->employee()->first();

        if (!$employee) {
            return $this->respondSuccess([]);
        }

        $branchId   = $employee->branch_id; 
        $departmentId = $employee->department_id;
        $positionId = $employee->position_id;
        $jobLevelId = $employee->job_level_id; 

        // Query pengumuman
        $announcements = Announcement::with(['category', 'creator'])
            ->where(function ($query) use ($branchId, $departmentId, $positionId, $jobLevelId) {
                // 1. Ambil yang Publish ke Semua
                $query->where('is_publish_to_all', true)
                
                // 2. ATAU ambil yang kriterianya WAJIB COCOK SEMUA (Logika AND)
                      ->orWhere(function ($q) use ($branchId, $departmentId, $positionId, $jobLevelId) {
                          
                          $q->where('is_publish_to_all', false)
                            ->whereNotNull('target_criteria')
                            
                            // A. Cek Branches
                            ->where(function ($sub) use ($branchId) {
                                $sub->whereNull('target_criteria->branches')
                                    ->orWhereJsonLength('target_criteria->branches', 0)
                                    ->orWhereJsonContains('target_criteria->branches', (int)$branchId)
                                    ->orWhereJsonContains('target_criteria->branches', (string)$branchId);
                            })
                            
                            // B. Cek Departments
                            ->where(function ($sub) use ($departmentId) {
                                $sub->whereNull('target_criteria->departments')
                                    ->orWhereJsonLength('target_criteria->departments', 0)
                                    ->orWhereJsonContains('target_criteria->departments', (int)$departmentId)
                                    ->orWhereJsonContains('target_criteria->departments', (string)$departmentId);
                            })
                            
                            // C. Cek Positions
                            ->where(function ($sub) use ($positionId) {
                                $sub->whereNull('target_criteria->positions')
                                    ->orWhereJsonLength('target_criteria->positions', 0)
                                    ->orWhereJsonContains('target_criteria->positions', (int)$positionId)
                                    ->orWhereJsonContains('target_criteria->positions', (string)$positionId);
                            })
                            
                            // D. Cek Job Levels
                            ->where(function ($sub) use ($jobLevelId) {
                                $sub->whereNull('target_criteria->job_levels')
                                    ->orWhereJsonLength('target_criteria->job_levels', 0)
                                    ->orWhereJsonContains('target_criteria->job_levels', (int)$jobLevelId)
                                    ->orWhereJsonContains('target_criteria->job_levels', (string)$jobLevelId);
                            });
                      });
            })
            ->latest()
            ->take(5) // Batasi 5 untuk dashboard
            ->get();

        // Tambahkan URL Attachment
        // $announcements->each(function ($announcement) {
        //     if ($announcement->attachment) {
        //         $announcement->attachment_url = Storage::url($announcement->attachment);
        //     }
        // });

        return $this->respondSuccess($announcements);
    }

    /**
     * Menyimpan pengumuman baru
     */
    public function store(Request $request)
    {
        // 1. UBAH DATA DULU SEBELUM VALIDASI (Mencegah FormData string error)
        if ($request->has('target_criteria') && is_string($request->target_criteria)) {
            $request->merge([
                'target_criteria' => json_decode($request->target_criteria, true)
            ]);
        }

        // 2. SEKARANG BARU KITA VALIDASI
        $validator = Validator::make($request->all(), [
            'title'             => 'required|string|max:255',
            'content'           => 'required|string',
            'category_id'       => 'nullable|exists:announcement_categories,id',
            'is_publish_to_all' => 'boolean',
            'target_criteria'   => 'nullable|array',
            'attachment'        => 'nullable|file|mimes:pdf,jpg,jpeg,png,xlsx,xls,doc,docx,csv|max:10240',
        ], [
            // Pesan Error Kustom
            'attachment.max'      => 'Ukuran file attachment tidak boleh lebih dari 10MB.',
            'attachment.mimes'    => 'Format file attachment harus berupa: pdf, jpg, png, xlsx, doc, atau csv.',
            'attachment.uploaded' => 'Gagal mengunggah file. Pastikan total ukuran pengumuman tidak terlalu besar.',
            'title.required'      => 'Judul pengumuman wajib diisi.',
            'content.required'    => 'Isi pengumuman wajib diisi.',
        ]);

        if ($validator->fails()) {
            return $this->respondError($validator->errors()->first(), 422);
        }

        try {
            $payload = $request->only(['title', 'category_id', 'form_id']);
            
            $cleanContent = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{2060}]/u', '', $request->content);
            $cleanContent = mb_convert_encoding($cleanContent, 'UTF-8', 'auto');
            $payload['content'] = $cleanContent;

            $payload['is_publish_to_all'] = $request->boolean('is_publish_to_all', true);
            $payload['created_by'] = Auth::id();

            // 3. MASUKKAN KRITERIA KE PAYLOAD
            if (!$payload['is_publish_to_all'] && $request->has('target_criteria')) {
                $payload['target_criteria'] = $request->target_criteria;
            } else {
                $payload['target_criteria'] = null;
            }

            // 4. HANDLE FILE ATTACHMENT (Tanpa ImageService agar dokumen tidak di-decode)
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                
                // Buat nama file unik: Timestamp + Nama Asli
                $filename = time() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
                
                $path = $file->storeAs('announcements', $filename, 'public');
                
                // Simpan path-nya ke database
                $payload['attachment'] = $path;
            }

            $announcement = Announcement::create($payload);

            return $this->respondSuccess($announcement, 'Pengumuman berhasil dibuat.');
        } catch (\Throwable $th) {
            return $this->respondError('Gagal membuat pengumuman: ' . $th->getMessage());
        }
    }

    /**
     * Menampilkan detail satu pengumuman
     */
    public function show($id)
    {
        $announcement = Announcement::with(['category', 'creator'])->find($id);
        if (!$announcement) return $this->respondError('Pengumuman tidak ditemukan.', 404);


        return $this->respondSuccess($announcement);
    }

    /**
     * Menghapus pengumuman
     */
    public function destroy($id)
    {
        $announcement = Announcement::find($id);
        if (!$announcement) return $this->respondError('Pengumuman tidak ditemukan.', 404);

        try {
            // Hapus file fisik dengan memanggil disk('public')
            if ($announcement->attachment) {
                if (Storage::disk('public')->exists($announcement->attachment)) {
                    Storage::disk('public')->delete($announcement->attachment);
                }
            }

            $announcement->delete();
            return $this->respondSuccess(null, 'Pengumuman berhasil dihapus.');
        } catch (\Throwable $th) {
            return $this->respondError('Gagal menghapus pengumuman: ' . $th->getMessage());
        }
    }

    // ==========================================
    // METHOD TAMBAHAN UNTUK MASTER CATEGORY
    // ==========================================
    public function getCategories()
    {
        $categories = AnnouncementCategory::orderBy('name', 'asc')->get();
        return $this->respondSuccess($categories);
    }

    public function storeCategory(Request $request)
    {
        $request->validate(['name' => 'required|string|unique:announcement_categories,name']);
        $category = AnnouncementCategory::create(['name' => $request->name]);
        return $this->respondSuccess($category, 'Kategori berhasil ditambahkan.');
    }

        /**
        * Update pengumuman yang sudah ada
        */
    
    public function update(Request $request, $id)
    {
        $announcement = Announcement::find($id);
        if (!$announcement) return $this->respondError('Pengumuman tidak ditemukan.', 404);

        if ($request->has('target_criteria') && is_string($request->target_criteria)) {
            $request->merge([
                'target_criteria' => json_decode($request->target_criteria, true)
            ]);
        }

        // VALIDASI
        $validator = Validator::make($request->all(), [
            'title'             => 'required|string|max:255',
            'content'           => 'required|string',
            'category_id'       => 'nullable|exists:announcement_categories,id',
            'is_publish_to_all' => 'boolean',
            'target_criteria'   => 'nullable|array',
            'attachment'        => 'nullable|file|mimes:pdf,jpg,jpeg,png,xlsx,xls,doc,docx,csv|max:10240',
        ], [
            'attachment.max'      => 'Ukuran file attachment tidak boleh lebih dari 10MB.',
            'attachment.mimes'    => 'Format file attachment harus berupa: pdf, jpg, png, xlsx, doc, atau csv.',
            'title.required'      => 'Judul pengumuman wajib diisi.',
            'content.required'    => 'Isi pengumuman wajib diisi.',
        ]);

        if ($validator->fails()) {
            return $this->respondError($validator->errors()->first(), 422);
        }

        try {
            $payload = $request->only(['title', 'category_id', 'form_id']);
            
            $cleanContent = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{2060}]/u', '', $request->content);
            $cleanContent = mb_convert_encoding($cleanContent, 'UTF-8', 'auto');
            $payload['content'] = $cleanContent;

            $payload['is_publish_to_all'] = $request->boolean('is_publish_to_all', true);

            // MASUKKAN KRITERIA KE PAYLOAD
            if (!$payload['is_publish_to_all'] && $request->has('target_criteria')) {
                $payload['target_criteria'] = $request->target_criteria;
            } else {
                $payload['target_criteria'] = null;
            }

            // HANDLE FILE ATTACHMENT JIKA ADA FILE BARU
            if ($request->hasFile('attachment')) {
                // Hapus file lama jika ada
                if ($announcement->attachment && Storage::disk('public')->exists($announcement->attachment)) {
                    Storage::disk('public')->delete($announcement->attachment);
                }

                $file = $request->file('attachment');
                $filename = time() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
                $path = $file->storeAs('announcements', $filename, 'public');
                $payload['attachment'] = $path;
            }

            $announcement->update($payload);

            return $this->respondSuccess($announcement, 'Pengumuman berhasil diperbarui.');
        } catch (\Throwable $th) {
            return $this->respondError('Gagal memperbarui pengumuman: ' . $th->getMessage());
        }
    }
}