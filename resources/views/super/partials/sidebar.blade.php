@php
    $managerRoleLabel = \App\Support\RoleLabels::manager();
    $sidebarProfileAvatar = asset('img/profil.jpg');
    $rawProfilePath = trim((string) ($superAdmin?->profil ?? ''));
    if ($rawProfilePath !== '') {
        if (str_starts_with($rawProfilePath, 'http://') || str_starts_with($rawProfilePath, 'https://')) {
            $sidebarProfileAvatar = $rawProfilePath;
        } else {
            $normalizedProfilePath = str_replace('\\', '/', ltrim($rawProfilePath, '/'));
            if (str_starts_with($normalizedProfilePath, 'storage/')) {
                $normalizedProfilePath = substr($normalizedProfilePath, 8);
            } elseif (str_starts_with($normalizedProfilePath, 'public/')) {
                $normalizedProfilePath = substr($normalizedProfilePath, 7);
            }
            $sidebarProfileAvatar = asset('storage/' . $normalizedProfilePath);
        }
    }
    $superSidebarItems = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => route('super.dashboard'), 'icon' => ''],
        ['key' => 'admins', 'label' => 'Daftar ' . $managerRoleLabel, 'url' => route('super.admins.index'), 'icon' => ''],
        ['key' => 'admin-verifications', 'label' => 'Verifikasi ' . $managerRoleLabel, 'url' => route('super.admin-verifications.index'), 'icon' => ''],
        ['key' => 'admins-create', 'label' => 'Tambah Akun Pengelola', 'url' => route('super.admins.create'), 'icon' => ''],
    ];
@endphp

<x-dashboard.sidebar
    id="admin-sidebar"
    :active-menu="($activeMenu ?? '')"
    :brand-url="route('super.dashboard')"
    :brand-image="null"
    :home-url="null"
    :nav-items="$superSidebarItems"
>
    <div class="profile-menu-wrap">
        <button type="button" class="admin-card profile-menu-trigger" aria-expanded="false" aria-controls="profile-menu">
            <img src="{{ $sidebarProfileAvatar }}" alt="Super Admin" onerror="this.onerror=null;this.src='{{ asset('img/profil.jpg') }}';">
            <div class="profile-meta">
                <strong>{{ $superAdmin?->nama ?? 'Super Admin' }}</strong>
                <small>Pengelola Sistem</small>
            </div>
            <svg class="profile-chevron" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M8 10l4 4 4-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>

        <div class="profile-menu" id="profile-menu">
            <a href="{{ route('super.profile') }}" class="{{ ($activeMenu ?? '') === 'profile' ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8a4 4 0 0 0 0 8Zm0 2c-4.4 0-8 2-8 4.5V20h16v-1.5C20 16 16.4 14 12 14Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Profil Saya
            </a>
            <form method="POST" action="{{ route('super.logout') }}">
                @csrf
                <button type="submit" class="danger">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H4m10-8h3a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3h-3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Keluar
                </button>
            </form>
        </div>
    </div>
</x-dashboard.sidebar>
