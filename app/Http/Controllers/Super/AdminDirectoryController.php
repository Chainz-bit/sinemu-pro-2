<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Http\Requests\Super\StoreAdminAccountRequest;
use App\Http\Requests\Super\UpdateAdminAccountRequest;
use App\Services\Super\Admins\AdminVerificationQueryService;
use App\Models\Admin;
use App\Models\Wilayah;
use App\Support\IndramayuDistricts;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AdminDirectoryController extends Controller
{
    public function __construct(
        private readonly AdminVerificationQueryService $adminVerificationQueryService
    ) {
    }

    public function index(Request $request): View
    {
        $superAdmin = Auth::guard('super_admin')->user();
        $search = trim((string) $request->query('search', ''));
        $statusFilter = trim((string) $request->query('status', 'semua'));

        $data = $this->adminVerificationQueryService->buildIndexData(
            search: $search,
            status: $statusFilter,
            page: (int) $request->query('page', 1),
            perPage: 12,
            superAdminId: $superAdmin?->id
        );

        return view('super.pages.admins.index', [
            'superAdmin' => $superAdmin,
            'search' => $search,
            'statusFilter' => $statusFilter,
            ...$data,
        ]);
    }

    public function create(): View
    {
        return view('super.pages.admins.create', [
            'superAdmin' => Auth::guard('super_admin')->user(),
            'kecamatanOptions' => IndramayuDistricts::names(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function store(StoreAdminAccountRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $superAdminId = (int) Auth::guard('super_admin')->id();
        $status = (string) $validated['status_verifikasi'];

        $admin = Admin::query()->create([
            'super_admin_id' => $superAdminId,
            'region_id' => $this->resolveRegionId((string) $validated['kecamatan']),
            'nama' => $validated['nama'],
            'email' => $validated['email'],
            'nomor_telepon' => $validated['nomor_telepon'],
            'username' => $validated['username'],
            'instansi' => $validated['instansi'],
            'kecamatan' => $validated['kecamatan'],
            'alamat_lengkap' => $validated['alamat_lengkap'],
            'status_verifikasi' => $status,
            'alasan_penolakan' => $status === 'rejected' ? ($validated['alasan_penolakan'] ?? null) : null,
            'verified_at' => in_array($status, ['active', 'rejected'], true) ? now() : null,
            'password' => Hash::make((string) $validated['password']),
        ]);

        return redirect()
            ->route('super.admins.show', $admin)
            ->with('status', 'Akun pengelola barang berhasil dibuat.');
    }

    public function show(Admin $admin): View
    {
        $this->authorizeScopedAdmin($admin);

        return view('super.pages.admins.show', [
            'superAdmin' => Auth::guard('super_admin')->user(),
            'admin' => $admin,
        ]);
    }

    public function edit(Admin $admin): View
    {
        $this->authorizeScopedAdmin($admin);

        return view('super.pages.admins.edit', [
            'superAdmin' => Auth::guard('super_admin')->user(),
            'admin' => $admin,
            'kecamatanOptions' => IndramayuDistricts::names(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function update(UpdateAdminAccountRequest $request, Admin $admin): RedirectResponse
    {
        $this->authorizeScopedAdmin($admin);

        $validated = $request->validated();
        $status = (string) $validated['status_verifikasi'];
        $payload = [
            'super_admin_id' => $admin->super_admin_id ?? Auth::guard('super_admin')->id(),
            'region_id' => $this->resolveRegionId((string) $validated['kecamatan']),
            'nama' => $validated['nama'],
            'email' => $validated['email'],
            'nomor_telepon' => $validated['nomor_telepon'],
            'username' => $validated['username'],
            'instansi' => $validated['instansi'],
            'kecamatan' => $validated['kecamatan'],
            'alamat_lengkap' => $validated['alamat_lengkap'],
            'status_verifikasi' => $status,
            'alasan_penolakan' => $status === 'rejected' ? ($validated['alasan_penolakan'] ?? null) : null,
            'verified_at' => in_array($status, ['active', 'rejected'], true) ? ($admin->verified_at ?? now()) : null,
        ];

        if (!empty($validated['password'])) {
            $payload['password'] = Hash::make((string) $validated['password']);
        }

        $admin->update($payload);

        return redirect()
            ->route('super.admins.show', $admin)
            ->with('status', 'Akun pengelola barang berhasil diperbarui.');
    }

    public function destroy(Admin $admin): RedirectResponse
    {
        $this->authorizeScopedAdmin($admin);

        if ($admin->barangs()->exists() || $admin->klaims()->exists() || $admin->pencocokans()->exists()) {
            return back()->with('error', 'Akun pengelola barang tidak dapat dihapus karena masih memiliki data laporan, klaim, atau pencocokan terkait.');
        }

        $adminName = (string) $admin->nama;
        $admin->delete();

        return redirect()
            ->route('super.admins.index')
            ->with('status', 'Akun pengelola barang ' . $adminName . ' berhasil dihapus.');
    }

    /**
     * @return array<string,string>
     */
    private function statusOptions(): array
    {
        return [
            'active' => 'Aktif',
            'pending' => 'Menunggu Verifikasi',
            'rejected' => 'Ditolak/Revisi',
            'inactive' => 'Nonaktif',
        ];
    }

    private function authorizeScopedAdmin(Admin $admin): void
    {
        $superAdminId = Auth::guard('super_admin')->id();

        abort_if(
            $superAdminId !== null
            && $admin->super_admin_id !== null
            && (int) $admin->super_admin_id !== (int) $superAdminId,
            403
        );
    }

    private function resolveRegionId(string $kecamatan): ?int
    {
        if (!Schema::hasTable('wilayahs') || !Schema::hasColumn('admins', 'region_id')) {
            return null;
        }

        $wilayah = IndramayuDistricts::wilayahItem($kecamatan);

        return (int) Wilayah::query()->firstOrCreate(
            ['nama_wilayah' => $wilayah['nama_wilayah']],
            ['lat' => $wilayah['lat'], 'lng' => $wilayah['lng']]
        )->id;
    }
}
