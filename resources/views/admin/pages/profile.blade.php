@extends('admin.layouts.app')

@php
    $pageTitle = 'Profil Saya - Admin - SiNemu';
    $activeMenu = 'profile';
    $hideSearch = true;
    $hideSidebar = true;
    $topbarBackUrl = route('admin.dashboard');
    $topbarBackLabel = 'Kembali ke Dashboard';
@endphp

@section('page-content')
    <div class="profile-page-content">
        @if(session('status'))
            <div class="feedback-alert feedback-alert-toast feedback-alert-popup success" data-autoclose="3200" style="--autoclose-ms: 3200ms;" role="status" aria-live="polite">
                <span class="feedback-alert-icon" aria-hidden="true">
                    <iconify-icon icon="mdi:check-circle"></iconify-icon>
                </span>
                <div class="feedback-alert-body">
                    <strong>Berhasil</strong>
                    <span>{{ session('status') }}</span>
                </div>
                <button type="button" class="feedback-alert-close" data-alert-close aria-label="Tutup notifikasi">
                    <iconify-icon icon="mdi:close"></iconify-icon>
                </button>
                <span class="feedback-alert-progress" aria-hidden="true"></span>
            </div>
        @endif

        <section class="profile-account-card">
            <div class="profile-account-top">
                <div class="profile-account-main">
                    <img src="{{ $profileAvatar }}" alt="Foto profil {{ $admin?->nama ?? 'Admin' }}" class="profile-account-avatar">
                    <div class="profile-account-meta">
                        <div class="profile-account-name-wrap">
                            <h2>{{ $admin?->nama ?? 'Admin' }}</h2>
                            <span class="profile-verify-chip {{ $verificationClass }}">{{ $verificationLabel }}</span>
                        </div>
                        <p class="profile-role">Admin SiNemu • Pengelola Operasional</p>
                        <div class="profile-account-contact">
                            <span>{{ $admin?->email ?? '-' }}</span>
                        </div>
                    </div>
                </div>
                <div class="profile-actions">
                    <a href="{{ route('admin.profile.edit') }}" class="profile-action-secondary">
                        Edit Profil
                    </a>
                </div>
            </div>

            <div class="profile-admin-info-grid">
                <article class="profile-info-card">
                    <span class="profile-info-label">Akun</span>
                    <strong class="profile-info-value">{{ $admin?->username ?? '-' }}</strong>
                </article>
                <article class="profile-info-card">
                    <span class="profile-info-label">Instansi Aktif</span>
                    <strong class="profile-info-value">{{ $admin?->instansi ?? '-' }}</strong>
                </article>
                <article class="profile-info-card">
                    <span class="profile-info-label">Wilayah</span>
                    <strong class="profile-info-value">{{ $admin?->kecamatan ?? '-' }}</strong>
                </article>
                <article class="profile-info-card">
                    <span class="profile-info-label">Alamat Operasional</span>
                    <strong class="profile-info-value">{{ $admin?->alamat_lengkap ?? '-' }}</strong>
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
                <small>Klaim sudah diputuskan admin</small>
            </article>
        </section>

        <section class="report-card profile-activity-card">
            <header>
                <h2>Aktivitas Terbaru</h2>
                <a href="{{ route('admin.dashboard') }}">Buka Dashboard</a>
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
                            <span class="status-chip status-{{ $activity->status_class }}">{{ $activity->status_label }}</span>
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
