<?php

namespace App\Http\Controllers;

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

        $categories = ['Semua Kategori'];
        if (Schema::hasTable('kategoris')) {
            $categories = array_merge(
                ['Semua Kategori'],
                Kategori::query()
                    ->orderBy('nama_kategori')
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

        $userName = Auth::user()->name ?? 'Pengguna';
        $userLocation = Auth::user()->location ?? 'Lokasi Anda';

        return view('home', compact('stats', 'lostItems', 'foundItems', 'categories', 'regions', 'mapRegions', 'userName', 'userLocation'));
    }
}
