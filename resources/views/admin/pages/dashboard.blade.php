@extends('admin.layouts.app')

@php
    /** BAGIAN: Meta Halaman */
    $pageTitle = 'Dashboard Admin - SiNemu';
    $activeMenu = 'dashboard';
    $searchAction = route('admin.dashboard');
    $searchPlaceholder = 'Cari laporan atau barang';
@endphp

@section('page-content')
    <div class="dashboard-page-content">
        {{-- BAGIAN: Pembuka --}}
        <section class="intro">
            <h1>Ringkasan Dashboard Admin</h1>
            <p>Selamat Datang, {{ $admin?->nama ?? 'Admin' }}! Kelola barang hilang &amp; temuan dengan efisien.</p>
        </section>

        {{-- BAGIAN: Kartu Statistik --}}
        <section class="stats-grid">
            <article class="stat-card">
                <span>Total Laporan Hilang</span>
                <strong>{{ $totalHilang }}</strong>
            </article>
            <article class="stat-card">
                <span>Total Laporan Temuan</span>
                <strong>{{ $totalTemuan }}</strong>
            </article>
            <article class="stat-card">
                <span>Menunggu Verifikasi</span>
                <strong>{{ $menungguVerifikasi }}</strong>
            </article>
        </section>

        {{-- BAGIAN: Tabel Laporan Terbaru --}}
        <section class="report-card dashboard-report-card">
            <header>
                <h2>Laporan Terbaru</h2>
                <div class="report-actions">
                    <button type="button" class="filter-btn">Filter</button>
                    <a href="#">Lihat Semua</a>
                </div>
            </header>

            <div class="report-table-wrap">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Detail Barang</th>
                            <th>Tanggal Laporan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($latestReports as $index => $report)
                            <tr>
                                <td>
                                    <div class="item-cell">
                                        <div class="item-avatar {{ $report->avatar_class ?? '' }}">
                                            <span class="item-avatar-fallback">{{ $report->avatar ?? '?' }}</span>
                                            @if(!empty($report->foto_barang))
                                                @php
                                                    $fotoPath = trim((string) $report->foto_barang, '/');
                                                    [$folder, $subPath] = array_pad(explode('/', $fotoPath, 2), 2, '');
                                                    $fotoUrl = in_array($folder, ['barang-hilang', 'barang-temuan', 'verifikasi-klaim'], true) && $subPath !== ''
                                                        ? route('media.image', ['folder' => $folder, 'path' => $subPath], false)
                                                        : asset('storage/' . $fotoPath);
                                                @endphp
                                                <img
                                                    src="{{ $fotoUrl }}"
                                                    alt="{{ $report->item_name ?? 'Barang' }}"
                                                    loading="lazy"
                                                    decoding="async"
                                                    width="30"
                                                    height="30"
                                                    onerror="this.remove()"
                                                >
                                            @elseif(($report->type ?? null) === 'temuan')
                                                <img
                                                    src="{{ route('media.image', ['folder' => 'barang-temuan', 'path' => 'hp.webp'], false) }}"
                                                    alt="{{ $report->item_name ?? 'Barang' }}"
                                                    loading="lazy"
                                                    decoding="async"
                                                    width="30"
                                                    height="30"
                                                    onerror="this.remove()"
                                                >
                                            @elseif(
                                                ($report->type ?? null) === 'hilang'
                                                && \Illuminate\Support\Str::contains(
                                                    \Illuminate\Support\Str::lower((string) ($report->item_name ?? '')),
                                                    'dompet'
                                                )
                                            )
                                                <img
                                                    src="{{ route('media.image', ['folder' => 'barang-hilang', 'path' => 'dompet.webp'], false) }}"
                                                    alt="{{ $report->item_name ?? 'Barang Hilang' }}"
                                                    loading="lazy"
                                                    decoding="async"
                                                    width="30"
                                                    height="30"
                                                    onerror="this.remove()"
                                                >
                                            @endif
                                        </div>
                                        <div>
                                            <strong>{{ $report->item_name ?? '-' }}</strong>
                                            <small>{{ $report->item_detail ?? '-' }}</small>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <div class="date-cell">
                                        <strong>
                                            {{ !empty($report->incident_date) ? \Carbon\Carbon::parse($report->incident_date)->format('d M Y') : '-' }}
                                        </strong>
                                        <small>
                                            {{ !empty($report->created_at) ? \Carbon\Carbon::parse($report->created_at)->format('H:i') : '-' }} WIB
                                        </small>
                                    </div>
                                </td>

                                <td>
                                    <span class="status-chip status-{{ $report->status ?? 'default' }}">
                                        {{ strtoupper(str_replace('_', ' ', $report->status ?? 'TIDAK DIKETAHUI')) }}
                                    </span>
                                </td>

                                <td class="menu-cell">
                                    <button
                                        type="button"
                                        class="row-menu-trigger"
                                        data-menu-target="menu-{{ $index }}"
                                        aria-label="Aksi"
                                    >
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M12 5.5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3zm0 5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3zm0 5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3z" fill="currentColor"/>
                                        </svg>
                                </button>

                                <div class="row-menu" id="menu-{{ $index }}">
                                    @if(($report->type ?? null) === 'klaim')
                                        <a href="{{ $report->target_url }}">Lihat Verifikasi Klaim</a>
                                        <a href="{{ $report->target_url }}">Proses Klaim</a>
                                    @else
                                        <a href="{{ $report->target_url }}">Lihat Detail</a>
                                        <a href="{{ $report->target_url }}">Edit Laporan</a>
                                        <form method="POST" action="{{ $report->delete_url }}" data-confirm-delete data-confirm-message="Yakin ingin menghapus data ini?">
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
                                <td colspan="4" class="empty-row">Belum ada data laporan terbaru.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <footer class="pagination">
                @if($latestReports->onFirstPage())
                    <button type="button" disabled>Sebelumnya</button>
                @else
                    <button type="button" onclick="window.location.href='{{ $latestReports->previousPageUrl() }}'">Sebelumnya</button>
                @endif

                @for($page = 1; $page <= $latestReports->lastPage(); $page++)
                    <button type="button" class="{{ $latestReports->currentPage() === $page ? 'active' : '' }}" onclick="window.location.href='{{ $latestReports->url($page) }}'">{{ $page }}</button>
                @endfor

                @if($latestReports->hasMorePages())
                    <button type="button" onclick="window.location.href='{{ $latestReports->nextPageUrl() }}'">Selanjutnya</button>
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
