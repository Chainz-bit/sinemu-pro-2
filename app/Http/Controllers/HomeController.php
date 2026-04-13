<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\LaporanBarangHilang;
use App\Models\Wilayah;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class HomeController extends Controller
{
    public function index()
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        $lostItems = [];
        $lostTotalCount = 0;
        if (Schema::hasTable('laporan_barang_hilangs')) {
            $lostQuery = LaporanBarangHilang::query();
            $lostQuery = $this->resolveHomeScopeQuery($lostQuery, 'laporan_barang_hilangs');
            $lostTotalCount = (clone $lostQuery)->count();

            $lostItems = $lostQuery
                ->latest('updated_at')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'category' => 'UMUM',
                        'name' => $item->nama_barang,
                        'location' => $item->lokasi_hilang,
                        'date' => $item->tanggal_hilang ? Carbon::parse((string) $item->tanggal_hilang)->format('m/d/Y') : '',
                        'date_label' => $item->tanggal_hilang ? Carbon::parse((string) $item->tanggal_hilang)->translatedFormat('d M Y') : '-',
                        'image_url' => $this->resolveItemImageUrl((string) ($item->foto_barang ?? ''), 'barang-hilang'),
                        'detail_url' => route('home.lost-detail', $item->id),
                    ];
                })
                ->values()
                ->all();
        }

        $foundItems = [];
        $foundTotalCount = 0;
        if (Schema::hasTable('barangs')) {
            $foundQuery = Barang::query()->with('kategori:id,nama_kategori');
            $foundQuery = $this->resolveHomeScopeQuery($foundQuery, 'barangs');
            $foundTotalCount = (clone $foundQuery)->count();

            $foundItems = $foundQuery
                ->latest('updated_at')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'category' => strtoupper($item->kategori->nama_kategori ?? 'UMUM'),
                        'name' => $item->nama_barang,
                        'location' => $item->lokasi_ditemukan,
                        'date' => $item->tanggal_ditemukan ? Carbon::parse((string) $item->tanggal_ditemukan)->format('m/d/Y') : '',
                        'date_label' => $item->tanggal_ditemukan ? Carbon::parse((string) $item->tanggal_ditemukan)->translatedFormat('d M Y') : '-',
                        'image_url' => $this->resolveItemImageUrl((string) ($item->foto_barang ?? ''), 'barang-temuan'),
                        'detail_url' => route('home.found-detail', $item->id),
                    ];
                })
                ->values()
                ->all();
        }

        $categories = ['Semua Kategori'];
        $kategoriOptions = collect();
        if (Schema::hasTable('kategoris')) {
            $kategoriOptions = Kategori::query()
                ->orderBy('nama_kategori')
                ->get(['id', 'nama_kategori']);

            $categories = array_merge(
                ['Semua Kategori'],
                $kategoriOptions
                    ->pluck('nama_kategori')
                    ->map(fn ($name) => ucwords(strtolower($name)))
                    ->values()
                    ->all()
            );
        }

        $regions = ['Seluruh Wilayah'];
        $mapRegions = [];
        if (Schema::hasTable('wilayahs')) {
            $wilayahs = Wilayah::query()
                ->orderBy('nama_wilayah')
                ->get(['nama_wilayah', 'lat', 'lng']);

            $regions = array_merge(['Seluruh Wilayah'], $wilayahs->pluck('nama_wilayah')->all());

            $allLocations = collect(array_merge(
                array_column($lostItems, 'location'),
                array_column($foundItems, 'location')
            ))->map(fn ($loc) => Str::lower((string) $loc));

            $mapRegions = $wilayahs->map(function ($wilayah) use ($allLocations) {
                $key = Str::lower(str_replace('kecamatan', '', $wilayah->nama_wilayah));
                $activePoints = $allLocations->filter(function ($loc) use ($key) {
                    return str_contains($loc, trim($key));
                })->count();

                return [
                    'name' => $wilayah->nama_wilayah,
                    'slug' => Str::slug($wilayah->nama_wilayah),
                    'lat' => $wilayah->lat ? (float) $wilayah->lat : null,
                    'lng' => $wilayah->lng ? (float) $wilayah->lng : null,
                    'active_points' => $activePoints,
                ];
            })->values()->all();
        }

        $pickupLocations = [];
        if (Schema::hasTable('admins')) {
            $hasStatusVerifikasi = Schema::hasColumn('admins', 'status_verifikasi');
            $hasKecamatan = Schema::hasColumn('admins', 'kecamatan');
            $hasAlamatLengkap = Schema::hasColumn('admins', 'alamat_lengkap');
            $hasLat = Schema::hasColumn('admins', 'lat');
            $hasLng = Schema::hasColumn('admins', 'lng');

            $selectColumns = ['id', 'instansi'];
            if ($hasKecamatan) {
                $selectColumns[] = 'kecamatan';
            }
            if ($hasAlamatLengkap) {
                $selectColumns[] = 'alamat_lengkap';
            }
            if ($hasLat) {
                $selectColumns[] = 'lat';
            }
            if ($hasLng) {
                $selectColumns[] = 'lng';
            }

            $pickupQuery = Admin::query()->orderBy('instansi');

            if ($hasStatusVerifikasi) {
                $pickupQuery->where('status_verifikasi', 'active');
            }
            if ($hasKecamatan) {
                $pickupQuery->whereNotNull('kecamatan');
            }
            if ($hasAlamatLengkap) {
                $pickupQuery->whereNotNull('alamat_lengkap');
            }

            $pickupLocations = $pickupQuery
                ->get($selectColumns)
                ->map(function (Admin $admin) use ($hasKecamatan, $hasAlamatLengkap, $hasLat, $hasLng) {
                    return [
                        'id' => $admin->id,
                        'name' => $admin->instansi,
                        'address' => $hasAlamatLengkap ? $admin->alamat_lengkap : $admin->instansi,
                        'kecamatan' => $hasKecamatan ? $admin->kecamatan : '',
                        'lat' => $hasLat && $admin->lat !== null ? (float) $admin->lat : null,
                        'lng' => $hasLng && $admin->lng !== null ? (float) $admin->lng : null,
                        'phone' => '0812-3456-7890',
                        'hours' => '08.00-20.00 WIB',
                    ];
                })
                ->values()
                ->all();
        }

        $userName = Auth::user()?->nama ?? Auth::user()?->name ?? 'Pengguna';
        $userLocation = Auth::user()?->location ?? 'Lokasi Anda';

        return view('home', compact(
            'lostItems',
            'foundItems',
            'lostTotalCount',
            'foundTotalCount',
            'categories',
            'kategoriOptions',
            'regions',
            'mapRegions',
            'pickupLocations',
            'userName',
            'userLocation'
        ));
    }

    public function showLostDetail(LaporanBarangHilang $laporanBarangHilang)
    {
        $isVisible = $this->canAccessPublicDetail(
            (bool) ($laporanBarangHilang->tampil_di_home ?? false),
            'laporan_barang_hilangs'
        );
        abort_unless($isVisible, 404);

        $pelapor = $laporanBarangHilang->user?->nama ?? $laporanBarangHilang->user?->name ?? 'Pengguna';
        $detail = (object) [
            'type' => 'hilang',
            'title' => (string) $laporanBarangHilang->nama_barang,
            'category' => 'Umum',
            'location' => (string) $laporanBarangHilang->lokasi_hilang,
            'date_label' => $laporanBarangHilang->tanggal_hilang
                ? Carbon::parse((string) $laporanBarangHilang->tanggal_hilang)->translatedFormat('d F Y')
                : '-',
            'status_label' => 'Belum Ditemukan',
            'status_class' => 'is-lost',
            'description' => trim((string) ($laporanBarangHilang->keterangan ?? '')) !== ''
                ? (string) $laporanBarangHilang->keterangan
                : 'Belum ada deskripsi tambahan dari pelapor.',
            'reporter' => $pelapor,
            'image_url' => $this->resolveItemImageUrl((string) ($laporanBarangHilang->foto_barang ?? ''), 'barang-hilang'),
        ];

        return view('home.detail', [
            'pageTitle' => 'Detail Barang Hilang - SiNemu',
            'detail' => $detail,
        ]);
    }

    public function showFoundDetail(Barang $barang)
    {
        $isVisible = $this->canAccessPublicDetail(
            (bool) ($barang->tampil_di_home ?? false),
            'barangs'
        );
        abort_unless($isVisible, 404);

        $penanggungJawab = $barang->admin?->instansi ?? $barang->admin?->nama ?? 'Admin';
        $detail = (object) [
            'type' => 'temuan',
            'title' => (string) $barang->nama_barang,
            'category' => ucwords(strtolower((string) ($barang->kategori?->nama_kategori ?? 'Umum'))),
            'location' => (string) $barang->lokasi_ditemukan,
            'date_label' => $barang->tanggal_ditemukan
                ? Carbon::parse((string) $barang->tanggal_ditemukan)->translatedFormat('d F Y')
                : '-',
            'status_label' => 'Sudah Ditemukan',
            'status_class' => 'is-found',
            'description' => trim((string) ($barang->deskripsi ?? '')) !== ''
                ? (string) $barang->deskripsi
                : 'Belum ada deskripsi tambahan.',
            'reporter' => $penanggungJawab,
            'image_url' => $this->resolveItemImageUrl((string) ($barang->foto_barang ?? ''), 'barang-temuan'),
        ];

        return view('home.detail', [
            'pageTitle' => 'Detail Barang Temuan - SiNemu',
            'detail' => $detail,
        ]);
    }

    private function resolveItemImageUrl(string $fotoPath, string $defaultFolder): string
    {
        $cleanPath = trim($fotoPath, '/');
        if ($cleanPath === '') {
            return asset('img/login-image.png');
        }

        [$folder, $subPath] = array_pad(explode('/', $cleanPath, 2), 2, '');

        if (in_array($folder, ['barang-hilang', 'barang-temuan', 'verifikasi-klaim'], true) && $subPath !== '') {
            return route('media.image', ['folder' => $folder, 'path' => $subPath], false);
        }

        if ($subPath !== '') {
            return route('media.image', ['folder' => $defaultFolder, 'path' => $cleanPath], false);
        }

        return asset('storage/' . $cleanPath);
    }

    private function resolveHomeScopeQuery($baseQuery, string $tableName)
    {
        if (!Schema::hasColumn($tableName, 'tampil_di_home')) {
            return $baseQuery;
        }

        $publishedQuery = (clone $baseQuery)->where('tampil_di_home', true);
        $publishedCount = (clone $publishedQuery)->count();

        if ($publishedCount > 0) {
            return $publishedQuery;
        }

        return $baseQuery;
    }

    private function canAccessPublicDetail(bool $isPublished, string $tableName): bool
    {
        if (!Schema::hasColumn($tableName, 'tampil_di_home')) {
            return true;
        }

        if ($isPublished) {
            return true;
        }

        // Jika belum ada item ter-publish sama sekali, izinkan fallback item terbaru tetap dibuka.
        return $this->publishedItemsCount($tableName) === 0;
    }

    private function publishedItemsCount(string $tableName): int
    {
        if ($tableName === 'laporan_barang_hilangs') {
            return LaporanBarangHilang::query()->where('tampil_di_home', true)->count();
        }

        if ($tableName === 'barangs') {
            return Barang::query()->where('tampil_di_home', true)->count();
        }

        return 0;
    }
}
