@extends('super.layouts.app')

@php
    $pageTitle = 'Edit Akun Pengelola - Super Admin';
    $activeMenu = 'admins';
    $searchAction = route('super.admins.index');
    $searchPlaceholder = 'Cari nama pengelola, instansi, atau kecamatan';
@endphp

@section('page-content')
    <div class="dashboard-page-content super-page-content super-manager-form-page">
        <section class="intro">
            <h1>Edit Akun Pengelola</h1>
            <p>Perbarui data akun pengelola barang tanpa menampilkan password lama.</p>
        </section>

        <section class="report-card dashboard-report-card">
            <header>
                <div class="report-heading">
                    <h2>{{ $admin->nama }}</h2>
                    <p>Jika password dikosongkan, password lama tetap digunakan.</p>
                </div>
            </header>

            <form method="POST" action="{{ route('super.admins.update', $admin) }}" class="super-manager-form">
                @csrf
                @method('PUT')
                @include('super.pages.admins._form', ['admin' => $admin])

                <div class="super-form-actions">
                    <a href="{{ route('super.admins.show', $admin) }}" class="super-inline-btn">Batal</a>
                    <button type="submit" class="super-inline-btn is-accept">Simpan Perubahan</button>
                </div>
            </form>
        </section>
    </div>
@endsection
