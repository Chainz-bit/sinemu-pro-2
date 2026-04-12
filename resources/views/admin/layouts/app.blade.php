<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle ?? 'Admin - SiNemu' }}</title>

    {{-- BAGIAN: Gaya Global --}}
    <link rel="stylesheet" href="{{ asset('css/page-transition.css') }}?v={{ @filemtime(public_path('css/page-transition.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/admin/app.css') }}?v={{ @filemtime(public_path('css/admin/app.css')) }}">
    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js" defer></script>
</head>
<body>
    {{-- BAGIAN: Kerangka Admin --}}
    <div class="admin-shell">
        {{-- BAGIAN: Sidebar --}}
        @include('admin.partials.sidebar', [
            'activeMenu' => $activeMenu ?? null,
            'admin' => $admin ?? null
        ])

        {{-- BAGIAN: Latar Belakang Mobile --}}
        <button type="button" class="sidebar-backdrop" aria-label="Tutup menu"></button>

        {{-- BAGIAN: Konten Utama --}}
        <main class="main-content">
            {{-- BAGIAN: Bilah Atas --}}
            @include('admin.partials.topbar', [
                'searchAction' => $searchAction ?? request()->url(),
                'searchPlaceholder' => $searchPlaceholder ?? 'Cari laporan atau barang',
            ])

            {{-- BAGIAN: Konten Halaman --}}
            @yield('page-content')
        </main>
    </div>

    {{-- BAGIAN: Modal Konfirmasi --}}
    <div class="confirm-modal-backdrop" id="confirm-modal-backdrop" hidden>
        <div class="confirm-modal" id="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title">
            <h3 id="confirm-modal-title">Konfirmasi Hapus</h3>
            <p id="confirm-modal-message">Yakin ingin menghapus data ini?</p>
            <div class="confirm-modal-actions">
                <button type="button" class="confirm-btn-cancel" id="confirm-modal-cancel">Batal</button>
                <button type="button" class="confirm-btn-danger" id="confirm-modal-submit">Hapus</button>
            </div>
        </div>
    </div>

    {{-- BAGIAN: Skrip Global --}}
    <script src="{{ asset('js/page-transition.js') }}?v={{ @filemtime(public_path('js/page-transition.js')) }}" defer></script>
    <script type="module" src="{{ asset('js/admin/app.js') }}?v={{ @filemtime(public_path('js/admin/app.js')) }}"></script>
</body>
</html>
