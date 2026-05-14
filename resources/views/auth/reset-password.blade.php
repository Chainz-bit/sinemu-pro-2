@extends('layouts.auth')

@section('content')
    <main style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:#eef1f5;padding:1rem;">
        <div style="width:min(460px,100%);background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:1.25rem;">
            <h1 style="margin:0 0 .5rem;font-size:1.25rem;">Reset Kata Sandi</h1>
            <p style="margin:0 0 1rem;color:#6b7280;">Masukkan email dan kata sandi baru Anda.</p>

            @if ($errors->any())
                <div style="margin-bottom:1rem;padding:.75rem;border-radius:10px;background:#fee2e2;border:1px solid #fecaca;color:#991b1b;">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.store') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <label for="email" style="display:block;margin-bottom:.45rem;font-weight:600;">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required autofocus
                    style="width:100%;height:44px;border:1px solid #d1d5db;border-radius:10px;padding:0 .85rem;margin-bottom:.8rem;">

                <label for="password" style="display:block;margin-bottom:.45rem;font-weight:600;">Kata Sandi Baru</label>
                <div class="auth-password-field" style="margin-bottom:.8rem;">
                    <input id="password" name="password" type="password" required
                        style="width:100%;height:44px;border:1px solid #d1d5db;border-radius:10px;padding:0 .85rem;">
                    <button type="button" class="auth-password-toggle" data-reset-password-toggle="password" aria-label="Tampilkan kata sandi" aria-pressed="false">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.8 12.2C4.3 8.7 7.7 6.5 12 6.5s7.7 2.2 9.2 5.7c-1.5 3.5-4.9 5.7-9.2 5.7s-7.7-2.2-9.2-5.7Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="12" cy="12.2" r="2.2" stroke="currentColor" stroke-width="1.7"/></svg>
                    </button>
                </div>

                <label for="password_confirmation" style="display:block;margin-bottom:.45rem;font-weight:600;">Konfirmasi Kata Sandi</label>
                <div class="auth-password-field" style="margin-bottom:1rem;">
                    <input id="password_confirmation" name="password_confirmation" type="password" required
                        style="width:100%;height:44px;border:1px solid #d1d5db;border-radius:10px;padding:0 .85rem;">
                    <button type="button" class="auth-password-toggle" data-reset-password-toggle="password_confirmation" aria-label="Tampilkan kata sandi" aria-pressed="false">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.8 12.2C4.3 8.7 7.7 6.5 12 6.5s7.7 2.2 9.2 5.7c-1.5 3.5-4.9 5.7-9.2 5.7s-7.7-2.2-9.2-5.7Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="12" cy="12.2" r="2.2" stroke="currentColor" stroke-width="1.7"/></svg>
                    </button>
                </div>

                <button type="submit" style="width:100%;height:44px;border:0;border-radius:10px;background:#5a3ff6;color:#fff;font-weight:700;">
                    Simpan Kata Sandi
                </button>
            </form>
        </div>
    </main>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-reset-password-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.getElementById(button.getAttribute('data-reset-password-toggle'));
                if (!input) return;

                var shouldShow = input.type === 'password';
                input.type = shouldShow ? 'text' : 'password';
                button.setAttribute('aria-pressed', shouldShow ? 'true' : 'false');
                button.setAttribute('aria-label', shouldShow ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
            });
        });
    </script>
@endpush
