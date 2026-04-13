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
            if (Schema::hasColumn('laporan_barang_hilangs', 'tampil_di_home')) {
                $lostQuery->where('tampil_di_home', true);
            }
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
                        'date' => $item->tanggal_hilang ? date('m/d/Y', strtotime((string) $item->tanggal_hilang)) : '',
                    ];
                })
                ->values()
                ->all();
        }

        $foundItems = [];
        $foundTotalCount = 0;
        if (Schema::hasTable('barangs')) {
            $foundQuery = Barang::query()->with('kategori:id,nama_kategori');
            if (Schema::hasColumn('barangs', 'tampil_di_home')) {
                $foundQuery->where('tampil_di_home', true);
            }
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
                        'date' => $item->tanggal_ditemukan ? date('m/d/Y', strtotime((string) $item->tanggal_ditemukan)) : '',
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
}
