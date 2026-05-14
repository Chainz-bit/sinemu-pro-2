<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Sinemu' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite('resources/js/entries/auth-base.js')
    @stack('styles')
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
    @yield('content')
    @stack('scripts')
</body>
</html>
