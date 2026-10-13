@extends('super.layouts.app')

@php
    $pageTitle = 'Profil Saya - Super Admin - SiNemu';
    $activeMenu = 'profile';
    $searchAction = route('super.admins.index');
    $searchPlaceholder = 'Cari kecamatan atau instansi';
    $topbarBackUrl = route('super.dashboard');
    $topbarBackLabel = 'Kembali ke Dashboard';
    $hideSuperSidebar = true;
    $hideSuperSearch = true;
    $managerRoleLabel = \App\Support\RoleLabels::manager();
    $managerRoleLabelLower = \App\Support\RoleLabels::managerLower();
@endphp

@section('page-content')
    <div class="profile-page-content super-page-content super-profile-page">
        <section class="profile-account-card">
            <div class="profile-account-top">
                <div class="profile-account-main">
                    <img src="{{ $profileAvatar }}" alt="Foto profil {{ $superAdmin?->nama ?? 'Super Admin' }}" class="profile-account-avatar" data-fallback="{{ asset('img/profil.jpg') }}" onerror="this.onerror=null;this.src=this.dataset.fallback;">
                    <div class="profile-account-meta">
                        <div class="profile-account-name-wrap">
                            <h2>{{ $superAdmin?->nama ?? 'Super Admin' }}</h2>
                            <span class="super-profile-badge">System Owner</span>
                        </div>
                        <p class="profile-role">Super Admin SiNemu | Pengelola Sistem</p>
                        <div class="profile-account-contact">
                            <span>{{ $superAdmin?->email ?? '-' }} | {{ $superAdmin?->username ?? '-' }}</span>
                        </div>
                    </div>
                </div>
                <div class="profile-actions">
                    <a href="{{ route('super.admin-verifications.index', ['status' => 'pending']) }}" class="profile-action-primary">Proses Verifikasi</a>
                    <a href="{{ route('super.profile.edit') }}" class="profile-action-secondary">Edit Profil</a>
                </div>
            </div>

            <div class="profile-admin-info-grid">
                <article class="profile-info-card">
                    <span class="profile-info-label">Akun</span>
                    <strong class="profile-info-value">{{ $superAdmin?->username ?? '-' }}</strong>
                </article>
                <article class="profile-info-card">
                    <span class="profile-info-label">Role</span>
                    <strong class="profile-info-value">Super Admin</strong>
                </article>
                <article class="profile-info-card">
                    <span class="profile-info-label">Email</span>
                    <strong class="profile-info-value">{{ $superAdmin?->email ?? '-' }}</strong>
                </article>
                <article class="profile-info-card">
                    <span class="profile-info-label">Terakhir Update</span>
                    <strong class="profile-info-value">{{ optional($superAdmin?->updated_at)->format('d M Y H:i') ?? '-' }} WIB</strong>
                </article>
            </div>
        </section>

        <section class="profile-stats-grid">
            <article class="profile-stat-card super-stat-total">
                <span>Total {{ $managerRoleLabel }}</span>
                <strong>{{ $totalAdmin }}</strong>
                <small>Semua akun {{ $managerRoleLabelLower }} terdaftar</small>
            </article>
            <article class="profile-stat-card super-stat-pending">
                <span>Menunggu Verifikasi</span>
                <strong>{{ $pendingAdmin }}</strong>
                <small>Perlu keputusan super admin</small>
            </article>
            <article class="profile-stat-card super-stat-active">
                <span>{{ $managerRoleLabel }} Aktif</span>
                <strong>{{ $activeAdmin }}</strong>
                <small>Sudah disetujui dan aktif</small>
            </article>
        </section>

        <section class="report-card profile-activity-card">
            <header>
                <h2>Aktivitas Verifikasi Terbaru</h2>
                <a href="{{ route('super.admin-verifications.index') }}">Buka Verifikasi</a>
            </header>

            <div class="profile-activity-list">
                @forelse($recentActivities as $activity)
                    <article class="profile-activity-item">
                        <div class="profile-activity-main">
                            <h3>{{ $activity->title }}</h3>
                            <p>
                                {{ \Carbon\Carbon::parse($activity->timestamp)->translatedFormat('d M Y, H:i') }} WIB
                                | {{ $activity->subtitle }}
                            </p>
                        </div>
                        <div class="profile-activity-right">
                            <x-status-badge :status="$activity->status_class" :label="$activity->status_label" />
                            <a href="{{ $activity->detail_url }}">Lihat Detail</a>
                        </div>
                    </article>
                @empty
                    <div class="profile-activity-empty">Belum ada aktivitas verifikasi untuk ditampilkan.</div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
