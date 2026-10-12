@extends('manager::layouts.app')

@php
    $managerRoleLabel = \App\Support\RoleLabels::manager();
    $managerRoleLabelLower = \App\Support\RoleLabels::managerLower();
    $pageTitle = 'Profil Saya - ' . $managerRoleLabel . ' - SiNemu';
    $activeMenu = 'profile';
    $hideSearch = true;
    $hideSidebar = true;
    $topbarBackUrl = manager_route('dashboard');
    $topbarBackLabel = 'Kembali ke Dashboard';
@endphp

@section('page-content')
    <div class="profile-page-content">
<section class="profile-account-card">
            <div class="profile-account-top">
                <div class="profile-account-main">
                    <img src="{{ $profileAvatar }}" alt="Foto profil {{ $manager?->nama ?? $managerRoleLabel }}" class="profile-account-avatar" data-fallback="{{ asset('img/profil.jpg') }}" onerror="this.onerror=null;this.src=this.dataset.fallback;">
                    <div class="profile-account-meta">
                        <div class="profile-account-name-wrap">
                            <h2>{{ $manager?->nama ?? $managerRoleLabel }}</h2>
                        </div>
                        <p class="profile-role">{{ $managerRoleLabel }} SiNemu - Pengelola Operasional</p>
                        <div class="profile-account-contact">
                            <span>{{ $manager?->email ?? '-' }}</span>
                        </div>
                    </div>
                </div>
                <div class="profile-actions">
                    <a href="{{ manager_route('profile.edit') }}" class="profile-action-secondary">
                        Edit Profil
                    </a>
                </div>
            </div>

            <div class="profile-admin-info-grid">
                <article class="profile-info-card">
                    <span class="profile-info-label">Akun</span>
                    <strong class="profile-info-value">{{ $manager?->username ?? '-' }}</strong>
                </article>
                <article class="profile-info-card">
                    <span class="profile-info-label">Instansi Aktif</span>
                    <strong class="profile-info-value">{{ $manager?->instansi ?? '-' }}</strong>
                </article>
                <article class="profile-info-card">
                    <span class="profile-info-label">Wilayah</span>
                    <strong class="profile-info-value">{{ $manager?->kecamatan ?? '-' }}</strong>
                </article>
                <article class="profile-info-card">
                    <span class="profile-info-label">Alamat Operasional</span>
                    <strong class="profile-info-value">{{ $manager?->alamat_lengkap ?? '-' }}</strong>
                </article>
            </div>
        </section>

        <section class="profile-stats-grid">
            <article class="profile-stat-card">
                <span>Laporan Diajukan</span>
                <strong>{{ $laporanDiajukan }}</strong>
                <small>Total input oleh akun ini</small>
            </article>
            <article class="profile-stat-card">
                <span>Klaim Menunggu</span>
                <strong>{{ $klaimMenunggu }}</strong>
                <small>Perlu tindak lanjut verifikasi</small>
            </article>
            <article class="profile-stat-card">
                <span>Selesai Ditangani</span>
                <strong>{{ $selesaiDitangani }}</strong>
                <small>Klaim sudah diputuskan {{ $managerRoleLabelLower }}</small>
            </article>
        </section>

        <section class="report-card profile-activity-card">
            <header>
                <h2>Aktivitas Terbaru</h2>
                <a href="{{ manager_route('dashboard') }}">Buka Dashboard</a>
            </header>

            <div class="profile-activity-list">
                @forelse($recentActivities as $activity)
                    <article class="profile-activity-item">
                        <div class="profile-activity-main">
                            <h3>{{ $activity->title }}</h3>
                            <p>
                                {{ \Carbon\Carbon::parse($activity->timestamp)->translatedFormat('d M Y, H:i') }} WIB
                            </p>
                        </div>
                        <div class="profile-activity-right">
                            <x-status-badge :status="$activity->status_class" :label="$activity->status_label" />
                            <a href="{{ $activity->detail_url }}">Lihat Detail</a>
                        </div>
                    </article>
                @empty
                    <div class="profile-activity-empty">Belum ada aktivitas terbaru untuk ditampilkan.</div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
