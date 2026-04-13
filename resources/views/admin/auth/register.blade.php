@extends('layouts.auth')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth/register.css') }}?v={{ @filemtime(public_path('css/auth/register.css')) }}">
@endpush

@section('content')
    {{-- Admin Register Page Start --}}
    <main class="register-shell register-body">
        {{-- Left Panel Start --}}
        <section class="login-left" aria-label="Informasi pendaftaran admin">
            <div class="left-content">
                <h1 class="left-title">Buat akun admin SiNemu sekarang</h1>
                <p class="left-subtitle">Daftarkan akun admin untuk mengelola verifikasi laporan temuan dan klaim barang secara efisien.</p>

                <div class="left-illustration" aria-hidden="true">
                    <img
                        src="{{ asset('img/login-image.png') }}"
                        alt="Ilustrasi Registrasi Admin"
                        class="left-illustration-img"
                        loading="lazy"
                        decoding="async"
                    >
                </div>
            </div>
        </section>
        {{-- Left Panel End --}}

        {{-- Right Panel Start --}}
        <section class="login-right" aria-label="Form registrasi admin">
            <div class="right-content">
                <div class="brand-wrap">
                    <img src="{{ asset('img/logo.png') }}" alt="Sinemu" class="brand-logo" width="130" height="40" fetchpriority="high">
                </div>

                <header class="login-header">
                    <h2>Buat akun admin</h2>
                    <p>Lengkapi data berikut untuk mengakses dashboard admin</p>
                </header>

                @if ($errors->any())
                    <div class="alert-box alert-error">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('admin.register') }}" class="login-form" novalidate>
                    @csrf

                    <div class="field-group">
                        <label for="nama">Nama Lengkap</label>
                        <div class="input-wrap">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <input id="nama" type="text" name="nama" value="{{ old('nama') }}" placeholder="Nama lengkap admin" required autocomplete="name">
                        </div>
                    </div>

                    <div class="field-group">
                        <label for="email">Alamat Email</label>
                        <div class="input-wrap">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M4 7.5A2.5 2.5 0 0 1 6.5 5h11A2.5 2.5 0 0 1 20 7.5v9a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 16.5v-9Zm1.8-.2 5.85 4.45a.6.6 0 0 0 .7 0l5.85-4.45" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="admin@email.com" required autocomplete="email">
                        </div>
                    </div>

                    <div class="field-group">
                        <label for="username">Username</label>
                        <div class="input-wrap">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <input id="username" type="text" name="username" value="{{ old('username') }}" placeholder="Username admin" required autocomplete="username">
                        </div>
                    </div>

                    <div class="field-group">
                        <label for="instansi">Instansi</label>
                        <div class="input-wrap">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M3 20h18M5 20V8l7-4 7 4v12M9 20v-5h6v5M9 11h.01M12 11h.01M15 11h.01" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <input id="instansi" type="text" name="instansi" value="{{ old('instansi') }}" placeholder="Nama instansi admin" required autocomplete="organization">
                        </div>
                    </div>

                    <div class="field-group">
                        <label for="kecamatan">Kecamatan (Indramayu)</label>
                        <div class="input-wrap">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M12 21s7-5.3 7-11a7 7 0 1 0-14 0c0 5.7 7 11 7 11Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="10" r="2.5" stroke="currentColor" stroke-width="1.7"/></svg>
                            </span>
                            <select id="kecamatan" name="kecamatan" required>
                                <option value="" disabled @selected(old('kecamatan') === null)>Pilih kecamatan</option>
                                @foreach(($kecamatanOptions ?? []) as $kecamatan)
                                    <option value="{{ $kecamatan }}" @selected(old('kecamatan') === $kecamatan)>{{ $kecamatan }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="field-group">
                        <label for="alamat_lengkap">Alamat Lengkap</label>
                        <div class="input-wrap input-wrap-textarea">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <textarea id="alamat_lengkap" name="alamat_lengkap" placeholder="Jl..., RT/RW..., No..., Desa/Kelurahan..." rows="3" required>{{ old('alamat_lengkap') }}</textarea>
                        </div>
                    </div>

                    <div class="field-group">
                        <label for="password">Kata Sandi</label>
                        <div class="input-wrap">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M8 11V8.5a4 4 0 0 1 8 0V11m-9.5 0h11a1.5 1.5 0 0 1 1.5 1.5v6A1.5 1.5 0 0 1 17.5 20h-11A1.5 1.5 0 0 1 5 18.5v-6A1.5 1.5 0 0 1 6.5 11Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <input id="password" type="password" name="password" placeholder="Minimal 8 karakter" required autocomplete="new-password">
                            <button type="button" class="toggle-password" data-toggle-target="password" aria-label="Tampilkan kata sandi">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M2.8 12.2C4.3 8.7 7.7 6.5 12 6.5s7.7 2.2 9.2 5.7c-1.5 3.5-4.9 5.7-9.2 5.7s-7.7-2.2-9.2-5.7Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="12" cy="12.2" r="2.2" stroke="currentColor" stroke-width="1.7"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="field-group">
                        <label for="password_confirmation">Konfirmasi Kata Sandi</label>
                        <div class="input-wrap">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M8 11V8.5a4 4 0 0 1 8 0V11m-9.5 0h11a1.5 1.5 0 0 1 1.5 1.5v6A1.5 1.5 0 0 1 17.5 20h-11A1.5 1.5 0 0 1 5 18.5v-6A1.5 1.5 0 0 1 6.5 11Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <input id="password_confirmation" type="password" name="password_confirmation" placeholder="Ulangi kata sandi" required autocomplete="new-password">
                            <button type="button" class="toggle-password" data-toggle-target="password_confirmation" aria-label="Tampilkan kata sandi">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M2.8 12.2C4.3 8.7 7.7 6.5 12 6.5s7.7 2.2 9.2 5.7c-1.5 3.5-4.9 5.7-9.2 5.7s-7.7-2.2-9.2-5.7Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="12" cy="12.2" r="2.2" stroke="currentColor" stroke-width="1.7"/></svg>
                            </button>
                        </div>
                    </div>

                    <button class="btn-submit" type="submit">Daftar Admin</button>
                </form>

                <p class="register-link">
                    Sudah punya akun admin?
                    <a href="{{ route('admin.login') }}">Masuk Sekarang</a>
                </p>
            </div>
        </section>
        {{-- Right Panel End --}}
    </main>
    {{-- Admin Register Page End --}}
@endsection

@push('scripts')
    <script src="{{ asset('js/auth/register.js') }}?v={{ @filemtime(public_path('js/auth/register.js')) }}" defer></script>
@endpush
