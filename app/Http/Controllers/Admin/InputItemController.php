<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\LaporanBarangHilang;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InputItemController extends Controller
{
    public function index(): View
    {
        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();
        $kategoriOptions = Kategori::query()
            ->orderBy('nama_kategori')
            ->get(['id', 'nama_kategori']);

        return view('admin.pages.input-items', compact('admin', 'kategoriOptions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'jenis_laporan' => ['required', 'in:hilang,temuan'],
            'nama_barang' => ['required', 'string', 'max:255'],
            'kategori_id' => ['nullable', 'integer', 'exists:kategoris,id'],
            'tanggal_waktu' => ['required', 'date'],
            'lokasi' => ['required', 'string', 'max:255'],
            'nama_pelapor' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'foto_barang' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'lokasi_pengambilan' => ['required_if:jenis_laporan,temuan', 'nullable', 'string', 'max:255'],
            'alamat_pengambilan' => ['required_if:jenis_laporan,temuan', 'nullable', 'string', 'max:255'],
            'penanggung_jawab_pengambilan' => ['required_if:jenis_laporan,temuan', 'nullable', 'string', 'max:255'],
            'kontak_pengambilan' => ['required_if:jenis_laporan,temuan', 'nullable', 'string', 'max:255'],
            'jam_layanan_pengambilan' => ['nullable', 'string', 'max:255'],
            'catatan_pengambilan' => ['nullable', 'string'],
        ]);

        $jenisLaporan = (string) $validated['jenis_laporan'];

        if ($jenisLaporan === 'hilang') {
            return $this->storeLostItem($request, $validated);
        }

        return $this->storeFoundItem($request, $validated);
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function storeLostItem(Request $request, array $validated): RedirectResponse
    {
        $reporterName = trim((string) ($validated['nama_pelapor'] ?? ''));
        $reportDate = Carbon::parse((string) $validated['tanggal_waktu'])->toDateString();

        $hasNamaColumn = Schema::hasColumn('users', 'nama');
        $hasNameColumn = Schema::hasColumn('users', 'name');

        $user = User::query()
            ->where(function ($query) use ($reporterName, $hasNamaColumn, $hasNameColumn) {
                if ($hasNamaColumn) {
                    $query->where('nama', $reporterName);
                }

                if ($hasNameColumn) {
                    if ($hasNamaColumn) {
                        $query->orWhere('name', $reporterName);
                    } else {
                        $query->where('name', $reporterName);
                    }
                }

                $query->orWhere('email', $reporterName);
            })
            ->first();

        if (!$user) {
            return back()
                ->withInput()
                ->with('error', 'Nama/akun pelapor tidak ditemukan. Gunakan nama akun pengguna yang sudah terdaftar.');
        }

        $keterangan = trim((string) ($validated['deskripsi'] ?? ''));
        $fotoPath = null;
        if ($request->hasFile('foto_barang')) {
            $fotoPath = $this->storeOptimizedImage(
                $request->file('foto_barang'),
                'barang-hilang/' . now()->format('Y/m')
            );
        }

        $payload = [
            'user_id' => (int) $user->id,
            'nama_barang' => $validated['nama_barang'],
            'lokasi_hilang' => $validated['lokasi'],
            'tanggal_hilang' => $reportDate,
            'keterangan' => $keterangan !== '' ? $keterangan : null,
            'foto_barang' => $fotoPath,
        ];

        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan')) {
            $payload['sumber_laporan'] = 'lapor_hilang';
        }

        LaporanBarangHilang::create($payload);

        return back()->with('status', 'Laporan barang hilang berhasil ditambahkan.');
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function storeFoundItem(Request $request, array $validated): RedirectResponse
    {
        /** @var \App\Models\Admin|null $admin */
        $admin = Auth::guard('admin')->user();
        if (!$admin) {
            return back()->with('error', 'Sesi admin tidak ditemukan. Silakan login ulang.');
        }

        $kategoriId = $validated['kategori_id'] ?? null;
        if (!$kategoriId) {
            $defaultKategori = Kategori::query()->first();
            if (!$defaultKategori) {
                $defaultKategori = Kategori::query()->create(['nama_kategori' => 'Umum']);
            }
            $kategoriId = $defaultKategori->id;
        }

        $deskripsiBase = trim((string) ($validated['deskripsi'] ?? ''));
        $reportDate = Carbon::parse((string) $validated['tanggal_waktu'])->toDateString();
        $metaPelapor = 'Pelapor/Penemu: ' . trim((string) $validated['nama_pelapor']);
        $deskripsiGabungan = $deskripsiBase !== '' ? $deskripsiBase . PHP_EOL . PHP_EOL . $metaPelapor : $metaPelapor;

        $fotoPath = null;
        if ($request->hasFile('foto_barang')) {
            $fotoPath = $this->storeOptimizedImage(
                $request->file('foto_barang'),
                'barang-temuan/' . now()->format('Y/m')
            );
        }

        Barang::create([
            'admin_id' => (int) $admin->id,
            'kategori_id' => (int) $kategoriId,
            'nama_barang' => $validated['nama_barang'],
            'deskripsi' => $deskripsiGabungan,
            'lokasi_ditemukan' => $validated['lokasi'],
            'tanggal_ditemukan' => $reportDate,
            'status_barang' => 'tersedia',
            'foto_barang' => $fotoPath,
            'lokasi_pengambilan' => trim((string) ($validated['lokasi_pengambilan'] ?? '')) ?: null,
            'alamat_pengambilan' => trim((string) ($validated['alamat_pengambilan'] ?? '')) ?: null,
            'penanggung_jawab_pengambilan' => trim((string) ($validated['penanggung_jawab_pengambilan'] ?? '')) ?: null,
            'kontak_pengambilan' => trim((string) ($validated['kontak_pengambilan'] ?? '')) ?: null,
            'jam_layanan_pengambilan' => trim((string) ($validated['jam_layanan_pengambilan'] ?? '')) ?: null,
            'catatan_pengambilan' => trim((string) ($validated['catatan_pengambilan'] ?? '')) ?: null,
        ]);

        return back()->with('status', 'Laporan barang temuan berhasil ditambahkan.');
    }

    private function storeOptimizedImage(UploadedFile $file, string $directory): string
    {
        $directory = trim($directory, '/');
        Storage::disk('public')->makeDirectory($directory);

        // Fallback aman bila GD tidak tersedia.
        if (!extension_loaded('gd')) {
            return $file->store($directory, 'public');
        }

        $realPath = $file->getRealPath();
        if (!$realPath) {
            return $file->store($directory, 'public');
        }

        $imageInfo = @getimagesize($realPath);
        if (!$imageInfo || !isset($imageInfo[2])) {
            return $file->store($directory, 'public');
        }

        $source = match ($imageInfo[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($realPath),
            IMAGETYPE_PNG => @imagecreatefrompng($realPath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($realPath) : false,
            default => false,
        };

        if (!$source) {
            return $file->store($directory, 'public');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            imagedestroy($source);
            return $file->store($directory, 'public');
        }

        $maxSide = 720;
        $scale = min(1, $maxSide / max($sourceWidth, $sourceHeight));
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, true);
        imagesavealpha($target, true);

        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );

        $relativePath = $directory . '/' . Str::uuid()->toString() . '.webp';
        $absolutePath = Storage::disk('public')->path($relativePath);
        $saved = imagewebp($target, $absolutePath, 78);

        imagedestroy($source);
        imagedestroy($target);

        if (!$saved) {
            return $file->store($directory, 'public');
        }

        return $relativePath;
    }
}
