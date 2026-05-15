@extends('layouts.auth')

@push('styles')
    @vite('resources/js/entries/manager-auth-register.js')
@endpush

@section('content')
    @php
        $managerRoleLabel = \App\Support\RoleLabels::manager();
        $managerRoleLabelLower = \App\Support\RoleLabels::managerLower();
    @endphp
    {{-- Pengelola Barang Register Page Start --}}
    <main class="register-shell register-body">
        {{-- Left Panel Start --}}
        <section class="login-left" aria-label="Informasi pendaftaran {{ $managerRoleLabelLower }}">
            <div class="left-content">
                <h1 class="left-title">Buat akun {{ $managerRoleLabelLower }} SiNemu sekarang</h1>
                <p class="left-subtitle">Daftarkan akun {{ $managerRoleLabelLower }} untuk mengelola verifikasi laporan temuan dan klaim barang secara efisien.</p>

                <div class="left-illustration" aria-hidden="true">
                    <img
                        src="{{ asset('img/login-image.png') }}"
                        alt="Ilustrasi Registrasi {{ $managerRoleLabel }}"
                        class="left-illustration-img"
                        loading="lazy"
                        decoding="async"
                    >
                </div>
            </div>
        </section>
        {{-- Left Panel End --}}

        {{-- Right Panel Start --}}
        <section class="login-right" aria-label="Form registrasi {{ $managerRoleLabelLower }}">
            <div class="right-content">
                <div class="brand-wrap">
                    <img src="{{ asset('img/logo.png') }}" alt="Sinemu" class="brand-logo" width="130" height="40" fetchpriority="high">
                </div>

                <header class="login-header">
                    <h2>Buat akun {{ $managerRoleLabelLower }}</h2>
                    <p>Lengkapi data berikut untuk mengakses dashboard {{ $managerRoleLabelLower }}</p>
                </header>

                <form method="POST" action="{{ manager_route('register') }}" class="login-form" novalidate data-register-form>
                    @csrf

                    <div class="field-group">
                        <label for="nama">Nama Lengkap</label>
                        <div class="input-wrap {{ $errors->has('nama') ? 'is-invalid' : '' }}">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <input id="nama" type="text" name="nama" value="{{ old('nama') }}" placeholder="Nama lengkap {{ $managerRoleLabelLower }}" required autocomplete="name" @error('nama') aria-invalid="true" aria-describedby="nama-error" @enderror>
                        </div>
                        @error('nama')
                            <p class="field-error" id="nama-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field-group">
                        <label for="email">Alamat Email</label>
                        <div class="input-wrap {{ $errors->has('email') ? 'is-invalid' : '' }}">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M4 7.5A2.5 2.5 0 0 1 6.5 5h11A2.5 2.5 0 0 1 20 7.5v9a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 16.5v-9Zm1.8-.2 5.85 4.45a.6.6 0 0 0 .7 0l5.85-4.45" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="{{ config('roles.admin.default_email') }}" required autocomplete="email" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                        </div>
                        @error('email')
                            <p class="field-error" id="email-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field-group">
                        <label for="nomor_telepon">Nomor Telepon</label>
                        <div class="input-wrap {{ $errors->has('nomor_telepon') ? 'is-invalid' : '' }}">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M6.5 3.5h3A1.5 1.5 0 0 1 11 5v14a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 5 19V5a1.5 1.5 0 0 1 1.5-1.5Zm6.5 3h4.5A1.5 1.5 0 0 1 19 8v8a1.5 1.5 0 0 1-1.5 1.5H13" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <input id="nomor_telepon" type="tel" name="nomor_telepon" value="{{ old('nomor_telepon') }}" placeholder="Contoh: 081234567890" required autocomplete="tel" @error('nomor_telepon') aria-invalid="true" aria-describedby="nomor-telepon-error" @enderror>
                        </div>
                        @error('nomor_telepon')
                            <p class="field-error" id="nomor-telepon-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field-group">
                        <label for="username">Username</label>
                        <div class="input-wrap {{ $errors->has('username') ? 'is-invalid' : '' }}">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <input id="username" type="text" name="username" value="{{ old('username') }}" placeholder="Username {{ $managerRoleLabelLower }}" required autocomplete="username" @error('username') aria-invalid="true" aria-describedby="username-error" @enderror>
                        </div>
                        @error('username')
                            <p class="field-error" id="username-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field-group">
                        <label for="instansi">Instansi</label>
                        <div class="input-wrap {{ $errors->has('instansi') ? 'is-invalid' : '' }}">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M3 20h18M5 20V8l7-4 7 4v12M9 20v-5h6v5M9 11h.01M12 11h.01M15 11h.01" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <input id="instansi" type="text" name="instansi" value="{{ old('instansi') }}" placeholder="Nama instansi {{ $managerRoleLabelLower }}" required autocomplete="organization" @error('instansi') aria-invalid="true" aria-describedby="instansi-error" @enderror>
                        </div>
                        @error('instansi')
                            <p class="field-error" id="instansi-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field-group">
                        <label for="kecamatan">Kecamatan (Indramayu)</label>
                        <div class="input-wrap {{ $errors->has('kecamatan') ? 'is-invalid' : '' }}">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M12 21s7-5.3 7-11a7 7 0 1 0-14 0c0 5.7 7 11 7 11Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="10" r="2.5" stroke="currentColor" stroke-width="1.7"/></svg>
                            </span>
                            <select id="kecamatan" name="kecamatan" required @error('kecamatan') aria-invalid="true" aria-describedby="kecamatan-error" @enderror>
                                <option value="" disabled @selected(old('kecamatan') === null)>Pilih kecamatan</option>
                                @foreach(($kecamatanOptions ?? []) as $kecamatan)
                                    <option value="{{ $kecamatan }}" @selected(old('kecamatan') === $kecamatan)>{{ $kecamatan }}</option>
                                @endforeach
                            </select>
                        </div>
                        @error('kecamatan')
                            <p class="field-error" id="kecamatan-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field-group">
                        <label for="alamat_lengkap">Alamat Lengkap</label>
                        <div class="input-wrap input-wrap-textarea {{ $errors->has('alamat_lengkap') ? 'is-invalid' : '' }}">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <textarea id="alamat_lengkap" name="alamat_lengkap" placeholder="Jl..., RT/RW..., No..., Desa/Kelurahan..." rows="3" required @error('alamat_lengkap') aria-invalid="true" aria-describedby="alamat-lengkap-error" @enderror>{{ old('alamat_lengkap') }}</textarea>
                        </div>
                        @error('alamat_lengkap')
                            <p class="field-error" id="alamat-lengkap-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field-group">
                        <label for="password">Kata Sandi</label>
                        <div class="input-wrap {{ $errors->has('password') ? 'is-invalid' : '' }}">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M8 11V8.5a4 4 0 0 1 8 0V11m-9.5 0h11a1.5 1.5 0 0 1 1.5 1.5v6A1.5 1.5 0 0 1 17.5 20h-11A1.5 1.5 0 0 1 5 18.5v-6A1.5 1.5 0 0 1 6.5 11Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <input id="password" type="password" name="password" placeholder="Minimal 8 karakter" required autocomplete="new-password" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                            <button type="button" class="toggle-password" data-toggle-target="password" aria-label="Tampilkan kata sandi">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M2.8 12.2C4.3 8.7 7.7 6.5 12 6.5s7.7 2.2 9.2 5.7c-1.5 3.5-4.9 5.7-9.2 5.7s-7.7-2.2-9.2-5.7Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="12" cy="12.2" r="2.2" stroke="currentColor" stroke-width="1.7"/></svg>
                            </button>
                        </div>
                        @error('password')
                            <p class="field-error" id="password-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field-group">
                        <label for="password_confirmation">Konfirmasi Kata Sandi</label>
                        <div class="input-wrap {{ $errors->has('password_confirmation') ? 'is-invalid' : '' }}">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M8 11V8.5a4 4 0 0 1 8 0V11m-9.5 0h11a1.5 1.5 0 0 1 1.5 1.5v6A1.5 1.5 0 0 1 17.5 20h-11A1.5 1.5 0 0 1 5 18.5v-6A1.5 1.5 0 0 1 6.5 11Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <input id="password_confirmation" type="password" name="password_confirmation" placeholder="Ulangi kata sandi" required autocomplete="new-password" @error('password_confirmation') aria-invalid="true" aria-describedby="password-confirmation-error" @enderror>
                            <button type="button" class="toggle-password" data-toggle-target="password_confirmation" aria-label="Tampilkan kata sandi">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M2.8 12.2C4.3 8.7 7.7 6.5 12 6.5s7.7 2.2 9.2 5.7c-1.5 3.5-4.9 5.7-9.2 5.7s-7.7-2.2-9.2-5.7Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="12" cy="12.2" r="2.2" stroke="currentColor" stroke-width="1.7"/></svg>
                            </button>
                        </div>
                        @error('password_confirmation')
                            <p class="field-error" id="password-confirmation-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <button class="btn-submit" type="submit" data-register-submit data-loading-label="Mendaftarkan...">Daftar {{ $managerRoleLabel }}</button>
                </form>

                <p class="register-link">
                    Sudah punya akun {{ $managerRoleLabelLower }}?
                    <a href="{{ manager_route('login') }}">Masuk Sekarang</a>
                </p>
            </div>
        </section>
        {{-- Right Panel End --}}
    </main>
    {{-- Pengelola Barang Register Page End --}}
@endsection
