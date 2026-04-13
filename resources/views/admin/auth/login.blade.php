@extends('layouts.auth')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth/login.css') }}?v={{ @filemtime(public_path('css/auth/login.css')) }}">
@endpush

@section('content')
    {{-- Admin Login Page Start --}}
    <main class="login-shell login-body">
        {{-- Left Panel Start --}}
        <section class="login-left" aria-label="Informasi portal admin">
            <div class="left-content">
                <h1 class="left-title">Kelola laporan barang dengan cepat dan aman</h1>
                <p class="left-subtitle">Portal admin SiNemu membantu verifikasi laporan temuan, klaim, dan pemrosesan data secara terstruktur.</p>

                <div class="left-illustration" aria-hidden="true">
                    <img
                        src="{{ asset('img/login-image.png') }}"
                        alt="Ilustrasi Login Admin"
                        class="left-illustration-img"
                        loading="lazy"
                        decoding="async"
                    >
                </div>
            </div>
        </section>
        {{-- Left Panel End --}}

        {{-- Right Panel Start --}}
        <section class="login-right" aria-label="Form login admin">
            <div class="right-content">
                <div class="brand-wrap">
                    <img src="{{ asset('img/logo.png') }}" alt="Sinemu" class="brand-logo" width="130" height="40" fetchpriority="high">
                </div>

                <header class="login-header">
                    <h2>Login Admin</h2>
                    <p>Masuk untuk melanjutkan ke dashboard admin</p>
                </header>

                @if (session('status'))
                    <div class="alert-box alert-success">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert-box alert-error">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('admin.login') }}" class="login-form" novalidate autocomplete="off">
                    @csrf

                    <div class="field-group">
                        <label for="login">Email atau Username</label>
                        <div class="input-wrap">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <input id="login" type="text" name="login" value="{{ old('login') }}" placeholder="Masukkan email atau username" required autofocus autocomplete="username" autocapitalize="off" spellcheck="false">
                        </div>
                    </div>

                    <div class="field-group">
                        <label for="password">Kata Sandi</label>
                        <div class="input-wrap">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M8 11V8.5a4 4 0 0 1 8 0V11m-9.5 0h11a1.5 1.5 0 0 1 1.5 1.5v6A1.5 1.5 0 0 1 17.5 20h-11A1.5 1.5 0 0 1 5 18.5v-6A1.5 1.5 0 0 1 6.5 11Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <input id="password" type="password" name="password" placeholder="********" required autocomplete="new-password">
                            <button type="button" class="toggle-password" data-toggle-target="password" aria-label="Tampilkan kata sandi">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M2.8 12.2C4.3 8.7 7.7 6.5 12 6.5s7.7 2.2 9.2 5.7c-1.5 3.5-4.9 5.7-9.2 5.7s-7.7-2.2-9.2-5.7Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="12" cy="12.2" r="2.2" stroke="currentColor" stroke-width="1.7"/></svg>
                            </button>
                        </div>
                    </div>

                    <label class="remember-row">
                        <input id="remember" type="checkbox" name="remember">
                        <span>Ingat saya untuk 30 hari</span>
                    </label>

                    <button class="btn-submit" type="submit">Masuk</button>
                </form>

                <p class="register-link">
                    Belum punya akun admin?
                    <a href="{{ route('admin.register') }}">Daftar Sekarang</a>
                </p>
            </div>
        </section>
        {{-- Right Panel End --}}
    </main>
    {{-- Admin Login Page End --}}
@endsection

@push('scripts')
    <script src="{{ asset('js/auth/register.js') }}?v={{ @filemtime(public_path('js/auth/register.js')) }}" defer></script>
@endpush
