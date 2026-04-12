@extends('admin.layouts.app')

@php
    $pageTitle = 'Detail Barang Hilang - SiNemu';
    $activeMenu = 'lost-items';
    $searchAction = route('admin.lost-items');
    $searchPlaceholder = 'Cari laporan atau barang';

    $fotoPath = trim((string) ($laporanBarangHilang->foto_barang ?? ''), '/');
    [$folder, $subPath] = array_pad(explode('/', $fotoPath, 2), 2, '');
    $fotoUrl = !empty($fotoPath) && in_array($folder, ['barang-hilang', 'barang-temuan', 'verifikasi-klaim'], true) && $subPath !== ''
        ? route('media.image', ['folder' => $folder, 'path' => $subPath], false)
        : (str_contains(strtolower((string) $laporanBarangHilang->nama_barang), 'dompet')
            ? route('media.image', ['folder' => 'barang-hilang', 'path' => 'dompet.webp'], false)
            : route('media.image', ['folder' => 'barang-temuan', 'path' => 'hp.webp'], false));

    $statusMap = [
        null => ['BELUM DITEMUKAN', 'status-dalam_peninjauan'],
        'pending' => ['DALAM PENINJAUAN', 'status-diproses'],
        'disetujui' => ['DITEMUKAN', 'status-selesai'],
        'ditolak' => ['DITOLAK', 'status-ditolak'],
    ];
    [$statusLabel, $statusClass] = $statusMap[$latestKlaim->status_klaim ?? null] ?? ['BELUM DITEMUKAN', 'status-dalam_peninjauan'];

    $pelaporName = $laporanBarangHilang->user?->nama ?? $laporanBarangHilang->user?->name ?? 'Pengguna';
@endphp

@section('page-content')
    <section class="found-detail-page">
        <div class="found-detail-header">
            <div>
                <p class="found-detail-breadcrumb">
                    <a href="{{ route('admin.lost-items') }}">Daftar Barang Hilang</a>
                    <span>/</span>
                    <strong>Detail Barang</strong>
                </p>
                <h1>Detail Laporan Barang Hilang</h1>
            </div>
            <div class="found-detail-actions">
                <a href="{{ route('admin.lost-items') }}" class="filter-btn found-action-btn found-action-btn-ghost">Kembali</a>
            </div>
        </div>

        <div class="found-detail-grid">
            <article class="report-card found-detail-main">
                <div class="found-detail-main-content">
                    <div class="found-detail-image-wrap">
                        <img
                            src="{{ $fotoUrl }}"
                            alt="{{ $laporanBarangHilang->nama_barang }}"
                            class="found-detail-image"
                            loading="lazy"
                            decoding="async"
                        >
                    </div>

                    <div class="found-detail-body">
                        <h2>{{ $laporanBarangHilang->nama_barang }}</h2>
                        <p>{{ $laporanBarangHilang->keterangan ?: 'Tidak ada deskripsi tambahan.' }}</p>

                        <div class="found-detail-meta">
                            <div>
                                <span>Pelapor</span>
                                <strong>{{ $pelaporName }}</strong>
                            </div>
                            <div>
                                <span>Tanggal Hilang</span>
                                <strong>{{ !empty($laporanBarangHilang->tanggal_hilang) ? \Illuminate\Support\Carbon::parse($laporanBarangHilang->tanggal_hilang)->format('d M Y') : '-' }}</strong>
                            </div>
                            <div>
                                <span>Lokasi Hilang</span>
                                <strong>{{ $laporanBarangHilang->lokasi_hilang ?: '-' }}</strong>
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
                    <header><h2>Status Klaim</h2></header>
                    <div class="found-detail-panel-body">
                        <span class="status-chip {{ $statusClass }}">{{ $statusLabel }}</span>
                        @if($latestKlaim && !empty($latestKlaim->catatan))
                            <p><strong>Catatan Admin</strong></p>
                            <p>{{ $latestKlaim->catatan }}</p>
                        @endif
                    </div>
                </article>

                <article class="report-card found-detail-panel">
                    <header><h2>Informasi Pelapor</h2></header>
                    <div class="found-detail-panel-body">
                        <p><strong>{{ $pelaporName }}</strong></p>
                        <p>{{ $laporanBarangHilang->user?->email ?? 'Email tidak tersedia' }}</p>
                    </div>
                </article>

                <article class="report-card found-detail-panel">
                    <header><h2>Riwayat Laporan</h2></header>
                    <div class="found-detail-panel-body">
                        <div class="activity-item">
                            <p><strong>Laporan Dibuat</strong></p>
                            <small>{{ !empty($laporanBarangHilang->created_at) ? \Illuminate\Support\Carbon::parse($laporanBarangHilang->created_at)->format('d M Y, H:i') : '-' }} WIB</small>
                        </div>
                        @if($latestKlaim)
                            <div class="activity-item">
                                <p><strong>Status Klaim Terakhir</strong></p>
                                <small>{{ $statusLabel }} - {{ \Illuminate\Support\Carbon::parse($latestKlaim->created_at)->format('d M Y, H:i') }} WIB</small>
                            </div>
                        @endif
                    </div>
                </article>
            </div>
        </div>
    </section>
@endsection
