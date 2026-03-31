<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\LaporanBarangHilang;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    public function index()
    {
        $stats = [
            [
                'title' => 'BARANG KEMBALI',
                'description' => 'Lebih dari 1,200 barang telah dikembalikan ke pemiliknya melalui platform ini.',
                'icon' => 'fa-solid fa-box-open',
                'accent' => 'success',
            ],
            [
                'title' => 'Privasi & Keamanan',
                'description' => 'Verifikasi berlapis untuk menjamin keamanan klaim setiap barang yang ditemukan.',
                'icon' => 'fa-solid fa-shield-heart',
                'accent' => 'primary',
            ],
            [
                'title' => 'Tidak Diterbitkan',
                'description' => 'Banyak tersedia dengan 24 jam keputusan sebagai faktor risiko penggolongan barang.',
                'icon' => 'fa-solid fa-clock-rotate-left',
                'accent' => 'warning',
            ],
        ];

        $lostItems = [];
        if (Schema::hasTable('laporan_barang_hilangs')) {
            $lostItems = LaporanBarangHilang::query()
                ->latest('tanggal_hilang')
                ->take(20)
                ->get()
                ->map(function ($item) {
                    return [
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
        if (Schema::hasTable('barangs')) {
            $foundItems = Barang::query()
                ->with('kategori:id,nama_kategori')
                ->latest('tanggal_ditemukan')
                ->take(20)
                ->get()
                ->map(function ($item) {
                    return [
                        'category' => strtoupper($item->kategori->nama_kategori ?? 'UMUM'),
                        'name' => $item->nama_barang,
                        'location' => $item->lokasi_ditemukan,
                        'date' => $item->tanggal_ditemukan ? date('m/d/Y', strtotime((string) $item->tanggal_ditemukan)) : '',
                    ];
                })
                ->values()
                ->all();
        }

        $categoryItems = [];
        if (Schema::hasTable('kategoris')) {
            $categoryItems = Kategori::query()
                ->orderBy('nama_kategori')
                ->pluck('nama_kategori')
                ->map(fn ($name) => ucwords(strtolower($name)))
                ->values()
                ->all();
        }
        $categories = array_values(array_unique(array_merge(['Semua Kategori'], $categoryItems)));

        $regionItems = collect(array_merge(
            array_column($lostItems, 'location'),
            array_column($foundItems, 'location')
        ))
            ->filter()
            ->values()
            ->unique()
            ->all();
        $defaultRegions = [
            'Kecamatan Indramayu',
            'Kecamatan Lohbener',
            'Kecamatan Pasekan',
            'Kecamatan Balongan',
            'Kecamatan Jatibarang',
            'Kecamatan Haurgeulis',
            'Kecamatan Bangodua',
            'Kecamatan Sliyeg',
            'Kecamatan Kandanghaur',
            'Kecamatan Krangkeng',
        ];
        $regions = collect(array_merge(['Seluruh Wilayah'], $defaultRegions, $regionItems))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $knownCoordinates = $this->knownRegionCoordinates();
        $baseMapRegions = [
            'Kecamatan Lohbener',
            'Kecamatan Pasekan',
            'Kecamatan Indramayu',
            'Kecamatan Balongan',
        ];

        $mapRegionNames = collect(array_merge($baseMapRegions, $regionItems))
            ->filter()
            ->unique()
            ->values();

        $allLocations = collect(array_merge(
            array_column($lostItems, 'location'),
            array_column($foundItems, 'location')
        ))->map(fn ($loc) => Str::lower((string) $loc));

        $mapRegions = $mapRegionNames->map(function ($name) use ($knownCoordinates, $allLocations) {
            $key = $this->normalizeRegionKey($name);
            $coord = $knownCoordinates[$key] ?? null;
            $activePoints = $allLocations->filter(function ($loc) use ($key) {
                return str_contains($loc, $key);
            })->count();

            return [
                'name' => $name,
                'slug' => Str::slug($name),
                'lat' => $coord['lat'] ?? null,
                'lng' => $coord['lng'] ?? null,
                'active_points' => max(1, $activePoints),
            ];
        })->values()->all();

        $userName = Auth::user()->name ?? 'Pengguna';
        $userLocation = Auth::user()->location ?? 'Lokasi Anda';

        return view('home', compact('stats', 'lostItems', 'foundItems', 'categories', 'regions', 'mapRegions', 'userName', 'userLocation'));
    }

    private function normalizeRegionKey(string $name): string
    {
        return trim(Str::lower(str_replace('kecamatan', '', $name)));
    }

    private function knownRegionCoordinates(): array
    {
        return [
            'lohbener' => ['lat' => -6.3852, 'lng' => 108.2793],
            'pasekan' => ['lat' => -6.3201, 'lng' => 108.3388],
            'indramayu' => ['lat' => -6.3275, 'lng' => 108.3207],
            'balongan' => ['lat' => -6.3502, 'lng' => 108.4108],
            'jatibarang' => ['lat' => -6.4741, 'lng' => 108.3061],
            'haurgeulis' => ['lat' => -6.4477, 'lng' => 107.9398],
            'bangodua' => ['lat' => -6.4941, 'lng' => 108.1455],
            'sliyeg' => ['lat' => -6.4406, 'lng' => 108.3693],
            'krangkeng' => ['lat' => -6.4415, 'lng' => 108.4841],
            'kandanghaur' => ['lat' => -6.3433, 'lng' => 107.9816],
            'patrol' => ['lat' => -6.3544, 'lng' => 107.8684],
            'jakarta' => ['lat' => -6.2088, 'lng' => 106.8456],
            'bandung' => ['lat' => -6.9175, 'lng' => 107.6191],
            'bekasi' => ['lat' => -6.2383, 'lng' => 106.9756],
            'depok' => ['lat' => -6.4025, 'lng' => 106.7942],
        ];
    }
}
