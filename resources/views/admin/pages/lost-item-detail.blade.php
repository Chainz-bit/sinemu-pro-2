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
    $statusOptionLabels = [
        'pending' => 'Dalam Peninjauan',
        'disetujui' => 'Ditemukan',
        'ditolak' => 'Ditolak',
    ];
    $statusValue = $latestKlaim->status_klaim ?? 'pending';
    $pelaporEmail = $laporanBarangHilang->user?->email ?? 'Email tidak tersedia';
    $initials = collect(explode(' ', trim($pelaporName)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
        ->implode('');
@endphp

@section('page-content')
    <section class="lost-detail-page">
        @if(session('status'))
            <div class="feedback-alert success">{{ session('status') }}</div>
        @endif
        @if(session('error'))
            <div class="feedback-alert error">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="feedback-alert error">{{ $errors->first() }}</div>
        @endif

        <div class="lost-detail-header">
            <div>
                <p class="lost-detail-breadcrumb">
                    <a href="{{ route('admin.lost-items') }}">Daftar Barang Hilang</a>
                    <span>/</span>
                    <strong>Detail Barang</strong>
                </p>
                <h1>Detail Laporan Barang Hilang</h1>
            </div>
            <div class="lost-detail-actions">
                <a href="{{ route('admin.lost-items') }}" class="filter-btn lost-action-btn lost-action-btn-ghost">Kembali</a>
                <button type="submit" form="lost-status-update-form" class="filter-btn lost-action-btn lost-action-btn-primary" @disabled(!$latestKlaim)>Simpan</button>
            </div>
        </div>

        <div class="lost-detail-grid">
            <article class="report-card lost-detail-main">
                <div class="lost-detail-main-content">
                    <div class="lost-detail-image-wrap">
                        <img
                            src="{{ $fotoUrl }}"
                            alt="{{ $laporanBarangHilang->nama_barang }}"
                            class="lost-detail-image"
                            loading="lazy"
                            decoding="async"
                        >
                    </div>

                    <div class="lost-detail-body">
                        <h2>{{ strtoupper($laporanBarangHilang->nama_barang) }}</h2>
                        <p>{{ $laporanBarangHilang->keterangan ?: 'Tidak ada deskripsi tambahan.' }}</p>

                        <div class="lost-detail-meta">
                            <div>
                                <span>Kategori</span>
                                <strong>Tidak Dikategorikan</strong>
                            </div>
                            <div>
                                <span>Tanggal Hilang</span>
                                <strong>{{ !empty($laporanBarangHilang->tanggal_hilang) ? \Illuminate\Support\Carbon::parse($laporanBarangHilang->tanggal_hilang)->format('d M Y') : '-' }}</strong>
                            </div>
                            <div>
                                <span>Lokasi Ditemukan Terakhir</span>
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

            <div class="lost-detail-side">
                <article class="report-card lost-detail-panel">
                    <header><h2>Status Saat Ini</h2></header>
                    <div class="lost-detail-panel-body">
                        <span class="status-chip {{ $statusClass }}">{{ $statusLabel }}</span>

                        <form method="POST" action="{{ route('admin.lost-items.update-status', $laporanBarangHilang->id) }}" id="lost-status-update-form" class="lost-status-edit-form">
                            @csrf
                            @method('PATCH')

                            <div class="lost-form-group">
                                <label class="lost-status-form-label" for="status_klaim">Status Baru</label>
                                <select id="status_klaim" name="status_klaim" class="form-input lost-status-form-input" @disabled(!$latestKlaim)>
                                    @foreach($statusOptionLabels as $optionValue => $optionLabel)
                                        <option value="{{ $optionValue }}" @selected(old('status_klaim', $statusValue) === $optionValue)>{{ $optionLabel }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="lost-form-group">
                                <label class="lost-status-form-label" for="catatan">Catatan (Opsional)</label>
                                <textarea
                                    id="catatan"
                                    name="catatan"
                                    class="form-input form-textarea-sm lost-status-form-input"
                                    placeholder="{{ $latestKlaim?->catatan ?: 'Contoh: Bukti kepemilikan valid dan sudah diverifikasi admin.' }}"
                                    @disabled(!$latestKlaim)
                                >{{ old('catatan') }}</textarea>
                            </div>
                        </form>

                        @if(!$latestKlaim)
                            <p class="lost-empty-note">Belum ada data klaim untuk laporan ini, jadi status belum bisa diedit.</p>
                        @endif
                    </div>
                </article>

                <article class="report-card lost-detail-panel">
                    <header><h2>Informasi Pelapor</h2></header>
                    <div class="lost-detail-panel-body">
                        <div class="lost-person-row">
                            <span class="lost-person-avatar">{{ $initials ?: 'US' }}</span>
                            <div>
                                <p><strong>{{ $pelaporName }}</strong></p>
                                <small>Pelapor Barang Hilang</small>
                            </div>
                        </div>
                        <div class="lost-contact-actions">
                            <a href="#" class="filter-btn">Hubungi</a>
                            <a href="#" class="filter-btn">Email</a>
                        </div>
                        <p>{{ $pelaporEmail }}</p>
                    </div>
                </article>

                <article class="report-card lost-detail-panel">
                    <header><h2>Lokasi &amp; Waktu Laporan</h2></header>
                    <div class="lost-detail-panel-body">
                        <div class="lost-info-item">
                            <span class="lost-info-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 7a1 1 0 0 1 1 1v4.1l2.2 1.47a1 1 0 1 1-1.1 1.66l-2.65-1.76A1 1 0 0 1 11 13V8a1 1 0 0 1 1-1zM12 3a9 9 0 1 1 0 18a9 9 0 0 1 0-18zm0 2a7 7 0 1 0 0 14a7 7 0 0 0 0-14z" fill="currentColor"/></svg>
                            </span>
                            <div>
                                <small>Hilang Pada</small>
                                <p><strong>{{ !empty($laporanBarangHilang->tanggal_hilang) ? \Illuminate\Support\Carbon::parse($laporanBarangHilang->tanggal_hilang)->format('d M Y, H:i') : '-' }} WIB</strong></p>
                            </div>
                        </div>

                        <div class="lost-info-item">
                            <span class="lost-info-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4a1 1 0 0 1 1 1v1h8V5a1 1 0 1 1 2 0v1h1a2 2 0 0 1 2 2v3H3V8a2 2 0 0 1 2-2h1V5a1 1 0 0 1 1-1zm14 9v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-6h18zm-8 2H7a1 1 0 1 0 0 2h6a1 1 0 1 0 0-2z" fill="currentColor"/></svg>
                            </span>
                            <div>
                                <small>Lokasi Terakhir</small>
                                <p><strong>{{ $laporanBarangHilang->lokasi_hilang ?: '-' }}</strong></p>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="report-card lost-detail-panel">
                    <header><h2>Riwayat Aktivitas</h2></header>
                    <div class="lost-detail-panel-body">
                        <div class="lost-activity-item">
                            <p><strong>Laporan Dibuat</strong></p>
                            <small>{{ !empty($laporanBarangHilang->created_at) ? \Illuminate\Support\Carbon::parse($laporanBarangHilang->created_at)->format('d M Y, H:i') : '-' }} WIB</small>
                        </div>
                        @if($latestKlaim)
                            <div class="lost-activity-item">
                                <p><strong>Status Klaim Terakhir</strong></p>
                                <small>{{ $statusLabel }} - {{ \Illuminate\Support\Carbon::parse($latestKlaim->created_at)->format('d M Y, H:i') }} WIB</small>
                            </div>
                        @endif
                    </div>
                </article>
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.body.classList.add('lost-detail-page-mode');
        });
    </script>
@endsection
