<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\SuperAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
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

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'password' => $request->input('password') !== null ? (string) $request->input('password') : null,
            'password_confirmation' => $request->input('password_confirmation') !== null ? (string) $request->input('password_confirmation') : null,
        ]);

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:admins,email'],
            'username' => ['required', 'string', 'max:255'],
            'instansi' => ['required', 'string', 'max:255'],
            'kecamatan' => ['required', 'string', 'max:100'],
            'alamat_lengkap' => ['required', 'string', 'max:1200'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $username = $this->buildUniqueAdminUsername($validated['username']);

        $superAdmin = SuperAdmin::query()->first();
        if (!$superAdmin) {
            $superAdmin = SuperAdmin::query()->create([
                'nama' => 'Super Admin',
                'email' => 'superadmin@sinemu.com',
                'username' => 'superadmin',
                'password' => Hash::make('super123'),
            ]);
        }

        Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'nama' => $validated['nama'],
            'email' => $validated['email'],
            'username' => $username,
            'instansi' => $validated['instansi'],
            'kecamatan' => $validated['kecamatan'],
            'alamat_lengkap' => $validated['alamat_lengkap'],
            'status_verifikasi' => 'pending',
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()
            ->route('admin.login')
            ->with('status', 'Pendaftaran berhasil. Akun Anda akan diverifikasi oleh super admin dalam 1x24 jam.');
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
