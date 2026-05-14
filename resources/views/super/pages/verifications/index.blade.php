@extends('super.layouts.app')

@php
    $managerRoleLabel = \App\Support\RoleLabels::manager();
    $managerRoleLabelLower = \App\Support\RoleLabels::managerLower();
    $pageTitle = 'Verifikasi ' . $managerRoleLabel . ' - Super Admin';
    $activeMenu = 'admin-verifications';
    $searchAction = route('super.admin-verifications.index');
    $searchPlaceholder = 'Cari nama, email, instansi, atau kecamatan';
    $normalizedStatusFilter = $statusFilter ?? 'pending';
    $activeFilterLabel = match ($normalizedStatusFilter) {
        'active' => 'Aktif',
        'rejected' => 'Ditolak',
        'inactive' => 'Nonaktif',
        'semua' => 'Semua',
        default => 'Menunggu',
    };
    $isFiltered = $normalizedStatusFilter !== 'semua' || !empty($search ?? '');
@endphp

@section('page-content')
    <div class="dashboard-page-content super-page-content super-verification-page">
        <section class="intro">
            <h1>Verifikasi {{ $managerRoleLabel }}</h1>
            <p>Tinjau pendaftaran akun {{ $managerRoleLabelLower }}, lalu setujui, tolak, atau nonaktifkan akun sesuai hasil pemeriksaan.</p>
        </section>

        <section class="super-dashboard-grid super-dashboard-grid-single">
            <article class="report-card">
                <header>
                    <div class="report-heading">
                        <h2>Ringkasan Status</h2>
                        <p>Lihat jumlah akun pengelola berdasarkan status verifikasi untuk menentukan prioritas pemeriksaan.</p>
                    </div>
                </header>
                <div class="super-status-summary">
                    @foreach([
                        ['label' => 'Menunggu Verifikasi', 'value' => $summary['pending'] ?? 0, 'status' => 'pending', 'icon' => 'mdi:clock-alert-outline', 'description' => 'Akun pengelola yang masih menunggu keputusan super admin.'],
                        ['label' => 'Aktif', 'value' => $summary['active'] ?? 0, 'status' => 'active', 'icon' => 'mdi:check-decagram-outline', 'description' => 'Akun pengelola yang sudah diverifikasi dan dapat mengakses dashboard pengelola.'],
                        ['label' => 'Ditolak / Revisi', 'value' => $summary['rejected'] ?? 0, 'status' => 'rejected', 'icon' => 'mdi:close-octagon-outline', 'description' => 'Akun pengelola yang ditolak atau perlu memperbaiki data pendaftaran.'],
                        ['label' => 'Nonaktif', 'value' => $summary['inactive'] ?? 0, 'status' => 'inactive', 'icon' => 'mdi:account-off-outline', 'description' => 'Akun pengelola yang sementara tidak dapat mengakses dashboard pengelola.'],
                    ] as $card)
                        <x-dashboard.stat-card
                            class="super-status-card-link {{ \App\Support\AdminVerificationStatusPresenter::cardClass($card['status']) }}"
                            :href="route('super.admin-verifications.index', array_filter(['search' => $search, 'status' => $card['status']])) . '#daftar-verifikasi'"
                            :active="$normalizedStatusFilter === $card['status']"
                            :label="$card['label']"
                            :value="$card['value']"
                            :icon="$card['icon']"
                            :description="$card['description']"
                        />
                    @endforeach
                </div>
            </article>
        </section>

        <section id="daftar-verifikasi" class="report-card dashboard-report-card {{ $admins->total() === 0 ? 'is-empty' : '' }}">
            <header>
                <div class="report-heading">
                    <h2>Daftar Verifikasi</h2>
                    <p>Kelola akun pengelola yang membutuhkan keputusan verifikasi atau perubahan status.</p>
                </div>
            </header>

            <div class="dashboard-table-toolbar">
                <div class="dashboard-quick-filters" aria-label="Filter cepat status verifikasi">
                    <a href="{{ route('super.admin-verifications.index', array_filter(['search' => $search, 'status' => 'semua'])) }}" class="dashboard-filter-chip {{ $statusFilter === 'semua' ? 'is-active' : '' }}">Semua</a>
                    <a href="{{ route('super.admin-verifications.index', array_filter(['search' => $search, 'status' => 'pending'])) }}" class="dashboard-filter-chip {{ $statusFilter === 'pending' ? 'is-active' : '' }}">Menunggu</a>
                    <a href="{{ route('super.admin-verifications.index', array_filter(['search' => $search, 'status' => 'active'])) }}" class="dashboard-filter-chip {{ $statusFilter === 'active' ? 'is-active' : '' }}">Aktif</a>
                    <a href="{{ route('super.admin-verifications.index', array_filter(['search' => $search, 'status' => 'rejected'])) }}" class="dashboard-filter-chip {{ $statusFilter === 'rejected' ? 'is-active' : '' }}">Ditolak</a>
                    <a href="{{ route('super.admin-verifications.index', array_filter(['search' => $search, 'status' => 'inactive'])) }}" class="dashboard-filter-chip {{ $statusFilter === 'inactive' ? 'is-active' : '' }}">Nonaktif</a>
                    @if($isFiltered)
                        <a href="{{ route('super.admin-verifications.index', ['status' => 'semua']) }}" class="dashboard-filter-chip">Reset Filter</a>
                    @endif
                </div>
                <div class="dashboard-toolbar-note">
                    Menampilkan {{ $admins->total() }} {{ $managerRoleLabelLower }}
                </div>
            </div>

            <div class="report-table-wrap super-verification-table-wrap">
                <table class="report-table responsive-card-table super-verifications-table">
                    <thead>
                        <tr>
                            <th>Detail Pengelola</th>
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
                                <td class="card-primary-cell" data-label="Detail Pengelola">
                                    <div class="item-cell">
                                        <div class="item-avatar avatar-claim">
                                            <span class="item-avatar-fallback">{{ strtoupper(substr((string) $admin->nama, 0, 1)) }}</span>
                                        </div>
                                        <div>
                                            <strong>{{ $admin->nama }}</strong>
                                            <small>{{ $admin->email }} | {{ $admin->username }}</small>
                                            <small>{{ $admin->instansi ?: 'Instansi belum diisi' }} | {{ $admin->kecamatan ?: 'Kecamatan belum diisi' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="card-date-cell" data-label="Tanggal Daftar">
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
                                    <x-dashboard.action-menu id="super-verification-menu-{{ $index }}" label="Buka menu aksi verifikasi">
                                        <a href="{{ route('super.admins.show', $admin) }}">Lihat Detail</a>
                                        @if(in_array($statusKey, ['pending', 'rejected', 'inactive'], true))
                                            <form method="POST"
                                                action="{{ route('super.admins.verify', $admin->id) }}"
                                                data-confirm-delete
                                                data-confirm-title="Verifikasi Akun Pengelola?"
                                                data-confirm-message="Akun pengelola ini akan diverifikasi dan dapat mengakses dashboard pengelola barang. Nama: {{ $admin->nama }}"
                                                data-confirm-submit-label="Ya, Verifikasi"
                                                data-confirm-submit-variant="primary">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="menu-submit">Verifikasi</button>
                                            </form>
                                        @endif
                                        @if($statusKey === 'pending')
                                            <form method="POST"
                                                action="{{ route('super.admins.reject', $admin->id) }}"
                                                data-confirm-delete
                                                data-confirm-title="Tolak Verifikasi Akun?"
                                                data-confirm-message="Akun pengelola ini akan ditandai sebagai ditolak atau perlu revisi data. Nama: {{ $admin->nama }}"
                                                data-confirm-submit-label="Ya, Tolak"
                                                data-confirm-submit-variant="danger">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="menu-submit danger">Tolak / Revisi</button>
                                            </form>
                                        @endif
                                        @if($statusKey === 'active')
                                            <form method="POST"
                                                action="{{ route('super.admins.deactivate', $admin->id) }}"
                                                data-confirm-delete
                                                data-confirm-title="Nonaktifkan Akun Pengelola?"
                                                data-confirm-message="Akun pengelola ini sementara tidak dapat mengakses dashboard pengelola barang. Nama: {{ $admin->nama }}"
                                                data-confirm-submit-label="Ya, Nonaktifkan"
                                                data-confirm-submit-variant="danger">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="menu-submit danger">Nonaktifkan</button>
                                            </form>
                                        @endif
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
                        @empty
                            <tr>
                                <td colspan="4" class="empty-row">
                                    <div class="super-manager-empty-state">
                                        <strong>Tidak ada akun yang perlu diverifikasi.</strong>
                                        <span>Saat ini belum ada akun pengelola barang dengan status yang dipilih.</span>
                                        @if($isFiltered)
                                            <a href="{{ route('super.admin-verifications.index', ['status' => 'semua']) }}" class="super-inline-btn is-accept">Tampilkan Semua Data</a>
                                        @endif
                                    </div>
                                </td>
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
