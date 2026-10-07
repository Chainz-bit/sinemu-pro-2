<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\SubmitFoundReportRequest;
use App\Models\Admin;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Wilayah;
use App\Support\WorkflowStatus;
use App\Rules\RegionHasActiveAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class FoundReportController extends Controller
{
    public function create()
    {
        $categories = collect();
        if (Schema::hasTable('kategoris')) {
            $categories = Kategori::query()
                ->forForm()
                ->get(['id', 'nama_kategori']);
        }

        return view('user.pages.reports.found-create', [
            'user' => Auth::user(),
            'categories' => $categories,
            'wilayahOptions' => Wilayah::query()
                ->whereHas('admins', static fn ($query) => $query
                    ->where('status_verifikasi', Admin::STATUS_ACTIVE))
                ->orderBy('nama_wilayah')
                ->get(['id', 'nama_wilayah']),
        ]);
    }

    public function store(SubmitFoundReportRequest $request): RedirectResponse
    {
        $userId = Auth::id() ?? 0;
        $lockKey = 'submit_laporan_temuan_' . $userId . '_' . md5(
            trim((string) $request->input('nama_barang')) . '_' .
            trim((string) $request->input('region_id')) . '_' .
            trim((string) $request->input('tanggal_ditemukan'))
        );
        $lock = Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            return back()->with('warning', 'Laporan Anda sedang diproses, harap tunggu sebentar.');
        }

        try {
            $recentDuplicate = Barang::query()
                ->where('user_id', (int) $userId)
                ->where('nama_barang', (string) $request->input('nama_barang'))
                ->where('tanggal_ditemukan', (string) $request->input('tanggal_ditemukan'))
                ->where('created_at', '>=', now()->subSeconds(15))
                ->first();

            if ($recentDuplicate) {
                return back()->with('warning', 'Laporan barang temuan serupa baru saja dikirim. Harap tunggu sebentar.');
            }

            $validated = $request->validated();

        $regionId = (int) $validated['region_id'];
        $admin = Admin::query()
            ->select(['id', 'region_id'])
            ->where('region_id', $regionId)
            ->where('status_verifikasi', Admin::STATUS_ACTIVE)
            ->orderBy('id')
            ->first();
        if (!$admin) {
            throw ValidationException::withMessages([
                'region_id' => RegionHasActiveAdmin::MESSAGE,
            ]);
        }

        $kategoriId = $validated['kategori_id'] ?? Kategori::query()->value('id');
        if (!$kategoriId) {
            $kategoriId = Kategori::query()->create(['nama_kategori' => 'Umum'])->id;
        }

        $payload = [
            'admin_id' => (int) $admin->id,
            'user_id' => (int) Auth::id(),
            'kategori_id' => (int) $kategoriId,
            'nama_barang' => $validated['nama_barang'],
            'warna_barang' => $validated['warna_barang'] ?? null,
            'merek_barang' => $validated['merek_barang'] ?? null,
            'nomor_seri' => $validated['nomor_seri'] ?? null,
            'deskripsi' => $validated['deskripsi'],
            'ciri_khusus' => $validated['ciri_khusus'] ?? null,
            'nama_penemu' => $validated['nama_penemu'] ?? (Auth::user()?->nama ?? Auth::user()?->name ?? null),
            'kontak_penemu' => $validated['kontak_penemu'],
            'lokasi_ditemukan' => $validated['lokasi_ditemukan'],
            'detail_lokasi_ditemukan' => $validated['detail_lokasi_ditemukan'] ?? null,
            'tanggal_ditemukan' => $validated['tanggal_ditemukan'],
            'waktu_ditemukan' => $validated['waktu_ditemukan'] ?? null,
            'status_barang' => 'tersedia',
        ];
        if (Schema::hasColumn('barangs', 'status_laporan')) {
            $payload['status_laporan'] = WorkflowStatus::REPORT_SUBMITTED;
        }
        if (Schema::hasColumn('barangs', 'tampil_di_home')) {
            $payload['tampil_di_home'] = false;
        }
        if (Schema::hasColumn('barangs', 'verified_by_admin_id')) {
            $payload['verified_by_admin_id'] = null;
        }
        if (Schema::hasColumn('barangs', 'verified_at')) {
            $payload['verified_at'] = null;
        }
        if (Schema::hasColumn('barangs', 'region_id')) {
            $payload['region_id'] = $regionId;
        }

        $newPhotoPath = null;
        $photo = $request->file('foto_barang');
        if ($photo) {
            $newPhotoPath = $photo->store('barang-temuan/' . now()->format('Y/m'), 'public');
            $payload['foto_barang'] = $newPhotoPath;
        }

        try {
            Barang::create($payload);
        } catch (Throwable $exception) {
            if ($newPhotoPath) {
                Storage::disk('public')->delete($newPhotoPath);
            }

            throw $exception;
        }

        return back()->with('status', 'Laporan barang temuan berhasil dikirim.');
        } finally {
            $lock->release();
        }
    }
}
