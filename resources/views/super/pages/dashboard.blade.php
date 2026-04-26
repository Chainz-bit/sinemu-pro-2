@extends('super.layouts.app')

@php
    $pageTitle = 'Dashboard Super Admin - SiNemu';
    $activeMenu = 'dashboard';
    $searchAction = route('super.admins.index');
    $searchPlaceholder = 'Cari kecamatan atau instansi';
@endphp

@section('page-content')
    @php
        $hasPriorityAdmins = ($summary['pending'] ?? 0) > 0 && $priorityAdmins->isNotEmpty();
    @endphp
    <div class="dashboard-page-content super-page-content">
        <section class="intro">
            <h1>Ringkasan Dashboard Super Admin</h1>
            <p>Kontrol verifikasi admin, pantau pertumbuhan pendaftar, dan fokus ke akun yang perlu ditindak segera.</p>
        </section>

        <section class="stats-grid super-stats-grid">
            <article class="stat-card super-stat-card super-stat-card-primary">
                <div class="stat-card-head">
                    <span>Total Admin Terdaftar</span>
                    <div class="stat-card-icon">
                        <iconify-icon icon="mdi:account-group-outline"></iconify-icon>
                    </div>
                </div>
                <strong>{{ $summary['total'] ?? 0 }}</strong>
                <small>Semua akun admin yang pernah terdaftar di sistem.</small>
            </article>
            <article class="stat-card super-stat-card super-stat-card-warning">
                <div class="stat-card-head">
                    <span>Menunggu Verifikasi</span>
                    <div class="stat-card-icon">
                        <iconify-icon icon="mdi:clock-alert-outline"></iconify-icon>
                    </div>
                </div>
                <strong>{{ $summary['pending'] ?? 0 }}</strong>
                <small>Pendaftar yang perlu keputusan super admin.</small>
            </article>
            <article class="stat-card super-stat-card super-stat-card-danger">
                <div class="stat-card-head">
                    <span>Ditolak / Revisi</span>
                    <div class="stat-card-icon">
                        <iconify-icon icon="mdi:close-octagon-outline"></iconify-icon>
                    </div>
                </div>
                <strong>{{ $summary['rejected'] ?? 0 }}</strong>
                <small>Akun yang ditolak dan menunggu perbaikan data.</small>
            </article>
            <article class="stat-card super-stat-card super-stat-card-success">
                <div class="stat-card-head">
                    <span>Admin Baru 7 Hari</span>
                    <div class="stat-card-icon">
                        <iconify-icon icon="mdi:chart-timeline-variant"></iconify-icon>
                    </div>
                </div>
                <strong>{{ $summary['newThisWeek'] ?? 0 }}</strong>
                <small>Pertumbuhan pendaftaran admin selama satu minggu terakhir.</small>
            </article>
        </section>

        <section class="super-dashboard-focus">
            <article class="report-card super-activity-card super-focus-card">
                <header>
                    <div class="report-heading">
                        <h2>Aktivitas Verifikasi</h2>
                        <p>Perubahan status terbaru dari proses approval admin.</p>
                    </div>
                    <div class="report-actions">
                        <a href="{{ route('super.admin-verifications.index', ['status' => 'semua']) }}#daftar-verifikasi">Riwayat Verifikasi</a>
                    </div>
                </header>

                <div class="super-activity-list">
                    @forelse($latestActivities as $admin)
                        @php
                            $statusKey = \App\Support\AdminVerificationStatusPresenter::key($admin->status_verifikasi);
                        @endphp
                        <article class="super-activity-item">
                            <span class="status-chip {{ \App\Support\AdminVerificationStatusPresenter::badgeClass($statusKey) }}">
                                {{ \App\Support\AdminVerificationStatusPresenter::label($statusKey) }}
                            </span>
                            <div>
                                <strong>{{ $admin->nama }}</strong>
                                <small>
                                    {{ $admin->instansi ?: 'Instansi belum diisi' }}
                                    |
                                    {{ $admin->verified_at?->format('d M Y H:i') ?? optional($admin->updated_at)->format('d M Y H:i') ?? '-' }}
                                </small>
                            </div>
                            <a class="super-activity-link" href="{{ route('super.admin-verifications.index', ['search' => $admin->nama]) }}">Buka</a>
                        </article>
                    @empty
                        <div class="claim-create-empty super-empty-panel">
                            <iconify-icon icon="mdi:timeline-clock-outline"></iconify-icon>
                            <strong>Belum ada aktivitas</strong>
                            <p>Riwayat verifikasi akan muncul setelah super admin mulai memproses data.</p>
                        </div>
                    @endforelse
                </div>
            </article>

            <section class="report-card dashboard-report-card super-focus-card">
                <header>
                    <div class="report-heading">
                        <h2>Admin Baru Terdaftar</h2>
                        <p>Gunakan daftar ini untuk memantau pendaftar terbaru dan membuka data lengkap.</p>
                    </div>
                    <div class="report-actions">
                        <a href="{{ route('super.admins.index') }}">Lihat Semua</a>
                    </div>
                </header>

                <div class="report-table-wrap">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Detail Admin</th>
                                <th>Tanggal Daftar</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($newestAdmins as $index => $admin)
                                @php
                                    $statusKey = \App\Support\AdminVerificationStatusPresenter::key($admin->status_verifikasi);
                                @endphp
                                <tr>
                                    <td>
                                        <div class="item-cell">
                                            <div class="item-avatar avatar-claim">
                                                <span class="item-avatar-fallback">{{ strtoupper(substr((string) $admin->nama, 0, 1)) }}</span>
                                            </div>
                                            <div>
                                                <strong>{{ $admin->nama }}</strong>
                                                <small>{{ $admin->instansi ?: 'Instansi belum diisi' }} | {{ $admin->kecamatan ?: 'Kecamatan belum diisi' }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="date-cell">
                                            <strong>{{ optional($admin->created_at)->format('d M Y') ?? '-' }}</strong>
                                            <small>{{ optional($admin->created_at)->format('H:i') ?? '-' }} WIB</small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-chip {{ \App\Support\AdminVerificationStatusPresenter::badgeClass($statusKey) }}">
                                            {{ \App\Support\AdminVerificationStatusPresenter::label($statusKey) }}
                                        </span>
                                    </td>
                                    <td class="menu-cell">
                                        <button type="button" class="row-menu-trigger" data-menu-target="super-dashboard-menu-{{ $index }}" aria-label="Aksi">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M12 5.5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3zm0 5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3zm0 5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3z" fill="currentColor"/>
                                            </svg>
                                        </button>
                                        <div class="row-menu" id="super-dashboard-menu-{{ $index }}">
                                            <a href="{{ route('super.admins.index', ['search' => $admin->nama]) }}">Lihat Detail</a>
                                            <a href="{{ route('super.admin-verifications.index', ['search' => $admin->nama]) }}">Buka Verifikasi</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr class="super-table-empty-row">
                                    <td colspan="4" class="empty-row">
                                        <div class="super-table-empty-state">
                                            <iconify-icon icon="mdi:account-search-outline"></iconify-icon>
                                            <strong>Belum ada admin terdaftar</strong>
                                            <p>Data admin baru akan muncul di sini setelah ada pendaftaran akun admin.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </section>

        @if($hasPriorityAdmins)
            <section class="super-dashboard-grid">
                <article class="report-card super-priority-card">
                    <header>
                        <div class="report-heading">
                            <h2>Butuh Tindakan Sekarang</h2>
                            <p>Urutkan penyelesaian berdasarkan antrean pendaftar yang paling baru.</p>
                        </div>
                        <div class="report-actions">
                            <a href="{{ route('super.admin-verifications.index', ['status' => 'pending']) }}#daftar-verifikasi">Lihat Semua Pending</a>
                        </div>
                    </header>

                    <div class="super-priority-list">
                        @foreach($priorityAdmins as $admin)
                            <article class="super-priority-item">
                                <div class="super-priority-meta">
                                    <strong>{{ $admin->nama }}</strong>
                                    <small>{{ $admin->instansi ?: 'Instansi belum diisi' }} | {{ $admin->kecamatan ?: 'Kecamatan belum diisi' }}</small>
                                    <small>Didaftarkan {{ optional($admin->created_at)->diffForHumans() ?? '-' }}</small>
                                </div>
                                <div class="super-priority-actions">
                                    <form method="POST" action="{{ route('super.admin-verifications.accept', $admin->id) }}">
                                        @csrf
                                        <button type="submit" class="super-inline-btn is-accept">Setujui</button>
                                    </form>
                                    <a href="{{ route('super.admin-verifications.index', ['search' => $admin->nama]) }}" class="super-inline-btn">Tinjau Detail</a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </article>
            </section>
        @endif
    </div>

    <script>
        document.body.classList.remove('dashboard-fixed-mode');
        document.body.classList.add('super-dashboard-page-mode');
    </script>
@endsection
