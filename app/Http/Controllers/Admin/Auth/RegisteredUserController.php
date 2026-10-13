<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminRegisterRequest;
use App\Models\Admin;
use App\Models\Wilayah;
use App\Support\IndramayuDistricts;
use App\Support\ManagerPortal;
use App\Support\RoleLabels;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        $kecamatanOptions = IndramayuDistricts::names();
        $pickupRegionOptions = IndramayuDistricts::wilayahItems();

        return view('manager::auth.register', compact('kecamatanOptions', 'pickupRegionOptions'));
    }

    public function store(AdminRegisterRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            Admin::query()->create([
                'super_admin_id' => null,
                'region_id' => $this->resolveRegionId($validated['kecamatan']),
                'nama' => $validated['nama'],
                'email' => $validated['email'],
                'nomor_telepon' => $validated['nomor_telepon'],
                'username' => $validated['username'],
                'instansi' => $validated['instansi'],
                'kecamatan' => $validated['kecamatan'],
                'alamat_lengkap' => $validated['alamat_lengkap'],
                'pickup_address' => $validated['pickup_address'] ?? null,
                'pickup_lat' => $validated['pickup_lat'] ?? null,
                'pickup_lng' => $validated['pickup_lng'] ?? null,
                'status_verifikasi' => Admin::STATUS_PENDING,
                'verified_at' => null,
                'password' => Hash::make($validated['password']),
            ]);
        } catch (QueryException $exception) {
            $this->throwValidationExceptionForDuplicateAccount($exception);
        }

        return redirect()
            ->route(ManagerPortal::loginRoute())
            ->with('status', 'Pendaftaran '.RoleLabels::managerLower().' berhasil. Akun Anda akan aktif setelah diverifikasi super admin.');
    }

    private function resolveRegionId(string $kecamatan): ?int
    {
        $wilayah = IndramayuDistricts::wilayahItem($kecamatan);

        return (int) Wilayah::query()->firstOrCreate(
            ['nama_wilayah' => $wilayah['nama_wilayah']],
            ['lat' => $wilayah['lat'], 'lng' => $wilayah['lng']]
        )->id;
    }

    /**
     * @throws ValidationException
     */
    private function throwValidationExceptionForDuplicateAccount(QueryException $exception): never
    {
        if ((string) $exception->getCode() !== '23000') {
            throw $exception;
        }

        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'admins_username_unique')) {
            throw ValidationException::withMessages([
                'username' => 'Username sudah digunakan.',
            ]);
        }

        if (str_contains($message, 'admins_email_unique')) {
            throw ValidationException::withMessages([
                'email' => 'Email sudah digunakan sebagai akun pengelola.',
            ]);
        }

        if (str_contains($message, 'admins_nomor_telepon_unique')) {
            throw ValidationException::withMessages([
                'nomor_telepon' => 'Nomor telepon sudah digunakan.',
            ]);
        }

        throw ValidationException::withMessages([
            'email' => 'Data akun sudah digunakan.',
        ]);
    }
}
