<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminRegisterRequest;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        $kecamatanOptions = [
            'Balongan',
            'Bongas',
            'Cantigi',
            'Cikedung',
            'Gabuswetan',
            'Gantar',
            'Haurgeulis',
            'Indramayu Kota',
            'Jatibarang',
            'Juntinyuat',
            'Kandanghaur',
            'Karangampel',
            'Kedokanbunder',
            'Kertasemaya',
            'Krangkeng',
            'Kroya',
            'Lelea',
            'Lobener',
            'Losarang',
            'Pasekan',
            'Patrol',
            'Sindang',
            'Sliyeg',
            'Sukagumiwang',
            'Sukra',
            'Terisi',
            'Tukdana',
            'Widasari',
        ];

        return view('admin.auth.register', compact('kecamatanOptions'));
    }

    public function store(AdminRegisterRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $username = $this->buildUniqueAdminUsername($validated['username']);

        Admin::query()->create([
            'super_admin_id' => null,
            'nama' => $validated['nama'],
            'email' => $validated['email'],
            'nomor_telepon' => $validated['nomor_telepon'],
            'username' => $username,
            'instansi' => $validated['instansi'],
            'kecamatan' => $validated['kecamatan'],
            'alamat_lengkap' => $validated['alamat_lengkap'],
            'status_verifikasi' => 'pending',
            'verified_at' => null,
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()
            ->route('admin.login')
            ->with('status', 'Pendaftaran admin berhasil. Akun Anda akan aktif setelah diverifikasi super admin.');
    }

    private function buildUniqueAdminUsername(string $usernameInput): string
    {
        $base = Str::lower(trim($usernameInput));
        $base = preg_replace('/[^a-z0-9._-]/', '', $base) ?? '';

        if ($base === '') {
            $base = 'admin';
        }

        $username = $base;
        $counter = 1;

        while (Admin::query()->where('username', $username)->exists()) {
            $username = $base.$counter;
            $counter++;
        }

        return $username;
    }
}
