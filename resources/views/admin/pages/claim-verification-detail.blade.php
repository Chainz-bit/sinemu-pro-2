@extends('admin.layouts.app')

@php
    $pageTitle = 'Detail Verifikasi Klaim - SiNemu';
    $activeMenu = 'claim-verifications';
    $hideSearch = true;
    $topbarBackUrl = route('admin.claim-verifications');
    $topbarBackLabel = 'Kembali ke Verifikasi Klaim';
@endphp

@section('page-content')
    <section class="intro">
        <h1>Detail Verifikasi Klaim</h1>
        <p>Tinjau data klaim, lakukan validasi, lalu putuskan status klaim.</p>
    </section>

    @if(session('status'))
        <div class="feedback-alert feedback-alert-toast success" data-autoclose="2800" style="--autoclose-ms: 2800ms;" role="status" aria-live="polite">
            <span class="feedback-alert-icon" aria-hidden="true"><iconify-icon icon="mdi:check-circle"></iconify-icon></span>
            <div class="feedback-alert-body">
                <strong>Berhasil</strong>
                <span>{{ session('status') }}</span>
            </div>
            <button type="button" class="feedback-alert-close" data-alert-close aria-label="Tutup notifikasi">
                <iconify-icon icon="mdi:close"></iconify-icon>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="feedback-alert error">{{ session('error') }}</div>
    @endif

    <section class="claim-detail-layout">
        <article class="report-card claim-main-card">
            <header class="claim-main-head">
                <div>
                    <span class="claim-chip-label">Status Klaim</span>
                    <h2>{{ $namaBarang }}</h2>
                </div>
                <span class="status-chip {{ $statusClass }}">{{ strtoupper($statusLabel) }}</span>
            </header>

            <div class="claim-main-grid">
                <div class="claim-item-visual">
                    <img src="{{ $fotoUrl }}" alt="{{ $namaBarang }}" loading="lazy" decoding="async">
                </div>
                <div class="claim-item-info">
                    <div class="claim-info-grid">
                        <article class="claim-info-card">
                            <small>Kategori</small>
                            <strong>{{ $kategoriNama }}</strong>
                        </article>
                        <article class="claim-info-card">
                            <small>Lokasi</small>
                            <strong>{{ $lokasi }}</strong>
                        </article>
                        <article class="claim-info-card">
                            <small>Tanggal Laporan</small>
                            <strong>{{ \Illuminate\Support\Carbon::parse($tanggalLaporan)->translatedFormat('d F Y') }}</strong>
                        </article>
                        <article class="claim-info-card">
                            <small>Pengaju Klaim</small>
                            <strong>{{ $pelaporNama }}</strong>
                        </article>
                    </div>
                    <article class="claim-description-box">
                        <h3>Deskripsi</h3>
                        <p>{{ $deskripsi }}</p>
                    </article>
                </div>
            </div>
        </article>

        <aside class="claim-side-column">
            <article class="report-card claim-side-card">
                <header><h2>Informasi Pengaju</h2></header>
                <div class="claim-side-body">
                    <strong>{{ $pelaporNama }}</strong>
                    <small>{{ $pelaporEmail }}</small>
                </div>
            </article>

            <article class="report-card claim-side-card">
                <header><h2>Status & Riwayat</h2></header>
                <div class="claim-side-body">
                    <div class="claim-status-current">
                        <small>Status Saat Ini</small>
                        <span class="status-chip {{ $statusClass }}">{{ strtoupper($statusLabel) }}</span>
                    </div>
                    <ul class="claim-timeline">
                        <li>
                            <strong>Klaim diajukan</strong>
                            <span>{{ $klaim->created_at?->translatedFormat('d M Y, H:i') }} WIB</span>
                        </li>
                        <li>
                            <strong>Terakhir diperbarui</strong>
                            <span>{{ $klaim->updated_at?->translatedFormat('d M Y, H:i') }} WIB</span>
                        </li>
                    </ul>
                    @if(!empty($klaim->catatan))
                        <div class="claim-note-box">
                            <small>Catatan Admin</small>
                            <p>{{ $klaim->catatan }}</p>
                        </div>
                    @endif
                </div>
            </article>

            @if($klaim->status_klaim === 'pending')
                <article class="report-card claim-side-card">
                    <header><h2>Aksi Verifikasi</h2></header>
                    <div class="claim-side-body claim-action-stack">
                        <form method="POST" action="{{ route('admin.claim-verifications.reject', $klaim->id) }}">
                            @csrf
                            <button type="submit" class="claim-action-btn danger">Tolak Klaim</button>
                        </form>
                        <form method="POST" action="{{ route('admin.claim-verifications.approve', $klaim->id) }}">
                            @csrf
                            <button type="submit" class="claim-action-btn success">Setujui Klaim</button>
                        </form>
                    </div>
                </article>
            @endif
        </aside>
    </section>
@endsection
