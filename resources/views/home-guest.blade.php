@extends('layouts.main')
@section('content')
    <div class="container pb-5">
        <nav class="navbar navbar-expand-lg floating-nav px-3 px-lg-4">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="{{ route('home') }}">
                <img src="{{ asset('img/logo.png') }}" alt="Sinemu" class="brand-logo">
            </a>
            <div class="ms-auto d-flex gap-2">
                @if (Route::has('login'))
                    <a class="btn btn-sinemu btn-sinemu-outline" href="{{ route('login') }}">Masuk</a>
                @endif
                @if (Route::has('register'))
                    <a class="btn btn-sinemu btn-sinemu-primary" href="{{ route('register') }}">Daftar</a>
                @endif
            </div>
        </nav>

        <section class="section-space">
            <div class="hero-card hero-card-main">
                <h1 class="hero-title mb-3">Selamat Datang di <span class="accent">Sinemu</span></h1>
                <p class="hero-subtitle mb-4">Masuk atau daftar untuk melaporkan dan mencari barang hilang atau temuan di wilayah Anda.</p>
                <div class="d-flex flex-wrap gap-3">
                    @if (Route::has('login'))
                        <a class="btn btn-sinemu btn-sinemu-primary" href="{{ route('login') }}">Masuk</a>
                    @endif
                    @if (Route::has('register'))
                        <a class="btn btn-sinemu btn-sinemu-outline" href="{{ route('register') }}">Daftar Akun</a>
                    @endif
                </div>
            </div>
        </section>
    </div>
@endsection
