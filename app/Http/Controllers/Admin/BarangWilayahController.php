<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Support\ManagerPortal;
use App\Support\RoleLabels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BarangWilayahController extends Controller
{
    public function index(Request $request): View
    {
        $admin = ManagerPortal::user();
        abort_if(! $admin || empty($admin->region_id), 403, ucfirst(RoleLabels::managerLower()).' belum memiliki wilayah akses.');

        $search = trim((string) $request->query('search', ''));

        $barangs = Barang::query()
            ->with(['kategori:id,nama_kategori', 'region:id,nama_wilayah'])
            ->where('region_id', $admin->region_id)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('nama_barang', 'like', "%{$search}%")
                        ->orWhere('lokasi_ditemukan', 'like', "%{$search}%")
                        ->orWhere('status_barang', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('manager::pages.barang-wilayah.index', compact('admin', 'barangs', 'search'));
    }

    public function edit(Barang $barang): View
    {
        $admin = ManagerPortal::user();

        return view('manager::pages.barang-wilayah.edit', compact('admin', 'barang'));
    }

    public function update(Request $request, Barang $barang): RedirectResponse
    {
        $validated = $request->validate([
            'nama_barang' => ['required', 'string', 'max:255'],
            'lokasi_ditemukan' => ['required', 'string', 'max:255'],
            'status_barang' => ['nullable', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
        ]);

        $barang->update($validated);

        return redirect()
            ->route(ManagerPortal::routeName('barang-wilayah.index'))
            ->with('status', 'Data barang wilayah berhasil diperbarui.');
    }

    public function destroy(Barang $barang): RedirectResponse
    {
        $barang->delete();

        return redirect()
            ->route(ManagerPortal::routeName('barang-wilayah.index'))
            ->with('status', 'Data barang wilayah berhasil dihapus.');
    }
}
