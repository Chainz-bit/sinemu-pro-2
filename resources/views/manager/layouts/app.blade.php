<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle ?? \App\Support\RoleLabels::manager() . ' - SiNemu' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/logo.png') }}">

    {{-- BAGIAN: Gaya Global --}}
    @vite('resources/js/entries/manager.js')
    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js" defer></script>
</head>
<body>
    @php
        $sinemuFlashMessages = [];
        $statusMessage = session('status');
        if (!empty($statusMessage)) {
            $sinemuFlashMessages[] = [
                'type' => 'success',
                'message' => (string) $statusMessage,
            ];
        }
        $errorMessage = session('error');
        if (!empty($errorMessage)) {
            $sinemuFlashMessages[] = ['type' => 'error', 'message' => (string) $errorMessage];
        }
        if ($errors->any()) {
            $sinemuFlashMessages[] = ['type' => 'error', 'message' => (string) $errors->first()];
        }
    @endphp
    <script>window.__SINEMU_FLASH_MESSAGES = @json($sinemuFlashMessages);</script>
    {{-- BAGIAN: Kerangka pengelola barang --}}
    <div class="admin-shell {{ ($hideSidebar ?? false) ? 'admin-shell-no-sidebar' : '' }}">
        @if(!($hideSidebar ?? false))
            {{-- BAGIAN: Sidebar --}}
            @include('manager::partials.sidebar', [
                'activeMenu' => $activeMenu ?? null,
                'admin' => $manager ?? null,
                'manager' => $manager ?? null
            ])

            {{-- BAGIAN: Latar Belakang Mobile --}}
            <button type="button" class="sidebar-backdrop" aria-label="Tutup menu"></button>
        @endif

        {{-- BAGIAN: Konten Utama --}}
        <main class="main-content">
            {{-- BAGIAN: Bilah Atas --}}
            @include('manager::partials.topbar', [
                'searchAction' => $searchAction ?? request()->url(),
                'searchPlaceholder' => $searchPlaceholder ?? 'Cari laporan atau barang',
                'hideSidebar' => $hideSidebar ?? false,
                'topbarBackUrl' => $topbarBackUrl ?? null,
                'topbarBackLabel' => $topbarBackLabel ?? 'Kembali',
            ])

            <div class="main-content-scroll">
                {{-- BAGIAN: Konten Halaman --}}
                @yield('page-content')
            </div>
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
</body>
</html>
