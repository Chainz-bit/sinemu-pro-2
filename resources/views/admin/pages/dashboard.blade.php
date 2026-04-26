@extends('admin.layouts.app')

@php
    /** BAGIAN: Meta Halaman */
    $pageTitle = 'Dashboard Admin - SiNemu';
    $activeMenu = 'dashboard';
    $searchAction = route('admin.dashboard');
    $searchPlaceholder = 'Cari laporan atau barang';
@endphp

@section('page-content')
    <div class="dashboard-page-content dashboard-hub-page">
{{-- BAGIAN: Pembuka --}}
        <section class="intro">
            <h1>Ringkasan Dashboard Admin</h1>
            <p>Selamat Datang, {{ $admin?->nama ?? 'Admin' }}! Kelola barang hilang &amp; temuan dengan efisien.</p>
        </section>

        {{-- BAGIAN: Kartu Statistik --}}
        <section class="stats-grid">
            <article class="stat-card stat-card-lost">
                <div class="stat-card-head">
                    <span>Total Laporan Hilang</span>
                    <div class="stat-card-icon">
                        <iconify-icon icon="mdi:map-marker-alert-outline"></iconify-icon>
                    </div>
                </div>
                <strong>{{ $totalHilang }}</strong>
                <small>Laporan kehilangan yang sudah masuk ke sistem.</small>
            </article>
            <article class="stat-card stat-card-found">
                <div class="stat-card-head">
                    <span>Total Laporan Temuan</span>
                    <div class="stat-card-icon">
                        <iconify-icon icon="mdi:package-variant-closed-check"></iconify-icon>
                    </div>
                </div>
                <strong>{{ $totalTemuan }}</strong>
                <small>Barang temuan yang dikelola oleh admin.</small>
            </article>
            <article class="stat-card stat-card-claim">
                <div class="stat-card-head">
                    <span>Menunggu Verifikasi</span>
                    <div class="stat-card-icon">
                        <iconify-icon icon="mdi:clock-alert-outline"></iconify-icon>
                    </div>
                </div>
                <strong>{{ $menungguVerifikasi }}</strong>
                <small>Klaim aktif yang perlu diproses segera.</small>
            </article>
        </section>

        {{-- BAGIAN: Tabel Laporan Terbaru --}}
        <section class="report-card dashboard-report-card">
            <header>
                <div class="report-heading">
                    <h2>Laporan Terbaru</h2>
                    <p>Pantau update terbaru dan fokus ke laporan yang membutuhkan tindakan.</p>
                </div>
                <div class="report-actions">
                    <form method="GET" action="{{ route('admin.dashboard') }}" class="dashboard-filter-form">
                        @if($search !== '')
                            <input type="hidden" name="search" value="{{ $search }}">
                        @endif
                        <select name="status" class="filter-btn dashboard-filter-select" onchange="this.form.submit()">
                            <option value="semua" @selected($statusFilter === 'semua')>Semua Status</option>
                            <option value="diproses" @selected($statusFilter === 'diproses')>Diproses</option>
                            <option value="dalam_peninjauan" @selected($statusFilter === 'dalam_peninjauan')>Dalam Peninjauan</option>
                            <option value="selesai" @selected($statusFilter === 'selesai')>Selesai</option>
                            <option value="ditolak" @selected($statusFilter === 'ditolak')>Ditolak</option>
                        </select>
                    </form>
                </div>
            </header>

            <div class="dashboard-table-toolbar">
                <div class="dashboard-quick-filters">
                    <a href="{{ route('admin.dashboard', array_filter(['search' => $search, 'status' => 'semua'])) }}" class="dashboard-filter-chip {{ $statusFilter === 'semua' ? 'is-active' : '' }}">Semua</a>
                    <a href="{{ route('admin.dashboard', array_filter(['search' => $search, 'status' => 'dalam_peninjauan'])) }}" class="dashboard-filter-chip {{ $statusFilter === 'dalam_peninjauan' ? 'is-active' : '' }}">Menunggu</a>
                    <a href="{{ route('admin.dashboard', array_filter(['search' => $search, 'status' => 'diproses'])) }}" class="dashboard-filter-chip {{ $statusFilter === 'diproses' ? 'is-active' : '' }}">Diproses</a>
                    <a href="{{ route('admin.dashboard', array_filter(['search' => $search, 'status' => 'selesai'])) }}" class="dashboard-filter-chip {{ $statusFilter === 'selesai' ? 'is-active' : '' }}">Selesai</a>
                    <a href="{{ route('admin.dashboard', array_filter(['search' => $search, 'status' => 'ditolak'])) }}" class="dashboard-filter-chip {{ $statusFilter === 'ditolak' ? 'is-active' : '' }}">Ditolak</a>
                </div>
                <div class="dashboard-toolbar-note">
                    @if($search !== '')
                        Hasil pencarian untuk "<strong>{{ $search }}</strong>"
                    @else
                        Menampilkan {{ $latestReports->total() }} laporan terbaru
                    @endif
                </div>
            </div>

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
                                            @php
                                                $fotoUrlDefault = asset('img/login-image.png');
                                                $fotoSrc = null;
                                                $rawFotoPath = str_replace('\\', '/', trim((string) ($report->foto_barang ?? '')));
                                                $localFotoPath = null;

                                                if ($rawFotoPath !== '') {
                                                    if (\Illuminate\Support\Str::startsWith($rawFotoPath, ['http://', 'https://'])) {
                                                        $fotoSrc = $rawFotoPath;
                                                    } else {
                                                        $fotoPath = ltrim($rawFotoPath, '/');
                                                        if (\Illuminate\Support\Str::startsWith($fotoPath, 'storage/')) {
                                                            $fotoPath = substr($fotoPath, 8);
                                                        } elseif (\Illuminate\Support\Str::startsWith($fotoPath, 'public/')) {
                                                            $fotoPath = substr($fotoPath, 7);
                                                        }
                                                        $localFotoPath = $fotoPath;
                                                    }
                                                }

                                                if (!empty($localFotoPath) && \Illuminate\Support\Facades\Storage::disk('public')->exists($localFotoPath)) {
                                                    $absolutePath = \Illuminate\Support\Facades\Storage::disk('public')->path($localFotoPath);
                                                    $mimeType = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($localFotoPath) ?: 'image/jpeg';
                                                    $binary = @file_get_contents($absolutePath);
                                                    if ($binary !== false) {
                                                        $fotoSrc = 'data:' . $mimeType . ';base64,' . base64_encode($binary);
                                                    }
                                                }

                                                if (!$fotoSrc) {
                                                    $fotoSrc = $fotoUrlDefault;
                                                }
                                            @endphp
                                            @if($fotoSrc)
                                                <img
                                                    src="{{ $fotoSrc }}"
                                                    alt="{{ $report->item_name ?? 'Barang' }}"
                                                    loading="lazy"
                                                    decoding="async"
                                                    width="30"
                                                    height="30"
                                                    onerror="this.onerror=null;this.src='{{ $fotoUrlDefault }}';"
                                                >
                                            @elseif(($report->type ?? null) === 'temuan')
                                                <img
                                                    src="{{ route('media.image', ['folder' => 'barang-temuan', 'path' => 'hp.webp']) }}"
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
                                                    src="{{ route('media.image', ['folder' => 'barang-hilang', 'path' => 'dompet.webp']) }}"
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
                                    <span class="status-chip {{ $report->status_class ?? ('status-' . ($report->status ?? 'default')) }}">
                                        {{ $report->status_text ?? strtoupper(str_replace('_', ' ', $report->status ?? 'TIDAK DIKETAHUI')) }}
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
                                        <a href="{{ $report->detail_url ?? $report->target_url }}">Lihat Detail</a>
                                        <a href="{{ $report->edit_url ?? ($report->detail_url ?? $report->target_url) }}">Edit Data</a>
                                        @if(!empty($report->upload_home_url) && !($report->home_published ?? false))
                                        <form method="POST" action="{{ $report->upload_home_url }}">
                                            @csrf
                                            <button type="submit" class="menu-submit">Upload</button>
                                        </form>
                                        @elseif($report->home_published ?? false)
                                            <span class="row-menu-note">Sudah di-upload</span>
                                        @endif
                                        @if(!empty($report->delete_url))
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
