@extends('super.layouts.app')

@php
    $pageTitle = 'Dashboard Super Admin - SiNemu';
    $activeMenu = 'dashboard';
    $searchAction = route('super.admins.index');
    $searchPlaceholder = 'Cari kecamatan atau instansi';
    $managerRoleLabel = \App\Support\RoleLabels::manager();
    $managerRoleLabelLower = \App\Support\RoleLabels::managerLower();
@endphp

@section('page-content')
    <div class="dashboard-page-content super-page-content">
        <section class="intro">
            <h1>Ringkasan Dashboard Super Admin</h1>
            <p>Pantau jumlah akun {{ $managerRoleLabelLower }}, status verifikasi, dan aktivitas terbaru dalam sistem SiNemu.</p>
        </section>

        <section class="stats-grid super-stats-grid">
            <x-dashboard.stat-card class="super-stat-card super-stat-card-primary" :label="'Total ' . $managerRoleLabel" :value="$summary['total'] ?? 0" icon="mdi:account-group-outline" :description="'Jumlah seluruh akun ' . $managerRoleLabelLower . ' yang terdaftar.'" />
            <x-dashboard.stat-card class="super-stat-card super-stat-card-warning" label="Menunggu Verifikasi" :value="$summary['pending'] ?? 0" icon="mdi:clock-alert-outline" description="Akun yang menunggu keputusan super admin." />
            <x-dashboard.stat-card class="super-stat-card super-stat-card-danger" label="Ditolak / Revisi" :value="$summary['rejected'] ?? 0" icon="mdi:close-octagon-outline" description="Akun yang ditolak atau perlu perbaikan data." />
            <x-dashboard.stat-card class="super-stat-card super-stat-card-success" :label="$managerRoleLabel . ' Baru 7 Hari'" :value="$summary['newThisWeek'] ?? 0" icon="mdi:chart-timeline-variant" :description="'Akun ' . $managerRoleLabelLower . ' yang mendaftar dalam 7 hari terakhir.'" />
        </section>

        <section class="super-dashboard-priority">
            <article class="report-card super-priority-card">
                <header>
                    <div class="report-heading">
                        <h2>Butuh Tindakan Sekarang</h2>
                        <p>Prioritaskan akun pengelola yang masih menunggu keputusan verifikasi.</p>
                    </div>
                    <div class="report-actions">
                        <a href="{{ route('super.admin-verifications.index', ['status' => 'pending']) }}#daftar-verifikasi">Lihat Semua Pending</a>
                    </div>
                </header>

                <div class="super-priority-list">
                    @forelse($priorityAdmins as $admin)
                        <article class="super-priority-item">
                            <div class="item-avatar avatar-claim">
                                <span class="item-avatar-fallback">{{ strtoupper(substr((string) $admin->nama, 0, 1)) }}</span>
                            </div>
                            <div class="super-priority-meta">
                                <strong>{{ $admin->nama }}</strong>
                                <small>{{ $admin->email }} | {{ $admin->instansi ?: 'Instansi belum diisi' }}</small>
                                <small>Didaftarkan {{ optional($admin->created_at)->format('d M Y H:i') ?? '-' }} WIB</small>
                            </div>
                            <span class="status-chip {{ \App\Support\AdminVerificationStatusPresenter::badgeClass('pending') }}">Menunggu</span>
                            <div class="super-priority-actions">
                                <form method="POST"
                                    action="{{ route('super.admins.verify', $admin->id) }}"
                                    data-confirm-delete
                                    data-confirm-title="Verifikasi Akun Pengelola?"
                                    data-confirm-message="Akun pengelola ini akan diverifikasi dan dapat mengakses fitur pengelola barang. Nama: {{ $admin->nama }}"
                                    data-confirm-submit-label="Ya, Verifikasi"
                                    data-confirm-submit-variant="primary">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="super-inline-btn is-accept">Setujui</button>
                                </form>
                                <a href="{{ route('super.admin-verifications.index', ['search' => $admin->nama]) }}" class="super-inline-btn">Tinjau Detail</a>
                            </div>
                        </article>
                    @empty
                        <x-dashboard.empty-state
                            class="super-table-empty-state"
                            icon="mdi:check-circle-outline"
                            title="Tidak ada tindakan tertunda"
                            message="Semua akun pengelola sudah ditangani."
                        />
                    @endforelse
                </div>
            </article>
        </section>

        <section class="super-dashboard-focus">
            <article class="report-card super-activity-card super-focus-card">
                <header>
                    <div class="report-heading">
                        <h2>Aktivitas Verifikasi</h2>
                        <p>Riwayat perubahan status terbaru akun pengelola.</p>
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
                        <x-dashboard.empty-state
                            icon="mdi:timeline-clock-outline"
                            title="Belum ada aktivitas verifikasi"
                            message="Perubahan status pengelola akan muncul di sini."
                        />
                    @endforelse
                </div>
            </article>

            <section class="report-card dashboard-report-card super-focus-card">
                <header>
                    <div class="report-heading">
                        <h2>{{ $managerRoleLabel }} Baru Terdaftar</h2>
                        <p>Pantau akun pengelola yang baru bergabung.</p>
                    </div>
                    <div class="report-actions">
                        <a href="{{ route('super.admins.index') }}">Lihat Semua</a>
                    </div>
                </header>

                <div class="super-newest-list">
                    @forelse($newestAdmins as $index => $admin)
                        @php
                            $statusKey = \App\Support\AdminVerificationStatusPresenter::key($admin->status_verifikasi);
                        @endphp
                        <article class="super-newest-item">
                            <div class="item-avatar avatar-claim">
                                <span class="item-avatar-fallback">{{ strtoupper(substr((string) $admin->nama, 0, 1)) }}</span>
                            </div>
                            <div class="super-newest-meta">
                                <strong>{{ $admin->nama }}</strong>
                                <small>{{ $admin->instansi ?: 'Instansi belum diisi' }} | {{ $admin->kecamatan ?: 'Kecamatan belum diisi' }}</small>
                                <small>{{ optional($admin->created_at)->format('d M Y, H:i') ?? '-' }} WIB</small>
                            </div>
                            <span class="status-chip {{ \App\Support\AdminVerificationStatusPresenter::badgeClass($statusKey) }}">
                                {{ \App\Support\AdminVerificationStatusPresenter::label($statusKey) }}
                            </span>
                            <div class="menu-cell card-action-cell" data-label="Aksi">
                                <x-dashboard.action-menu id="super-dashboard-menu-{{ $index }}">
                                    <a href="{{ route('super.admins.show', $admin) }}">Lihat Detail</a>
                                    <a href="{{ route('super.admins.edit', $admin) }}">Edit Akun</a>
                                    <form method="POST"
                                        action="{{ route('super.admins.destroy', $admin) }}"
                                        data-confirm-delete
                                        data-confirm-title="Hapus Akun Pengelola?"
                                        data-confirm-message="Akun pengelola ini akan dihapus dari sistem. Tindakan ini tidak dapat dibatalkan. Nama: {{ $admin->nama }}"
                                        data-confirm-submit-label="Ya, Hapus Akun"
                                        data-confirm-submit-variant="danger">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="menu-submit danger">Hapus Akun</button>
                                    </form>
                                </x-dashboard.action-menu>
                            </div>
                        </article>
                    @empty
                        <x-dashboard.empty-state
                            class="super-table-empty-state"
                            icon="mdi:account-search-outline"
                            :title="'Belum ada ' . $managerRoleLabelLower . ' baru'"
                            :message="'Akun ' . $managerRoleLabelLower . ' yang baru terdaftar akan tampil di sini.'"
                        />
                    @endforelse
                </div>
            </section>
        </section>
    </div>

    <script>
        document.body.classList.remove('dashboard-fixed-mode');
        document.body.classList.add('super-dashboard-page-mode');
    </script>
@endsection
