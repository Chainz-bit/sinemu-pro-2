<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInputItemRequest;
use App\Models\Kategori;
use App\Services\Admin\InputItems\InputItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InputItemController extends Controller
{
    public function __construct(private readonly InputItemService $inputItemService)
    {
    }

    public function index(): View
    {
        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();
        $kategoriOptions = Kategori::query()
            ->orderBy('nama_kategori')
            ->get(['id', 'nama_kategori']);

        return view('admin.pages.input-items', compact('admin', 'kategoriOptions'));
    }

    public function store(StoreInputItemRequest $request): RedirectResponse
    {
        /** @var \App\Models\Admin|null $admin */
        $admin = Auth::guard('admin')->user();
        if (!$admin) {
            return back()->with('error', 'Sesi admin tidak ditemukan. Silakan login ulang.');
        }

        $validated = $request->validated();
        $jenisLaporan = (string) $validated['jenis_laporan'];
        $photo = $request->file('foto_barang');

        if ($jenisLaporan === 'hilang') {
            $stored = $this->inputItemService->storeLostItem($validated, $photo);

            if (!$stored) {
                return back()
                    ->withInput()
                    ->with('error', 'Nama/akun pelapor tidak ditemukan. Gunakan nama akun pengguna yang sudah terdaftar.');
            }

            return back()->with('status', 'Laporan barang hilang berhasil ditambahkan.');
        }

        $this->inputItemService->storeFoundItem((int) $admin->id, $validated, $photo);

        return back()->with('status', 'Laporan barang temuan berhasil ditambahkan.');
    }
}
