@extends('super.layouts.app')

@php
    $pageTitle = 'Tambah Akun Pengelola - Super Admin';
    $activeMenu = 'admins-create';
    $hideSuperSearch = true;
@endphp

@section('page-content')
    <div class="dashboard-page-content super-page-content super-manager-form-page">
        <section class="intro super-manager-form-hero">
            <nav class="super-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('super.dashboard') }}">Super Admin</a>
                <span>/</span>
                <a href="{{ route('super.admins.index') }}">Pengelola Barang</a>
                <span>/</span>
                <strong>Tambah Akun</strong>
            </nav>
            <div>
                <h1>Tambah Akun Pengelola</h1>
                <p>Buat akun pengelola barang baru dan tentukan status awal akun.</p>
            </div>
        </section>

        <section class="report-card dashboard-report-card super-manager-form-card">
            <header>
                <div class="report-heading">
                    <h2>Informasi Akun Pengelola</h2>
                    <p>Isi data akun dengan benar. Password akan disimpan secara aman dan tidak ditampilkan kembali.</p>
                </div>
            </header>

            <form method="POST" action="{{ route('super.admins.store') }}" class="super-manager-form" data-super-manager-form>
                @csrf
                @include('super.pages.admins._form')

                <div class="super-form-actions">
                    <a href="{{ route('super.admins.index') }}" class="super-inline-btn" data-cancel-form-link>Batal</a>
                    <button type="submit" class="super-inline-btn is-accept" data-submit-label="Simpan Akun" data-loading-label="Menyimpan...">Simpan Akun</button>
                </div>
            </form>
        </section>
    </div>
@endsection
