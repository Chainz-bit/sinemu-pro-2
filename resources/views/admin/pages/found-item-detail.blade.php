@extends('admin.layouts.app')

@php
    /** BAGIAN: Meta Halaman */
    $pageTitle = 'Detail Barang Temuan - SiNemu';
    $activeMenu = 'found-items';
    $searchAction = route('admin.found-items');
    $searchPlaceholder = 'Cari laporan atau barang';

    $fotoPath = trim((string) ($barang->foto_barang ?? ''), '/');
    [$folder, $subPath] = array_pad(explode('/', $fotoPath, 2), 2, '');
    $fotoUrl = !empty($fotoPath) && in_array($folder, ['barang-hilang', 'barang-temuan', 'verifikasi-klaim'], true) && $subPath !== ''
        ? route('media.image', ['folder' => $folder, 'path' => $subPath], false)
        : route('media.image', ['folder' => 'barang-temuan', 'path' => 'hp.webp'], false);

    $statusMap = [
        'tersedia' => ['TERSEDIA', 'status-dalam_peninjauan'],
        'dalam_proses_klaim' => ['DALAM PROSES KLAIM', 'status-diproses'],
        'sudah_diklaim' => ['SUDAH DIKLAIM', 'status-selesai'],
        'sudah_dikembalikan' => ['SELESAI', 'status-selesai'],
    ];
    $statusOptionLabels = [
        'tersedia' => 'Tersedia',
        'dalam_proses_klaim' => 'Dalam Proses Klaim',
        'sudah_diklaim' => 'Sudah Diklaim',
        'sudah_dikembalikan' => 'Sudah Dikembalikan',
    ];
    [$statusLabel, $statusClass] = $statusMap[$barang->status_barang] ?? ['UNKNOWN', 'status-diproses'];
    $petugasName = $barang->admin?->nama ?? 'Admin';
    $initials = collect(explode(' ', trim($petugasName)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
        ->implode('');
    $statusHistories = $barang->statusHistories->take(8);
@endphp

@section('page-content')
    <section class="found-detail-page">
        @if(session('status'))
            <div class="info-modal-backdrop" id="status-info-modal" role="dialog" aria-modal="true" aria-labelledby="status-info-title">
                <div class="info-modal">
                    <h3 id="status-info-title">Informasi</h3>
                    <p>{{ session('status') }}</p>
                    <div class="info-modal-actions">
                        <button type="button" class="btn-primary" id="status-info-close">Tutup</button>
                    </div>
                </div>
            </div>
        @endif

        <div class="found-detail-header">
            <div>
                <p class="found-detail-breadcrumb">
                    <a href="{{ route('admin.found-items') }}">Daftar Barang Temuan</a>
                    <span>/</span>
                    <strong>Detail Barang</strong>
                </p>
                <h1>Detail Laporan Barang Temuan</h1>
            </div>
            <div class="found-detail-actions">
                <a href="{{ route('admin.found-items.export', $barang->id) }}" class="filter-btn found-action-btn found-action-btn-ghost">Export Laporan</a>
                <button type="submit" form="status-update-form" class="filter-btn found-action-btn found-action-btn-primary">Simpan</button>
            </div>
        </div>

        <div class="found-detail-grid">
            <article class="report-card found-detail-main">
                <div class="found-detail-main-content">
                    <div class="found-detail-image-wrap">
                        <img
                            src="{{ $fotoUrl }}"
                            alt="{{ $barang->nama_barang }}"
                            class="found-detail-image"
                            loading="lazy"
                            decoding="async"
                        >
                    </div>

                    <div class="found-detail-body">
                        <h2>{{ strtoupper($barang->nama_barang) }}</h2>
                        <p>{{ $barang->deskripsi ?: 'Deskripsi barang belum ditambahkan pada laporan ini.' }}</p>

                        <div class="found-detail-meta">
                            <div>
                                <span>Kategori</span>
                                <strong>{{ $barang->kategori?->nama_kategori ?? 'Tanpa Kategori' }}</strong>
                            </div>
                            <div>
                                <span>Tanggal Ditemukan</span>
                                <strong>{{ !empty($barang->tanggal_ditemukan) ? \Illuminate\Support\Carbon::parse($barang->tanggal_ditemukan)->format('d M Y') : '-' }}</strong>
                            </div>
                            <div>
                                <span>Lokasi Ditemukan</span>
                                <strong>{{ $barang->lokasi_ditemukan ?: '-' }}</strong>
                            </div>
                            <div>
                                <span>Status</span>
                                <strong><span class="status-chip {{ $statusClass }}">{{ $statusLabel }}</span></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </article>

            <div class="found-detail-side">
                <article class="report-card found-detail-panel">
                    <header>
                        <h2>Status Saat Ini</h2>
                    </header>
                    <div class="found-detail-panel-body">
                        <span class="status-chip {{ $statusClass }}">{{ $statusLabel }}</span>

                        <form method="POST" action="{{ route('admin.found-items.update-status', $barang->id) }}" class="status-edit-form" id="status-update-form">
                            @csrf
                            @method('PATCH')
                            <label for="status_barang" class="status-form-label">Status Baru</label>
                            <select name="status_barang" id="status_barang" class="form-input status-form-input">
                                @foreach($statusOptionLabels as $statusValue => $statusText)
                                    <option value="{{ $statusValue }}" @selected(old('status_barang', $barang->status_barang) === $statusValue)>{{ $statusText }}</option>
                                @endforeach
                            </select>

                            <label for="catatan_status" class="status-form-label">Catatan (Opsional)</label>
                            <textarea name="catatan_status" id="catatan_status" class="form-input form-textarea-sm status-form-input" placeholder="Contoh: Barang sudah diserahkan ke pemilik.">{{ old('catatan_status') }}</textarea>
                        </form>
                    </div>
                </article>

                <article class="report-card found-detail-panel">
                    <header><h2>Informasi Penemu</h2></header>
                    <div class="found-detail-panel-body">
                        <div class="found-person-row">
                            <span class="found-person-avatar">{{ $initials ?: 'AD' }}</span>
                            <div>
                                <p><strong>{{ $petugasName }}</strong></p>
                                <small>Petugas Keamanan</small>
                            </div>
                        </div>
                        <div class="found-contact-actions">
                            <a href="#" class="filter-btn">Hubungi</a>
                            <a href="#" class="filter-btn">Email</a>
                        </div>
                    </div>
                </article>

                <article class="report-card found-detail-panel">
                    <header><h2>Lokasi &amp; Waktu Penyimpanan</h2></header>
                    <div class="found-detail-panel-body">
                        <div class="found-info-item">
                            <span class="found-info-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 7a1 1 0 0 1 1 1v4.1l2.2 1.47a1 1 0 1 1-1.1 1.66l-2.65-1.76A1 1 0 0 1 11 13V8a1 1 0 0 1 1-1zM12 3a9 9 0 1 1 0 18a9 9 0 0 1 0-18zm0 2a7 7 0 1 0 0 14a7 7 0 0 0 0-14z" fill="currentColor"/></svg>
                            </span>
                            <div>
                                <small>Ditemukan</small>
                                <p><strong>{{ !empty($barang->tanggal_ditemukan) ? \Illuminate\Support\Carbon::parse($barang->tanggal_ditemukan)->format('d M Y, H:i') : '-' }} WIB</strong></p>
                            </div>
                        </div>
                        <div class="found-info-item">
                            <span class="found-info-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4a1 1 0 0 1 1 1v1h8V5a1 1 0 1 1 2 0v1h1a2 2 0 0 1 2 2v3H3V8a2 2 0 0 1 2-2h1V5a1 1 0 0 1 1-1zm14 9v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-6h18zm-8 2H7a1 1 0 1 0 0 2h6a1 1 0 1 0 0-2z" fill="currentColor"/></svg>
                            </span>
                            <div>
                                <small>Disimpan di</small>
                                <p><strong>{{ $barang->lokasi_pengambilan ?: $barang->lokasi_ditemukan ?: '-' }}</strong></p>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="report-card found-detail-panel">
                    <header><h2>Riwayat Aktivitas</h2></header>
                    <div class="found-detail-panel-body">
                        @forelse($statusHistories as $history)
                            <div class="activity-item">
                                <p><strong>Status Diperbarui</strong></p>
                                <small>
                                    {{ ($statusOptionLabels[$history->status_lama] ?? strtoupper(str_replace('_', ' ', (string) $history->status_lama))) ?: '-' }}
                                    ke
                                    {{ $statusOptionLabels[$history->status_baru] ?? strtoupper(str_replace('_', ' ', (string) $history->status_baru)) }}
                                    - {{ $history->admin?->nama ?? 'Admin' }} - {{ \Illuminate\Support\Carbon::parse($history->created_at)->format('d M Y, H:i') }} WIB
                                </small>
                                @if(!empty($history->catatan))
                                    <small>Catatan: {{ $history->catatan }}</small>
                                @endif
                            </div>
                        @empty
                            <div class="activity-item">
                                <p><strong>Laporan Dibuat</strong></p>
                                <small>{{ !empty($barang->created_at) ? \Illuminate\Support\Carbon::parse($barang->created_at)->format('d M Y, H:i') : '-' }} WIB</small>
                            </div>
                        @endforelse
                    </div>
                </article>
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.body.classList.add('found-detail-page-mode');
            const infoModal = document.getElementById('status-info-modal');
            const closeInfoModal = document.getElementById('status-info-close');
            if (infoModal && closeInfoModal) {
                closeInfoModal.addEventListener('click', function () {
                    infoModal.remove();
                });
                infoModal.addEventListener('click', function (event) {
                    if (event.target === infoModal) {
                        infoModal.remove();
                    }
                });
            }
        });
    </script>
@endsection
