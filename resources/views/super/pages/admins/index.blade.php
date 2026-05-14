@extends('super.layouts.app')

@php
    $managerRoleLabel = \App\Support\RoleLabels::manager();
    $managerRoleLabelLower = \App\Support\RoleLabels::managerLower();
    $pageTitle = 'Daftar ' . $managerRoleLabel . ' - Super Admin';
    $activeMenu = 'admins';
    $searchAction = route('super.admins.index');
    $searchPlaceholder = 'Cari nama ' . $managerRoleLabelLower . ', instansi, atau kecamatan';
@endphp

@section('page-content')
    <div class="dashboard-page-content super-page-content super-managers-page">
        <section class="intro">
            <h1>Daftar {{ $managerRoleLabel }} Terdaftar</h1>
            <p>Pantau semua {{ $managerRoleLabelLower }} yang sudah masuk ke sistem, termasuk status aktif, antrean verifikasi, dan akun yang perlu revisi.</p>
        </section>

        <section class="stats-grid super-stats-grid compact">
            <x-dashboard.stat-card class="stat-card-found" :label="'Total ' . $managerRoleLabel" :value="$summary['total'] ?? 0" icon="mdi:account-group-outline" :description="'Total akun ' . $managerRoleLabelLower . ' yang terdaftar.'" />
            <x-dashboard.stat-card class="stat-card-claim" label="Menunggu" :value="$summary['pending'] ?? 0" icon="mdi:clock-alert-outline" description="Pendaftar yang butuh keputusan." />
            <x-dashboard.stat-card class="stat-card-found" label="Aktif" :value="$summary['active'] ?? 0" icon="mdi:check-decagram-outline" :description="$managerRoleLabel . ' yang sudah lolos verifikasi.'" />
            <x-dashboard.stat-card class="stat-card-lost" label="Ditolak" :value="$summary['rejected'] ?? 0" icon="mdi:close-octagon-outline" description="Akun yang ditolak atau perlu revisi." />
        </section>

        <section class="report-card dashboard-report-card">
            <header>
                <div class="report-heading">
                    <h2>Direktori {{ $managerRoleLabel }}</h2>
                    <p>Telusuri daftar {{ $managerRoleLabelLower }} berdasarkan nama, instansi, kecamatan, atau status verifikasi.</p>
                </div>
                <div class="report-actions">
                    <form method="GET" action="{{ route('super.admins.index') }}" class="dashboard-filter-form">
                        @if($search !== '')
                            <input type="hidden" name="search" value="{{ $search }}">
                        @endif
                        <select name="status" class="filter-btn dashboard-filter-select" onchange="this.form.submit()">
                            <option value="semua" @selected($statusFilter === 'semua')>Semua Status</option>
                            <option value="pending" @selected($statusFilter === 'pending')>Menunggu</option>
                            <option value="active" @selected($statusFilter === 'active')>Aktif</option>
                            <option value="rejected" @selected($statusFilter === 'rejected')>Ditolak</option>
                            <option value="inactive" @selected($statusFilter === 'inactive')>Nonaktif</option>
                        </select>
                    </form>
                </div>
            </header>

            <div class="dashboard-table-toolbar">
                <div class="dashboard-quick-filters">
                    <a href="{{ route('super.admins.index', array_filter(['search' => $search, 'status' => 'semua'])) }}" class="dashboard-filter-chip {{ $statusFilter === 'semua' ? 'is-active' : '' }}">Semua</a>
                    <a href="{{ route('super.admins.index', array_filter(['search' => $search, 'status' => 'pending'])) }}" class="dashboard-filter-chip {{ $statusFilter === 'pending' ? 'is-active' : '' }}">Menunggu</a>
                    <a href="{{ route('super.admins.index', array_filter(['search' => $search, 'status' => 'active'])) }}" class="dashboard-filter-chip {{ $statusFilter === 'active' ? 'is-active' : '' }}">Aktif</a>
                    <a href="{{ route('super.admins.index', array_filter(['search' => $search, 'status' => 'rejected'])) }}" class="dashboard-filter-chip {{ $statusFilter === 'rejected' ? 'is-active' : '' }}">Ditolak</a>
                    <a href="{{ route('super.admins.index', array_filter(['search' => $search, 'status' => 'inactive'])) }}" class="dashboard-filter-chip {{ $statusFilter === 'inactive' ? 'is-active' : '' }}">Nonaktif</a>
                </div>
                <div class="dashboard-toolbar-note">
                    Menampilkan {{ $admins->total() }} {{ $managerRoleLabelLower }}
                </div>
            </div>

            <div class="report-table-wrap">
                <table class="report-table responsive-card-table super-managers-table">
                    <thead>
                        <tr>
                            <th>Detail {{ $managerRoleLabel }}</th>
                            <th>Tanggal Daftar</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($admins as $index => $admin)
                            @php
                                $statusKey = \App\Support\AdminVerificationStatusPresenter::key($admin->status_verifikasi);
                            @endphp
                            <tr>
                                <td class="card-primary-cell" data-label="Detail Aktivitas">
                                    <div class="item-cell">
                                        <div class="item-avatar avatar-claim">
                                            <span class="item-avatar-fallback">{{ strtoupper(substr((string) $admin->nama, 0, 1)) }}</span>
                                        </div>
                                        <div>
                                            <strong>{{ $admin->nama }}</strong>
                                            <small>{{ $admin->email }} | {{ $admin->instansi ?: 'Instansi belum diisi' }}</small>
                                            <small>{{ $admin->kecamatan ?: 'Kecamatan belum diisi' }} | {{ $admin->username }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="card-date-cell" data-label="Tanggal">
                                    <div class="date-cell">
                                        <strong>{{ optional($admin->created_at)->format('d M Y') ?? '-' }}</strong>
                                        <small>{{ optional($admin->created_at)->format('H:i') ?? '-' }} WIB</small>
                                    </div>
                                </td>
                                <td class="card-status-cell" data-label="Status">
                                    <span class="status-chip {{ \App\Support\AdminVerificationStatusPresenter::badgeClass($statusKey) }}">
                                        {{ \App\Support\AdminVerificationStatusPresenter::label($statusKey) }}
                                    </span>
                                </td>
                                <td class="menu-cell card-action-cell" data-label="Aksi">
                                    <x-dashboard.action-menu id="super-manager-menu-{{ $index }}" label="Buka menu aksi pengelola">
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
                                </td>
                            </tr>
                            @if($search !== '' && str_contains(strtolower($admin->nama . ' ' . $admin->email . ' ' . $admin->instansi . ' ' . $admin->kecamatan), strtolower($search)))
                                <tr class="super-manager-detail-row">
                                    <td colspan="4">
                                        <div class="super-manager-detail-card">
                                            <div><span>Alamat</span><strong>{{ $admin->alamat_lengkap ?: '-' }}</strong></div>
                                            <div><span>Telepon</span><strong>{{ $admin->nomor_telepon ?: '-' }}</strong></div>
                                            <div><span>Catatan Penolakan</span><strong>{{ $admin->alasan_penolakan ?: '-' }}</strong></div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="4" class="empty-row">Belum ada {{ $managerRoleLabelLower }} yang cocok dengan filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <footer class="pagination">
                @if($admins->onFirstPage())
                    <button type="button" disabled>Sebelumnya</button>
                @else
                    <button type="button" onclick="window.location.href='{{ $admins->previousPageUrl() }}'">Sebelumnya</button>
                @endif

                @for($page = 1; $page <= $admins->lastPage(); $page++)
                    <button type="button" class="{{ $admins->currentPage() === $page ? 'active' : '' }}" onclick="window.location.href='{{ $admins->url($page) }}'">{{ $page }}</button>
                @endfor

                @if($admins->hasMorePages())
                    <button type="button" onclick="window.location.href='{{ $admins->nextPageUrl() }}'">Selanjutnya</button>
                @else
                    <button type="button" disabled>Selanjutnya</button>
                @endif
            </footer>
        </section>
    </div>
@endsection
