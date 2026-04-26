@extends('user.layouts.app')

@php
    /** BAGIAN: Meta halaman dashboard user */
    $pageTitle = 'Dashboard User - SiNemu';
    $activeMenu = 'dashboard';
    $searchAction = route('user.dashboard');
    $searchPlaceholder = 'Cari aktivitas Anda';
@endphp

@section('page-content')
    <div class="dashboard-page-content dashboard-hub-page">
{{-- BAGIAN: Header ringkasan --}}
        <section class="intro">
            <h1>Ringkasan Dashboard User</h1>
            <p>Selamat datang, {{ $user?->nama ?? $user?->name ?? 'Pengguna' }}. Pantau laporan dan klaim Anda dari satu tempat.</p>
        </section>

        {{-- BAGIAN: Kartu statistik --}}
        <section class="stats-grid">
            <article class="stat-card stat-card-lost">
                <div class="stat-card-head">
                    <span>Total Lapor Barang Hilang</span>
                    <div class="stat-card-icon">
                        <iconify-icon icon="mdi:map-marker-alert-outline"></iconify-icon>
                    </div>
                </div>
                <strong>{{ $totalLaporHilang }}</strong>
                <small>Total laporan hilang yang Anda kirim.</small>
            </article>
            <article class="stat-card stat-card-found">
                <div class="stat-card-head">
                    <span>Total Pengajuan Klaim</span>
                    <div class="stat-card-icon">
                        <iconify-icon icon="mdi:clipboard-check-outline"></iconify-icon>
                    </div>
                </div>
                <strong>{{ $totalPengajuanKlaim }}</strong>
                <small>Seluruh klaim barang yang Anda ajukan.</small>
            </article>
            <article class="stat-card stat-card-claim">
                <div class="stat-card-head">
                    <span>Menunggu Tinjauan</span>
                    <div class="stat-card-icon">
                        <iconify-icon icon="mdi:clock-alert-outline"></iconify-icon>
                    </div>
                </div>
                <strong>{{ $menungguVerifikasi }}</strong>
                <small>Laporan/klaim aktif yang masih ditinjau.</small>
            </article>
        </section>

        {{-- BAGIAN: Tabel aktivitas terbaru --}}
        <section class="report-card report-card-scrollable dashboard-report-card">
            <header>
                <div class="report-heading">
                    <h2>Aktivitas Terbaru</h2>
                    <p>Laporan dan klaim terbaru yang Anda kirimkan.</p>
                </div>
                <div class="report-actions">
                    <form method="GET" action="{{ route('user.dashboard') }}" class="dashboard-filter-form">
                        @if($search !== '')
                            <input type="hidden" name="search" value="{{ $search }}">
                        @endif
                        <select name="status" class="filter-btn dashboard-filter-select" onchange="this.form.submit()">
                            <option value="semua" @selected($statusFilter === 'semua')>Semua Status</option>
                            <option value="menunggu_tinjauan" @selected($statusFilter === 'menunggu_tinjauan')>Menunggu Tinjauan</option>
                            <option value="terverifikasi" @selected($statusFilter === 'terverifikasi')>Terverifikasi</option>
                            <option value="sedang_diproses" @selected($statusFilter === 'sedang_diproses')>Sedang Diproses</option>
                            <option value="selesai" @selected($statusFilter === 'selesai')>Selesai</option>
                            <option value="tidak_disetujui" @selected($statusFilter === 'tidak_disetujui')>Tidak Disetujui</option>
                        </select>
                    </form>
                </div>
            </header>

            <div class="dashboard-table-toolbar">
                <div class="dashboard-quick-filters">
                    <a href="{{ route('user.dashboard', array_filter(['search' => $search, 'status' => 'semua'])) }}" class="dashboard-filter-chip {{ $statusFilter === 'semua' ? 'is-active' : '' }}">Semua</a>
                    <a href="{{ route('user.dashboard', array_filter(['search' => $search, 'status' => 'menunggu_tinjauan'])) }}" class="dashboard-filter-chip {{ $statusFilter === 'menunggu_tinjauan' ? 'is-active' : '' }}">Menunggu</a>
                    <a href="{{ route('user.dashboard', array_filter(['search' => $search, 'status' => 'terverifikasi'])) }}" class="dashboard-filter-chip {{ $statusFilter === 'terverifikasi' ? 'is-active' : '' }}">Terverifikasi</a>
                    <a href="{{ route('user.dashboard', array_filter(['search' => $search, 'status' => 'sedang_diproses'])) }}" class="dashboard-filter-chip {{ $statusFilter === 'sedang_diproses' ? 'is-active' : '' }}">Diproses</a>
                    <a href="{{ route('user.dashboard', array_filter(['search' => $search, 'status' => 'selesai'])) }}" class="dashboard-filter-chip {{ $statusFilter === 'selesai' ? 'is-active' : '' }}">Selesai</a>
                    <a href="{{ route('user.dashboard', array_filter(['search' => $search, 'status' => 'tidak_disetujui'])) }}" class="dashboard-filter-chip {{ $statusFilter === 'tidak_disetujui' ? 'is-active' : '' }}">Tidak Disetujui</a>
                </div>
                <div class="dashboard-toolbar-note">
                    @if($search !== '')
                        Hasil pencarian untuk "<strong>{{ $search }}</strong>"
                    @else
                        Menampilkan {{ $latestActivities->total() }} aktivitas
                    @endif
                </div>
            </div>

            <div class="report-table-wrap">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Detail Aktivitas</th>
                            <th>Tanggal Aktivitas</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($latestActivities as $activity)
                            <tr>
                                <td>
                                    <div class="item-cell">
                                        <div class="item-avatar {{ $activity->avatar_class ?? '' }}">
                                            @if(!empty($activity->image_url))
                                                <img src="{{ $activity->image_url }}" alt="{{ $activity->item_name ?? 'Gambar barang' }}" loading="lazy" decoding="async" onerror="this.remove()">
                                            @endif
                                            <span class="item-avatar-fallback">{{ $activity->avatar ?? '?' }}</span>
                                        </div>
                                        <div>
                                            <strong>{{ $activity->item_name ?? '-' }}</strong>
                                            <small>{{ $activity->item_detail ?? '-' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="date-cell">
                                        <strong>
                                            {{ !empty($activity->incident_date) ? \Carbon\Carbon::parse($activity->incident_date)->format('d M Y') : '-' }}
                                        </strong>
                                        <small>
                                            {{ !empty($activity->created_at) ? \Carbon\Carbon::parse($activity->created_at)->format('H:i') : '-' }} WIB
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-chip {{ $activity->status_class ?? 'status-dalam_peninjauan' }}">
                                        {{ $activity->status_text ?? '-' }}
                                    </span>
                                </td>
                                <td class="menu-cell">
                                    <button type="button" class="row-menu-trigger" data-menu-target="user-menu-{{ $loop->index }}" aria-label="Aksi">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <circle cx="12" cy="5" r="2" fill="currentColor"></circle>
                                            <circle cx="12" cy="12" r="2" fill="currentColor"></circle>
                                            <circle cx="12" cy="19" r="2" fill="currentColor"></circle>
                                        </svg>
                                    </button>
                                    <div class="row-menu" id="user-menu-{{ $loop->index }}">
                                        <a href="{{ $activity->detail_url ?? '#' }}">{{ $activity->action_label ?? 'Lihat Detail' }}</a>
                                        @if(($activity->can_delete ?? false) && !empty($activity->delete_url))
                                            <form method="POST" action="{{ $activity->delete_url }}" data-confirm-delete data-confirm-message="Laporan yang dihapus tidak bisa dikembalikan. Lanjutkan?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="menu-submit danger">Hapus</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty-row">Belum ada aktivitas untuk ditampilkan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <footer class="pagination">
                @if($latestActivities->onFirstPage())
                    <button type="button" disabled>Sebelumnya</button>
                @else
                    <button type="button" onclick="window.location.href='{{ $latestActivities->previousPageUrl() }}'">Sebelumnya</button>
                @endif

                @for($page = 1; $page <= $latestActivities->lastPage(); $page++)
                    <button type="button" class="{{ $latestActivities->currentPage() === $page ? 'active' : '' }}" onclick="window.location.href='{{ $latestActivities->url($page) }}'">{{ $page }}</button>
                @endfor

                @if($latestActivities->hasMorePages())
                    <button type="button" onclick="window.location.href='{{ $latestActivities->nextPageUrl() }}'">Selanjutnya</button>
                @else
                    <button type="button" disabled>Selanjutnya</button>
                @endif
            </footer>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.body.classList.add('dashboard-fixed-mode');
        });
    </script>
@endsection
