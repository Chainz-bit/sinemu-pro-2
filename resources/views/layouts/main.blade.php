<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Sinemu' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        (function () {
            try {
                if ('scrollRestoration' in window.history) {
                    window.history.scrollRestoration = 'manual';
                }

                if (!window.location.hash) {
                    window.scrollTo(0, 0);
                }
            } catch (error) {
                // ignore
            }
        })();
    </script>
    <link rel="icon" type="image/png" href="{{ asset('img/logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
    {{-- Anti-FOUC: hide body until CSS is loaded --}}
    <style>body{opacity:0}body.page-transition{opacity:1}[x-cloak]{display:none!important}</style>
    @vite('resources/js/entries/main.js')
    @stack('styles')
</head>
<body>
    @php
        /** @var \Illuminate\Support\ViewErrorBag $errors */
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
    <script id="sinemuFlashMessagesData" type="application/json">@json($sinemuFlashMessages)</script>
    <script>window.__SINEMU_FLASH_MESSAGES = JSON.parse(document.getElementById('sinemuFlashMessagesData')?.textContent || '[]');</script>
    <div class="page-shell">
        @yield('content')
    </div>

    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js" defer></script>
    @stack('scripts')
</body>
</html>
