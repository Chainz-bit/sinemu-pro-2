@extends('super.layouts.app')

@php
    $managerRoleLabel = \App\Support\RoleLabels::manager();
    $pageTitle = 'Detail ' . $managerRoleLabel . ' - Super Admin';
    $activeMenu = 'admins';
    $searchAction = route('super.admins.index');
    $searchPlaceholder = 'Cari nama pengelola, instansi, atau kecamatan';
    $statusKey = \App\Support\AdminVerificationStatusPresenter::key($admin->status_verifikasi);
@endphp

@section('page-content')
    <div class="dashboard-page-content super-page-content super-manager-detail-page">
        <section class="intro">
            <h1>Detail Akun Pengelola</h1>
            <p>Lihat data lengkap akun pengelola barang, status verifikasi, dan informasi kontak.</p>
        </section>

        <section class="report-card dashboard-report-card">
            <header>
                <div class="report-heading">
                    <h2>{{ $admin->nama }}</h2>
                    <p>{{ $admin->instansi ?: 'Instansi belum diisi' }} | {{ $admin->kecamatan ?: 'Kecamatan belum diisi' }}</p>
                </div>
                <div class="report-actions">
                    <span class="status-chip {{ \App\Support\AdminVerificationStatusPresenter::badgeClass($statusKey) }}">
                        {{ \App\Support\AdminVerificationStatusPresenter::label($statusKey) }}
                    </span>
                </div>
            </header>

            <div class="super-manager-detail-layout">
                <div class="super-manager-detail-card">
                    <div><span>Nama Lengkap</span><strong>{{ $admin->nama }}</strong></div>
                    <div><span>Username</span><strong>{{ $admin->username }}</strong></div>
                    <div><span>Email</span><strong>{{ $admin->email }}</strong></div>
                    <div><span>Nomor HP</span><strong>{{ $admin->nomor_telepon ?: '-' }}</strong></div>
                    <div><span>Instansi</span><strong>{{ $admin->instansi ?: '-' }}</strong></div>
                    <div><span>Kecamatan</span><strong>{{ $admin->kecamatan ?: '-' }}</strong></div>
                    <div><span>Alamat/Wilayah Tugas</span><strong>{{ $admin->alamat_lengkap ?: '-' }}</strong></div>
                    <div><span>Status Akun</span><strong>{{ \App\Support\AdminVerificationStatusPresenter::label($statusKey) }}</strong></div>
                    <div><span>Tanggal Daftar</span><strong>{{ optional($admin->created_at)->format('d M Y H:i') ?? '-' }} WIB</strong></div>
                    <div><span>Terakhir Diperbarui</span><strong>{{ optional($admin->updated_at)->format('d M Y H:i') ?? '-' }} WIB</strong></div>
                    <div><span>Diverifikasi Pada</span><strong>{{ optional($admin->verified_at)->format('d M Y H:i') ?? '-' }}</strong></div>
                    <div><span>Catatan Penolakan/Revisi</span><strong>{{ $admin->alasan_penolakan ?: '-' }}</strong></div>
                </div>
            </div>

            <div class="super-form-actions">
                <a href="{{ route('super.admins.index') }}" class="super-inline-btn">Kembali ke Daftar</a>
                <a href="{{ route('super.admins.edit', $admin) }}" class="super-inline-btn is-accept">Edit Akun</a>
                <form method="POST"
                    action="{{ route('super.admins.destroy', $admin) }}"
                    data-confirm-delete
                    data-confirm-title="Hapus Akun Pengelola?"
                    data-confirm-message="Akun pengelola ini akan dihapus dari sistem. Tindakan ini tidak dapat dibatalkan. Nama: {{ $admin->nama }}"
                    data-confirm-submit-label="Ya, Hapus Akun"
                    data-confirm-submit-variant="danger">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="super-inline-btn is-reject">Hapus Akun</button>
                </form>
            </div>
        </section>
    </div>
@endsection
