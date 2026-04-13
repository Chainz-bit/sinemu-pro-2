@php
    $sidebarProfilePath = trim((string) ($admin?->profil ?? ''));
    if ($sidebarProfilePath === '') {
        $sidebarProfileAvatar = asset('img/profil.jpg');
    } elseif (str_starts_with($sidebarProfilePath, 'http://') || str_starts_with($sidebarProfilePath, 'https://')) {
        $sidebarProfileAvatar = $sidebarProfilePath;
    } elseif (str_starts_with($sidebarProfilePath, '/')) {
        $sidebarProfileAvatar = asset(ltrim($sidebarProfilePath, '/'));
    } else {
        $sidebarProfileAvatar = asset('storage/' . ltrim($sidebarProfilePath, '/'));
    }
@endphp

<aside class="sidebar" id="admin-sidebar">
    {{-- BAGIAN: Merek --}}
    <div class="sidebar-brand">
        <img src="{{ asset('img/logo.png') }}" alt="SiNemu">
    </div>

    {{-- BAGIAN: Navigasi Utama --}}
    <nav class="sidebar-nav">
        <a href="{{ route('admin.dashboard') }}" class="{{ ($activeMenu ?? '') === 'dashboard' ? 'active' : '' }}">Dashboard</a>
        <a href="{{ route('admin.lost-items') }}" class="{{ ($activeMenu ?? '') === 'lost-items' ? 'active' : '' }}">Daftar Barang Hilang</a>
        <a href="{{ route('admin.found-items') }}" class="{{ ($activeMenu ?? '') === 'found-items' ? 'active' : '' }}">Daftar Barang Temuan</a>
        <a href="{{ route('admin.claim-verifications') }}" class="{{ ($activeMenu ?? '') === 'claim-verifications' ? 'active' : '' }}">Verifikasi Klaim</a>
        <a href="{{ route('admin.input-items') }}" class="{{ ($activeMenu ?? '') === 'input-items' ? 'active' : '' }}">Input Barang</a>
    </nav>

    {{-- BAGIAN: Menu Profil --}}
    <div class="sidebar-bottom">
        <div class="profile-menu-wrap">
            <button type="button" class="admin-card profile-menu-trigger" aria-expanded="false" aria-controls="profile-menu">
                <img src="{{ $sidebarProfileAvatar }}" alt="Admin">
                <div class="profile-meta">
                    <strong>{{ $admin?->nama ?? 'Admin' }}</strong>
                    <small>Pengelola Sistem</small>
                </div>
                <svg class="profile-chevron" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M8 10l4 4 4-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>

            <div class="profile-menu" id="profile-menu">
                <a href="{{ route('admin.profile') }}" class="{{ ($activeMenu ?? '') === 'profile' ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Profil Saya
                </a>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="danger">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H4m10-8h3a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3h-3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>
</aside>
