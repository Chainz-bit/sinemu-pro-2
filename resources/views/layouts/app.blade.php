<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Sinemu') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/logo.png') }}">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/page-transition.css') }}?v={{ @filemtime(public_path('css/page-transition.css')) }}" rel="stylesheet">

    <!-- Bootstrap Icons (optional) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

    <!-- Navbar Bootstrap -->
  <nav class="navbar navbar-expand-lg bg-body-tertiary shadow-sm py-3 mb-4">
  <div class="container-fluid">
     <a class="navbar-brand fw-bold" href="{{ url('/') }}">
                <img src="{{ asset('') }}" alt="Sinemu" height="50" class="d-inline-block align-text-top me-2 ps-5">
            </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav mx-auto">
        <li class="nav-item mx-2">
          <a class="nav-link active" aria-current="page" href="#">Pencarian</a>
        </li>
        <li class="nav-item mx-2">
          <a class="nav-link" href="#">Hilang</a>
        </li>
        <li class="nav-item mx-2">
          <a class="nav-link" href="#">Temuan</a>  
        </li>
        <li class="nav-item mx-2">
          <a class="nav-link" href="#">Tutorial</a>  
        </li>
        <li class="nav-item mx-2">
          <a class="nav-link" href="#">Admin</a>  
        </li>
        <li class="nav-item mx-2">
          <a class="nav-link" href="#">Lokasi</a>  
        </li>

      </ul>
    
    </div>
  </div>
</nav>
    <!-- Navbar Bootstrap end-->

    <!-- Konten Utama -->
    <main>
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">© 2024 SINEMU INDONESIA - BUILD FOR COMMUNITY</p>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/app.js') }}?v={{ @filemtime(public_path('js/app.js')) }}" defer></script>
    <script src="{{ asset('js/page-transition.js') }}?v={{ @filemtime(public_path('js/page-transition.js')) }}" defer></script>

</body>
</html>
