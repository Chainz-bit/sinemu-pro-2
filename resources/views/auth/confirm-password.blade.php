@extends('layouts.auth')

@php
    /** @var \Illuminate\Support\ViewErrorBag $errors */
@endphp

@section('content')
    <main style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:#eef1f5;padding:1rem;">
        <div style="width:min(460px,100%);background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:1.25rem;">
            <h1 style="margin:0 0 .5rem;font-size:1.25rem;">Konfirmasi Kata Sandi</h1>
            <p style="margin:0 0 1rem;color:#6b7280;">Masukkan kata sandi untuk melanjutkan.</p>

            @if ($errors->any())
                <div style="margin-bottom:1rem;padding:.75rem;border-radius:10px;background:#fee2e2;border:1px solid #fecaca;color:#991b1b;">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.confirm') }}">
                @csrf
                <label for="password" style="display:block;margin-bottom:.45rem;font-weight:600;">Kata Sandi</label>
                <input id="password" name="password" type="password" required autofocus
                    style="width:100%;height:44px;border:1px solid #d1d5db;border-radius:10px;padding:0 .85rem;margin-bottom:1rem;">
                <button type="submit" style="width:100%;height:44px;border:0;border-radius:10px;background:#5a3ff6;color:#fff;font-weight:700;">
                    Konfirmasi
                </button>
            </form>
        </div>
    </main>
@endsection
