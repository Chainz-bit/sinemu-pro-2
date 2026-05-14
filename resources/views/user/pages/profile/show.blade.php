@extends('user.layouts.app')

@php
    $pageTitle = 'Profil Saya - User - SiNemu';
    $activeMenu = 'profile';
    $hideSearch = true;
    $hideSidebar = true;
    $topbarBackUrl = route('user.dashboard');
    $topbarBackLabel = 'Kembali ke Dashboard';
@endphp

@section('page-content')
    <div class="profile-page-content">
        <section class="profile-account-card">
            <div class="profile-account-top">
                <div class="profile-account-main">
                    <img src="{{ $profileAvatar }}" alt="Foto profil {{ $user?->nama ?? $user?->name ?? 'Pengguna' }}" class="profile-account-avatar" onerror="this.onerror=null;this.src='{{ asset('img/profil.jpg') }}';">
                    <div class="profile-account-meta">
                        <div class="profile-account-name-wrap">
                            <h2>{{ $user?->nama ?? $user?->name ?? 'Pengguna' }}</h2>
                        </div>
                        <p class="profile-role">Pengguna SiNemu - Pelapor Barang</p>
                        <div class="profile-account-contact">
                            <span>{{ $user?->email ?? '-' }}</span>
                        </div>
                    </div>
                </div>
                <div class="profile-actions">
                    <a href="{{ route('profile.edit') }}" class="profile-action-secondary">
                        Edit Profil
                    </a>
                </div>
            </div>

            <div class="profile-admin-info-grid user-profile-info-grid">
                <article class="profile-info-card">
                    <span class="profile-info-label">Akun</span>
                    <strong class="profile-info-value">{{ $user?->username ?? '-' }}</strong>
                </article>
                <article class="profile-info-card">
                    <span class="profile-info-label">Jenis Akun</span>
                    <strong class="profile-info-value">Pelapor</strong>
                </article>
                <article class="profile-info-card">
                    <span class="profile-info-label">Nomor Telepon</span>
                    <strong class="profile-info-value">{{ $user?->nomor_telepon ?: '-' }}</strong>
                </article>
                <article class="profile-info-card">
                    <span class="profile-info-label">Status Akun</span>
                    <strong class="profile-info-value">Aktif</strong>
                </article>
                <article class="profile-info-card">
                    <span class="profile-info-label">Bergabung Sejak</span>
                    <strong class="profile-info-value">{{ !empty($user?->created_at) ? \Carbon\Carbon::parse($user->created_at)->translatedFormat('d M Y') : '-' }}</strong>
                </article>
            </div>
        </section>

        <section class="profile-stats-grid">
            <article class="profile-stat-card">
                <span>Laporan Diajukan</span>
                <strong>{{ $laporanDiajukan }}</strong>
                <small>Total laporan hilang oleh akun ini</small>
            </article>
            <article class="profile-stat-card">
                <span>Klaim Menunggu Tinjauan</span>
                <strong>{{ $klaimMenunggu }}</strong>
                <small>Masih menunggu tinjauan admin</small>
            </article>
            <article class="profile-stat-card">
                <span>Klaim Diputuskan</span>
                <strong>{{ $klaimSelesai }}</strong>
                <small>Klaim sudah selesai atau tidak disetujui</small>
            </article>
        </section>

        <section class="report-card profile-activity-card">
            <header>
                <h2>Aktivitas Terbaru</h2>
                <a href="{{ route('user.dashboard') }}">Buka Dashboard</a>
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
