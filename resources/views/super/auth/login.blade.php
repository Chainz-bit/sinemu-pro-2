@extends('layouts.auth')

@push('styles')
    @vite('resources/js/entries/super-auth-login.js')
@endpush

@section('content')
<main class="super-login-shell">
    <section class="super-login-card">
        <div class="super-login-head">
            <div class="super-login-brand">
                <img src="{{ asset('img/logo.png') }}" alt="Sinemu">
            </div>
            <div class="super-login-title-wrap">
                <span class="super-login-pill">Portal Internal</span>
                <h1>Login Super Admin</h1>
                <p>Akses ini khusus untuk pengelolaan verifikasi admin dan pemantauan operasional sistem.</p>
            </div>
        </div>

        @if ($errors->any())
            <div class="super-login-alert">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('super.login.store') }}" class="super-login-form">
            @csrf
            <input type="text" name="fake_login" autocomplete="username" tabindex="-1" aria-hidden="true" style="display:none">
            <input type="password" name="fake_password" autocomplete="current-password" tabindex="-1" aria-hidden="true" style="display:none">
            <div class="super-login-field">
                <label for="login">Email atau Username Super Admin</label>
                <div class="super-input-wrap">
                    <span class="super-input-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M4 7.5A2.5 2.5 0 0 1 6.5 5h11A2.5 2.5 0 0 1 20 7.5v9a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 16.5v-9Zm1.8-.2 5.85 4.45a.6.6 0 0 0 .7 0l5.85-4.45" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <input id="login" name="login" type="text" required placeholder="admin" autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false" readonly onfocus="this.removeAttribute('readonly');">
                </div>
                <small class="super-login-help">Gunakan email internal atau username yang terdaftar.</small>
            </div>
            <div class="super-login-field">
                <label for="password">Kata Sandi</label>
                <div class="super-input-wrap">
                    <span class="super-input-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M8 11V8.5a4 4 0 0 1 8 0V11m-9.5 0h11a1.5 1.5 0 0 1 1.5 1.5v6A1.5 1.5 0 0 1 17.5 20h-11A1.5 1.5 0 0 1 5 18.5v-6A1.5 1.5 0 0 1 6.5 11Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <input id="password" name="password" type="password" required placeholder="Masukkan kata sandi" autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false" readonly onfocus="this.removeAttribute('readonly');">
                    <button type="button" class="super-password-toggle" data-toggle-password="password" aria-label="Tampilkan kata sandi">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M2.8 12.2C4.3 8.7 7.7 6.5 12 6.5s7.7 2.2 9.2 5.7c-1.5 3.5-4.9 5.7-9.2 5.7s-7.7-2.2-9.2-5.7Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                            <circle cx="12" cy="12.2" r="2.2" stroke="currentColor" stroke-width="1.7"/>
                        </svg>
                    </button>
                </div>
                <small class="super-login-help">Pastikan Anda login di perangkat internal yang aman.</small>
            </div>
            <button type="submit" class="super-login-submit">Masuk</button>
        </form>

        <div class="super-login-note">
            URL ini tidak ditampilkan di landing page publik. Gunakan hanya untuk operasional internal.
        </div>
    </section>
</main>
@endsection

@push('scripts')
    <script>
        document.body.classList.add('super-login-fixed');
        window.addEventListener('pageshow', function () {
            ['login', 'password'].forEach(function (id) {
                var input = document.getElementById(id);
                if (!input) return;
                input.value = '';
            });
        });
        document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
            button.addEventListener('click', function () {
                var targetId = button.getAttribute('data-toggle-password');
                var input = targetId ? document.getElementById(targetId) : null;
                if (!input) return;
                var isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                button.setAttribute('aria-label', isPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
            });
        });
    </script>
@endpush
