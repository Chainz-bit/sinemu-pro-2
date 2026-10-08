@php
    $managerRoleLabel = \App\Support\RoleLabels::manager();
    $managerSystemRoleLabel = \App\Support\RoleLabels::managerSystemRole();

    // BAGIAN: Avatar profil pengelola barang.
    $sidebarProfilePath = trim((string) ($manager?->profil ?? ''));
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

        /** @var \Illuminate\Filesystem\FilesystemAdapter $storage */
        $storage = \Illuminate\Support\Facades\Storage::disk('public');
        if ($storage->exists($normalizedPath)) {
            $absolutePath = $storage->path($normalizedPath);
            $mimeType = mime_content_type($absolutePath) ?: ($storage->mimeType($normalizedPath) ?: 'image/jpeg');
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

    // BAGIAN: Daftar menu sidebar pengelola barang.
    $managerSidebarItems = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => manager_route('dashboard'), 'icon' => ''],
        ['key' => 'lost-items', 'label' => 'Daftar Barang Hilang', 'url' => manager_route('lost-items'), 'icon' => ''],
        ['key' => 'found-items', 'label' => 'Daftar Barang Temuan', 'url' => manager_route('found-items'), 'icon' => ''],
        ['key' => 'claim-verifications', 'label' => 'Verifikasi Klaim', 'url' => manager_route('claim-verifications'), 'icon' => ''],
        ['key' => 'input-items', 'label' => 'Input Barang', 'url' => manager_route('input-items'), 'icon' => ''],
    ];
@endphp

<x-dashboard.sidebar
    id="admin-sidebar"
    :active-menu="($activeMenu ?? '')"
    :brand-url="manager_route('dashboard')"
    :brand-image="null"
    :home-url="null"
    home-label="Kembali ke Landing Page"
    home-icon="mdi:home-outline"
    :nav-items="$managerSidebarItems"
>
    {{-- BAGIAN: Menu profil pengelola barang --}}
    <div class="profile-menu-wrap">
        <button type="button" class="admin-card profile-menu-trigger" aria-expanded="false" aria-controls="profile-menu">
            <img src="{{ $sidebarProfileAvatar }}" alt="{{ $managerRoleLabel }}" data-fallback="{{ asset('img/profil.jpg') }}" onerror="this.onerror=null;this.src=this.dataset.fallback;">
            <div class="profile-meta">
                <strong>{{ $manager?->nama ?? $managerRoleLabel }}</strong>
                <small>{{ $managerSystemRoleLabel }}</small>
            </div>
            <svg class="profile-chevron" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M8 10l4 4 4-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>

        <div class="profile-menu" id="profile-menu">
            <a href="{{ manager_route('profile') }}" class="{{ ($activeMenu ?? '') === 'profile' ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Profil Saya
            </a>
            <form method="POST" action="{{ manager_route('logout') }}">
                @csrf
                <button type="submit" class="danger">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H4m10-8h3a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3h-3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Keluar
                </button>
            </form>
        </div>
    </div>
</x-dashboard.sidebar>
