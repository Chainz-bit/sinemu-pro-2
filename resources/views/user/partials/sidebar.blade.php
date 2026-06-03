@php
    // BAGIAN: Avatar profil user.
    $sidebarProfilePath = trim((string) ($user?->profil ?? ''));
    if ($sidebarProfilePath === '') {
        $sidebarProfileAvatar = asset('img/profil.jpg');
    } elseif (str_starts_with($sidebarProfilePath, 'http://') || str_starts_with($sidebarProfilePath, 'https://')) {
        $sidebarProfileAvatar = $sidebarProfilePath;
    } elseif (str_starts_with($sidebarProfilePath, '/')) {
        $sidebarProfileAvatar = asset(ltrim($sidebarProfilePath, '/'));
    } else {
        $normalizedPath = str_replace('\\', '/', ltrim($sidebarProfilePath, '/'));
        if (str_starts_with($normalizedPath, 'storage/')) {
            $normalizedPath = substr($normalizedPath, 8);
        } elseif (str_starts_with($normalizedPath, 'public/')) {
            $normalizedPath = substr($normalizedPath, 7);
        }

        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($normalizedPath)) {
            $absolutePath = \Illuminate\Support\Facades\Storage::disk('public')->path($normalizedPath);
            $mimeType = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($normalizedPath) ?: 'image/jpeg';
            $binary = @file_get_contents($absolutePath);
            if ($binary !== false) {
                $sidebarProfileAvatar = 'data:' . $mimeType . ';base64,' . base64_encode($binary);
            } else {
                [$avatarFolder, $avatarSubPath] = array_pad(explode('/', $normalizedPath, 2), 2, '');
                $sidebarProfileAvatar = in_array($avatarFolder, ['profil-admin', 'profil-user', 'barang-hilang', 'barang-temuan', 'verifikasi-klaim'], true) && $avatarSubPath !== ''
                    ? route('media.image', ['folder' => $avatarFolder, 'path' => $avatarSubPath])
                    : asset('storage/' . $normalizedPath);
            }
        } else {
            $sidebarProfileAvatar = asset('img/profil.jpg');
        }
    }

    // BAGIAN: Menu sidebar user.
    $userSidebarItems = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => route('user.dashboard'), 'icon' => ''],
        ['key' => 'lost-report', 'label' => 'Lapor Barang Hilang', 'url' => route('user.lost-reports.create'), 'icon' => ''],
        ['key' => 'found-report', 'label' => 'Lapor Barang Temuan', 'url' => route('user.found-reports.create'), 'icon' => ''],
        ['key' => 'claim-history', 'label' => 'Riwayat Klaim', 'url' => route('user.claim-history'), 'icon' => ''],
    ];
@endphp

<x-dashboard.sidebar
    id="admin-sidebar"
    :active-menu="($activeMenu ?? '')"
    :brand-url="route('home')"
    brand-alt="Kembali ke halaman utama"
    :nav-items="$userSidebarItems"
>
    {{-- BAGIAN: Profil + aksi akun user --}}
    <div class="profile-menu-wrap">
        <button type="button" class="admin-card profile-menu-trigger" aria-expanded="false" aria-controls="profile-menu">
            <img src="{{ $sidebarProfileAvatar }}" alt="Pengguna" onerror="this.onerror=null;this.src='{{ asset('img/profil.jpg') }}';">
            <div class="profile-meta">
                <strong>{{ $user?->nama ?? $user?->name ?? 'Pengguna' }}</strong>
                <small>Pengguna SiNemu</small>
            </div>
            <svg class="profile-chevron" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M8 10l4 4 4-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>

        <div class="profile-menu" id="profile-menu">
            <a href="{{ route('user.profile') }}" class="{{ ($activeMenu ?? '') === 'profile' ? 'active' : '' }}">
                Profil Saya
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="danger">
                    Keluar
                </button>
            </form>
        </div>
    </div>
</x-dashboard.sidebar>
