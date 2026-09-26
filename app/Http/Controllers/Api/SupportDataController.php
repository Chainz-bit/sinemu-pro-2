<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\KategoriResource;
use App\Http\Resources\Api\WilayahResource;
use App\Models\Admin;
use App\Models\Kategori;
use App\Models\Wilayah;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupportDataController extends Controller
{
    public function kategoris(): AnonymousResourceCollection
    {
        $kategoris = Kategori::query()
            ->forForm()
            ->get(['id', 'nama_kategori']);

        return KategoriResource::collection($kategoris);
    }

    public function wilayahs(): AnonymousResourceCollection
    {
        $wilayahs = Wilayah::query()
            ->whereHas('admins', static fn($query) =>
            $query->where('status_verifikasi', Admin::STATUS_ACTIVE))
            ->orderBy('nama_wilayah')
            ->get(['id', 'nama_wilayah', 'lat', 'lng']);

        return WilayahResource::collection($wilayahs);
    }
}
