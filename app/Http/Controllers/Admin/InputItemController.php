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
    private const MISSING_REGION_MESSAGE = 'Pengelola harus memiliki wilayah aktif sebelum membuat data barang.';

    public function __construct(private readonly InputItemService $inputItemService)
    {
    }

    public function index(): View
    {
        /** @var \App\Models\Admin $admin */
        $admin = \App\Support\ManagerPortal::user();
        $kategoriOptions = Kategori::query()
            ->forForm()
            ->get(['id', 'nama_kategori']);

        return view('manager::pages.input-items.create', compact('admin', 'kategoriOptions'));
    }

    public function store(StoreInputItemRequest $request): RedirectResponse
    {
        /** @var \App\Models\Admin|null $admin */
        $admin = \App\Support\ManagerPortal::user();
        if (!$admin) {
            return back()->with('error', 'Sesi ' . \App\Support\RoleLabels::managerLower() . ' tidak ditemukan. Silakan masuk kembali.');
        }

        if (empty($admin->region_id)) {
            return back()
                ->withInput()
                ->with('error', self::MISSING_REGION_MESSAGE);
        }

        $validated = $request->validated();
        $jenisLaporan = (string) $validated['jenis_laporan'];
        $photo = $request->file('foto_barang');
        $adminId = (int) $admin->id;
        $regionId = (int) $admin->region_id;

        if ($jenisLaporan === 'hilang') {
            $stored = $this->inputItemService->storeLostItem($adminId, $regionId, $validated, $photo);

            if (!$stored) {
                return back()
                    ->withInput()
                    ->with('error', 'Nama/akun pelapor tidak ditemukan. Gunakan nama akun pengguna yang sudah terdaftar.');
            }

            return back()->with('status', 'Laporan barang hilang berhasil ditambahkan.');
        }

        $this->inputItemService->storeFoundItem($adminId, $regionId, $validated, $photo);

        return back()->with('status', 'Laporan barang temuan berhasil ditambahkan.');
    }
}
