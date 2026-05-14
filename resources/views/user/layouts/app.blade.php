<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle ?? 'Dashboard User - SiNemu' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/logo.png') }}">

    {{-- BAGIAN: Style reusable dashboard --}}
    @vite('resources/js/entries/user.js')
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
    {{-- BAGIAN: Kerangka dashboard user --}}
    <div class="admin-shell {{ ($hideSidebar ?? false) ? 'admin-shell-no-sidebar' : '' }}">
        @if(!($hideSidebar ?? false))
            @include('user.partials.sidebar', [
                'activeMenu' => $activeMenu ?? null,
                'user' => $user ?? auth()->user(),
            ])
            <button type="button" class="sidebar-backdrop" aria-label="Tutup menu"></button>
        @endif

        <main class="main-content user-main-content">
            @include('user.partials.topbar', [
                'searchAction' => $searchAction ?? request()->url(),
                'searchPlaceholder' => $searchPlaceholder ?? 'Cari laporan Anda',
                'hideSidebar' => $hideSidebar ?? false,
                'hideTopActions' => $hideTopActions ?? false,
                'topbarBackUrl' => $topbarBackUrl ?? null,
                'topbarBackLabel' => $topbarBackLabel ?? 'Kembali',
                'activeMenu' => $activeMenu ?? null,
            ])

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

    {{-- BAGIAN: Script interaksi halaman dashboard user --}}
</body>
</html>
