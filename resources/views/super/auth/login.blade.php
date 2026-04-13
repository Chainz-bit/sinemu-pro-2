@extends('layouts.auth')

@section('content')
<main style="min-height:100vh;display:grid;place-items:center;padding:16px;background:linear-gradient(150deg,#eef4ff,#f0fff6);font-family:'Plus Jakarta Sans',sans-serif;">
    <section style="width:min(440px,100%);background:#fff;border:1px solid #dbe5f2;border-radius:16px;box-shadow:0 20px 40px rgba(15,23,42,.14);padding:18px;">
        <div style="text-align:center;margin-bottom:14px;">
            <img src="{{ asset('img/logo.png') }}" alt="Sinemu" style="width:130px;height:auto;" />
            <h1 style="margin:10px 0 4px;font-size:1.25rem;color:#0f2f63;">Login Super Admin</h1>
            <p style="margin:0;color:#64748b;font-size:.9rem;">Verifikasi pendaftaran admin SiNemu</p>
        </div>

        @if ($errors->any())
            <div style="margin-bottom:10px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:10px;padding:10px;font-size:.85rem;">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('super.login.store') }}" style="display:grid;gap:10px;">
            @csrf
            <div>
                <label for="email" style="display:block;margin-bottom:4px;font-size:.86rem;font-weight:700;color:#334155;">Email Super Admin</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required
                       style="width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:10px 12px;font-size:.9rem;" placeholder="superadmin@sinemu.com" />
            </div>
            <div>
                <label for="password" style="display:block;margin-bottom:4px;font-size:.86rem;font-weight:700;color:#334155;">Kata Sandi</label>
                <input id="password" name="password" type="password" required
                       style="width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:10px 12px;font-size:.9rem;" placeholder="********" />
            </div>
            <button type="submit" style="border:0;border-radius:10px;padding:10px 12px;font-size:.92rem;font-weight:800;background:linear-gradient(135deg,#2563eb,#4f46e5);color:#fff;cursor:pointer;">Masuk</button>
        </form>

        <p style="margin:12px 0 0;font-size:.82rem;color:#64748b;text-align:center;">Akun default: superadmin@sinemu.com</p>
    </section>
</main>
@endsection
