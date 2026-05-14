@extends('layouts.auth')

@push('styles')
    @vite('resources/js/entries/auth-login.js')
@endpush

@section('content')
    {{-- Login Page Start --}}
    <main class="login-shell login-body">
        {{-- Left Panel Start --}}
        <section class="login-left" aria-label="Informasi platform">
            <div class="left-content">
                <h1 class="left-title">Membantu menemukan barang berharga anda</h1>
                <p class="left-subtitle">SiNemu adalah sistem restoratif premium untuk mengelola dan menemukan barang hilang dengan presisi tinggi.</p>

                <div class="left-illustration" aria-hidden="true">
                    <img
                        src="{{ asset('img/login-image.png') }}"
                        alt="Ilustrasi Login"
                        class="left-illustration-img"
                        loading="lazy"
                        decoding="async"
                    >
                </div>
            </div>
        </section>
        {{-- Left Panel End --}}

        {{-- Right Panel Start --}}
        <section class="login-right" aria-label="Form login">
            <div class="right-content">
                <div class="brand-wrap">
                    <img src="{{ asset('img/logo.png') }}" alt="Sinemu" class="brand-logo" width="130" height="40" fetchpriority="high">
                </div>

                <header class="login-header">
                    <h2>Selamat datang kembali!</h2>
                    <p>Masuk untuk melanjutkan ke akun Anda</p>
                </header>

                <form method="POST" action="{{ route('login') }}" class="login-form" novalidate autocomplete="off" data-login-form>
                    @csrf

                    <div class="field-group">
                        <label for="login">Email atau Username</label>
                        <div class="input-wrap {{ $errors->has('login') ? 'is-invalid' : '' }}">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M4 7.5A2.5 2.5 0 0 1 6.5 5h11A2.5 2.5 0 0 1 20 7.5v9a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 16.5v-9Zm1.8-.2 5.85 4.45a.6.6 0 0 0 .7 0l5.85-4.45" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <input id="login" type="text" name="login" value="{{ old('login') }}" placeholder="nama@email.com atau username" required autofocus autocomplete="username" autocapitalize="off" spellcheck="false" @error('login') aria-invalid="true" aria-describedby="login-error" @enderror>
                        </div>
                        @error('login')
                            <p class="field-error" id="login-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field-group">
                        <div class="label-row">
                            <label for="password">Kata Sandi</label>
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="small-link">Lupa Kata Sandi?</a>
                            @endif
                        </div>

                        <div class="input-wrap {{ $errors->has('password') ? 'is-invalid' : '' }}">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M8 11V8.5a4 4 0 0 1 8 0V11m-9.5 0h11a1.5 1.5 0 0 1 1.5 1.5v6A1.5 1.5 0 0 1 17.5 20h-11A1.5 1.5 0 0 1 5 18.5v-6A1.5 1.5 0 0 1 6.5 11Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <input id="password" type="password" name="password" placeholder="********" required autocomplete="current-password" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                            <button id="togglePassword" type="button" class="toggle-password" aria-label="Tampilkan kata sandi">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M2.8 12.2C4.3 8.7 7.7 6.5 12 6.5s7.7 2.2 9.2 5.7c-1.5 3.5-4.9 5.7-9.2 5.7s-7.7-2.2-9.2-5.7Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="12" cy="12.2" r="2.2" stroke="currentColor" stroke-width="1.7"/></svg>
                            </button>
                        </div>
                        @error('password')
                            <p class="field-error" id="password-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="remember-row">
                        <input id="remember_me" type="checkbox" name="remember">
                        <span>Ingat saya</span>
                    </label>

                    <button class="btn-submit" type="submit" data-login-submit data-loading-label="Memproses...">Masuk</button>

                    @if (Route::has('auth.google.redirect'))
                        <div class="divider" role="presentation">ATAU</div>

                        <a id="googleLoginBtn" class="btn-google" href="{{ route('auth.google.redirect') }}">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M21.8 12.2c0-.7-.1-1.2-.2-1.8H12v3.4h5.5a4.7 4.7 0 0 1-2 3.1v2.5h3.2c1.9-1.7 3.1-4.2 3.1-7.2Z" fill="#4285F4"/>
                                <path d="M12 22c2.7 0 4.9-.9 6.5-2.5l-3.2-2.5c-.9.6-2 .9-3.3.9-2.5 0-4.6-1.7-5.4-4H3.2v2.6A10 10 0 0 0 12 22Z" fill="#34A853"/>
                                <path d="M6.6 13.9a5.8 5.8 0 0 1 0-3.8V7.5H3.2a10 10 0 0 0 0 9l3.4-2.6Z" fill="#FBBC05"/>
                                <path d="M12 6.2c1.4 0 2.7.5 3.7 1.4l2.8-2.8A10 10 0 0 0 3.2 7.5l3.4 2.6c.8-2.3 2.9-4 5.4-4Z" fill="#EA4335"/>
                            </svg>
                            Masuk dengan Google
                        </a>
                    @endif
                </form>

                <p class="register-link">
                    Belum punya akun?
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}">Daftar Sekarang</a>
                    @else
                        <a href="#">Daftar Sekarang</a>
                    @endif
                </p>
            </div>
        </section>
        {{-- Right Panel End --}}
    </main>
    {{-- Login Page End --}}
@endsection
