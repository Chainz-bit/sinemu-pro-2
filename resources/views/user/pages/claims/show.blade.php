@extends('user.layouts.app')

@php
    $pageTitle = 'Tiket Pengambilan Barang ' . $claimCode . ' - SiNemu';
    $activeMenu = 'claim-history';
    $topbarBackUrl = route('user.claim-history');
    $topbarBackLabel = 'Kembali ke Riwayat Klaim';
    $searchAction = route('user.dashboard');
    $searchPlaceholder = 'Cari riwayat atau barang';
@endphp

@section('page-content')
<style>
@media print {
    @page {
        size: A4 portrait;
        margin: 10mm 14mm;
    }

    /* Netralkan pembungkus layout utama agar tidak terkunci */
    html, body, #app, main, .main-content, .dashboard-layout, .container,
    .admin-shell, .user-main-content, .dashboard-page-content, .claim-ticket-page,
    body.claim-ticket-page-mode,
    body.claim-ticket-page-mode .admin-shell,
    body.claim-ticket-page-mode .main-content,
    body.claim-ticket-page-mode .user-main-content {
        height: auto !important;
        min-height: auto !important;
        max-height: none !important;
        overflow: visible !important;
        position: static !important;
        display: block !important;
        background: #ffffff !important;
        color: #000000 !important;
        padding: 0 !important;
        margin: 0 !important;
        border: none !important;
        box-shadow: none !important;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
        font-size: 9.5pt !important;
        line-height: 1.35 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* Sembunyikan seluruh elemen halaman agar tidak ada teks bocor */
    body * {
        visibility: hidden !important;
    }

    /* Tampilkan HANYA kontena dokumen cetak resmi */
    #printable-ticket,
    #printable-ticket * {
        visibility: visible !important;
    }

    /* Sembunyikan elemen UI website secara display none */
    nav, aside, header.topbar, .sidebar, .sidebar-backdrop, .navbar, .topbar, .topbar-wrapper,
    .no-print, .print-hidden, button, a.btn, .btn, .btn-copy, .btn-copy-code,
    .btn-ticket-secondary, .btn-claim-contact, input, .search-bar, .search-form,
    .user-dropdown, .top-actions, .notification-modal, .breadcrumb, .breadcrumbs,
    .confirm-modal-backdrop, .claim-ticket-intro, .claim-ticket-layout {
        display: none !important;
    }

    /* Format Flat Document Resmi Instansi */
    .official-print-ticket,
    #printable-ticket,
    .printable-area {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        border: 1px solid #334155 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        background: #ffffff !important;
        box-sizing: border-box !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    .print-ticket-content-wrap {
        padding: 16px 20px !important;
        box-sizing: border-box !important;
        background: #ffffff !important;
    }

    /* Kop Dokumen Resmi */
    .print-ticket-kop {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        padding-bottom: 8px !important;
        border-bottom: 3px double #0f172a !important;
        margin-bottom: 10px !important;
        background: transparent !important;
    }

    .print-kop-brand {
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
    }

    .print-kop-logo {
        width: 44px !important;
        height: 44px !important;
        object-fit: contain !important;
        border-radius: 0 !important;
    }

    .print-brand-title {
        display: block !important;
        font-size: 16pt !important;
        font-weight: 800 !important;
        color: #0f172a !important;
        line-height: 1.1 !important;
        letter-spacing: -0.02em !important;
    }

    .print-brand-subtitle {
        display: block !important;
        font-size: 8pt !important;
        color: #475569 !important;
        font-weight: 500 !important;
    }

    .print-kop-meta {
        text-align: right !important;
        font-size: 8.5pt !important;
        color: #1e293b !important;
        line-height: 1.4 !important;
    }

    .print-meta-row strong {
        color: #0f172a !important;
        font-weight: 700 !important;
    }

    .print-code-text {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
        font-weight: 800 !important;
        color: #0f172a !important;
    }

    /* Judul Dokumen */
    .print-doc-title-box {
        text-align: center !important;
        margin: 6px 0 8px !important;
    }

    .print-doc-title {
        font-size: 13pt !important;
        font-weight: 800 !important;
        color: #0f172a !important;
        margin: 0 !important;
        letter-spacing: 0.04em !important;
        text-transform: uppercase !important;
        line-height: 1.2 !important;
    }

    .print-doc-subtitle {
        font-size: 8.5pt !important;
        color: #475569 !important;
        margin: 2px 0 0 !important;
        font-weight: 500 !important;
    }

    /* Status Bar */
    .print-status-bar {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        background: #f8fafc !important;
        border-top: 1px solid #cbd5e1 !important;
        border-bottom: 1px solid #cbd5e1 !important;
        border-left: none !important;
        border-right: none !important;
        border-radius: 0 !important;
        padding: 4px 10px !important;
        font-size: 8.5pt !important;
        margin-bottom: 8px !important;
    }

    .print-status-label {
        color: #475569 !important;
        font-weight: 600 !important;
    }

    .print-status-value {
        color: #0f172a !important;
        font-weight: 700 !important;
    }

    /* Section Grid */
    .print-section-grid {
        display: grid !important;
        grid-template-columns: 1.15fr 0.85fr !important;
        gap: 8px !important;
        margin-bottom: 8px !important;
    }

    .print-card {
        border: 1px solid #94a3b8 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        overflow: hidden !important;
        background: #ffffff !important;
    }

    .print-card-header {
        background: #f8fafc !important;
        border-bottom: 1px solid #94a3b8 !important;
        border-radius: 0 !important;
        padding: 4px 8px !important;
    }

    .print-card-header h2 {
        margin: 0 !important;
        font-size: 8.5pt !important;
        font-weight: 700 !important;
        color: #0f172a !important;
        letter-spacing: 0.02em !important;
        text-transform: uppercase !important;
    }

    .print-card-body {
        padding: 5px 8px !important;
        border-radius: 0 !important;
        background: #ffffff !important;
    }

    .print-item-content {
        display: flex !important;
        gap: 8px !important;
        align-items: flex-start !important;
    }

    .print-table {
        width: 100% !important;
        border-collapse: collapse !important;
        font-size: 8pt !important;
    }

    .print-table tr {
        border-bottom: 1px solid #f1f5f9 !important;
    }

    .print-table tr:last-child {
        border-bottom: none !important;
    }

    .print-table td {
        padding: 2.5px 0 !important;
        vertical-align: top !important;
        line-height: 1.3 !important;
    }

    .print-label {
        width: 105px !important;
        color: #475569 !important;
        font-weight: 500 !important;
    }

    .print-val {
        color: #0f172a !important;
        word-break: break-word !important;
    }

    .print-email-val {
        word-break: break-all !important;
        overflow-wrap: anywhere !important;
        font-size: 8pt !important;
        color: #0f172a !important;
    }

    .print-item-thumb {
        width: 80px !important;
        height: 80px !important;
        border-radius: 0 !important;
        overflow: hidden !important;
        border: 1px solid #94a3b8 !important;
        flex-shrink: 0 !important;
        background: #f8fafc !important;
    }

    .print-item-thumb img {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        border-radius: 0 !important;
    }

    /* Informasi Posko */
    .print-posko-card {
        margin-bottom: 8px !important;
    }

    .print-posko-grid {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 4px 14px !important;
        font-size: 8pt !important;
    }

    .print-meta-label {
        display: block !important;
        color: #475569 !important;
        font-size: 7.5pt !important;
        font-weight: 500 !important;
    }

    .print-meta-val {
        color: #0f172a !important;
        font-size: 8pt !important;
        line-height: 1.25 !important;
    }

    /* SOP Box */
    .print-sop-box {
        background: #f8fafc !important;
        border: 1px solid #cbd5e1 !important;
        border-left: 3px solid #334155 !important;
        border-radius: 0 !important;
        padding: 5px 8px !important;
        font-size: 7.5pt !important;
        color: #1e293b !important;
        line-height: 1.35 !important;
        margin-bottom: 6px !important;
    }

    /* Titimangsa & Tanda Tangan */
    .print-titimangsa-wrap {
        text-align: right !important;
        margin: 6px 0 4px !important;
    }

    .print-titimangsa-text {
        font-size: 8.5pt !important;
        color: #0f172a !important;
        font-weight: 500 !important;
        padding-right: 25px !important;
    }

    .print-signatures {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 24px !important;
        margin-top: 4px !important;
        padding-top: 2px !important;
    }

    .print-signature-col {
        text-align: center !important;
    }

    .print-sign-role {
        font-size: 8pt !important;
        font-weight: 600 !important;
        color: #0f172a !important;
        margin: 0 !important;
    }

    .print-sign-space {
        height: 60px !important;
    }

    .print-sign-name {
        font-size: 8.5pt !important;
        font-weight: 700 !important;
        color: #000000 !important;
        margin: 0 !important;
        border-bottom: 1px solid #000000 !important;
        display: inline-block !important;
        min-width: 190px !important;
        padding-bottom: 2px !important;
        border-radius: 0 !important;
    }

    .print-sign-note {
        font-size: 7pt !important;
        color: #64748b !important;
        margin: 2px 0 0 !important;
    }
}
</style>
<div class="dashboard-page-content claim-ticket-page pb-5" style="height: auto; min-height: auto; overflow: visible; padding-bottom: 3.5rem;">
    {{-- Header / Intro --}}
    <section class="intro claim-ticket-intro no-print print-hidden">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h1>Tiket & Instruksi Pengambilan Barang</h1>
                <p>Tunjukkan tiket dan identitas diri Anda di lokasi posko layanan untuk verifikasi dan serah terima fisik barang.</p>
            </div>
            <div class="d-flex align-items-center gap-2 no-print print-hidden">
                <button type="button" class="btn btn-ticket-secondary d-inline-flex align-items-center gap-2" onclick="window.print()">
                    <iconify-icon icon="mdi:printer-outline" width="18"></iconify-icon>
                    <span>Cetak Tiket</span>
                </button>
                <a href="{{ route('user.claim-history') }}" class="btn btn-ticket-secondary d-inline-flex align-items-center gap-2">
                    <iconify-icon icon="mdi:format-list-bulleted" width="18"></iconify-icon>
                    <span>Riwayat Klaim</span>
                </a>
            </div>
        </div>
    </section>

    <div class="claim-ticket-layout no-print print-hidden" style="margin-bottom: 2rem;">
        {{-- Kolom Kiri / Utama: Card Bukti & Instruksi Pengambilan Barang --}}
        <div class="claim-ticket-main-column">
            <article class="report-card claim-instruction-card mb-4" style="margin-bottom: 2rem;">
                {{-- Banner Bukti Pengambilan --}}
                <div class="claim-ticket-banner">
                    <div class="claim-ticket-banner-header">
                        <div class="claim-ticket-type-label">
                            <iconify-icon icon="mdi:file-document-check-outline" width="18"></iconify-icon>
                            <span>BUKTI PENGAMBILAN BARANG</span>
                        </div>
                        <div class="claim-ticket-badge-wrap">
                            @if($isReadyForPickup)
                                <span class="status-chip status-diproses claim-chip-lg claim-chip-ready">
                                    <iconify-icon icon="mdi:check-circle" width="16"></iconify-icon>
                                    Siap Diambil
                                </span>
                            @elseif($isCompleted)
                                <span class="status-chip status-selesai claim-chip-lg claim-chip-completed">
                                    <iconify-icon icon="mdi:package-variant-closed-check" width="16"></iconify-icon>
                                    Sudah Diserahkan (Selesai)
                                </span>
                            @elseif($statusKey === 'ditolak')
                                <span class="status-chip status-ditolak claim-chip-lg claim-chip-rejected">
                                    <iconify-icon icon="mdi:close-circle" width="16"></iconify-icon>
                                    Tidak Disetujui
                                </span>
                            @else
                                <span class="status-chip status-dalam_peninjauan claim-chip-lg claim-chip-pending">
                                    <iconify-icon icon="mdi:clock-outline" width="16"></iconify-icon>
                                    Menunggu Tinjauan
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Kode Pengambilan Box --}}
                    <div class="claim-ticket-code-box">
                        <div class="claim-ticket-code-label">KODE PENGAMBILAN BARANG</div>
                        <div class="claim-ticket-code-display">
                            <span class="claim-ticket-code-val" id="claimTicketCodeText">{{ $claimCode }}</span>
                            <button type="button" class="btn-copy-code print-hidden" data-code="{{ $claimCode }}" onclick="copyClaimCode(this.dataset.code)" title="Salin Kode Pengambilan">
                                <iconify-icon icon="mdi:content-copy" width="15"></iconify-icon>
                                <span id="copyFeedbackText">Salin</span>
                            </button>
                        </div>
                        <small class="claim-ticket-code-desc">
                            Tunjukkan kode ini kepada petugas posko bersama kartu identitas diri yang sah.
                        </small>
                    </div>

                    @if(!empty($statusMeta['description']))
                        <div class="claim-ticket-status-note">
                            <p class="mb-0">{{ $statusMeta['description'] }}</p>
                        </div>
                    @endif
                </div>

                {{-- Section Instruksi Pengambilan --}}
                <div class="claim-instruction-body">
                    {{-- Blok 1: Lokasi Posko Layanan --}}
                    <section class="claim-instruction-section">
                        <div class="claim-section-title">
                            <div class="claim-section-icon icon-posko">
                                <iconify-icon icon="mdi:map-marker-radius-outline" width="20"></iconify-icon>
                            </div>
                            <div>
                                <h3>Lokasi Posko Layanan</h3>
                                <p>Tempat verifikasi fisik dan serah terima barang temuan.</p>
                            </div>
                        </div>

                        <div class="claim-posko-card">
                            <div class="claim-posko-row">
                                <span class="claim-posko-label">Nama Posko / Instansi</span>
                                <strong class="claim-posko-value claim-posko-name">{{ $poskoName }}</strong>
                            </div>
                            <div class="claim-posko-row">
                                <span class="claim-posko-label">Alamat Lengkap</span>
                                <strong class="claim-posko-value">{{ $poskoAddress }}</strong>
                            </div>
                            <div class="claim-posko-row">
                                <span class="claim-posko-label">Wilayah / Kecamatan</span>
                                <span class="claim-posko-value">{{ $poskoWilayah }}</span>
                            </div>
                            <div class="claim-posko-row">
                                <span class="claim-posko-label">Jam Layanan Operasional</span>
                                <span class="claim-posko-value fw-semibold text-slate-800">{{ $jamLayanan }}</span>
                            </div>
                            <div class="claim-posko-row">
                                <span class="claim-posko-label">Petugas Penanggung Jawab</span>
                                <span class="claim-posko-value">{{ $penanggungJawab }}</span>
                            </div>
                            @if(!empty($catatanPosko))
                                <div class="claim-posko-row claim-posko-alert">
                                    <span class="claim-posko-label">Catatan Posko</span>
                                    <div class="claim-posko-value text-muted">{{ $catatanPosko }}</div>
                                </div>
                            @endif
                        </div>
                    </section>

                    {{-- Blok 2: Syarat Penyerahan Fisik --}}
                    <section class="claim-instruction-section">
                        <div class="claim-section-title">
                            <div class="claim-section-icon icon-rules">
                                <iconify-icon icon="mdi:card-account-details-outline" width="20"></iconify-icon>
                            </div>
                            <div>
                                <h3>Syarat Penyerahan Fisik Barang</h3>
                                <p>Wajib dipenuhi saat pengambilan di posko layanan:</p>
                            </div>
                        </div>

                        <div class="claim-requirements-list">
                            <div class="claim-requirement-item">
                                <div class="claim-req-number">1</div>
                                <div class="claim-req-content">
                                    <strong>Membawa Identitas Asli yang Sah</strong>
                                    <p>Bawa KTM (Kartu Tanda Mahasiswa), KTP, atau SIM asli atas nama <strong>{{ $user?->nama ?? $user?->name ?? 'Pemilik Klaim' }}</strong> sesuai profil akun SiNemu Anda.</p>
                                </div>
                            </div>

                            <div class="claim-requirement-item">
                                <div class="claim-req-number">2</div>
                                <div class="claim-req-content">
                                    <strong>Menunjukkan Kode Tiket Klaim</strong>
                                    <p>Tunjukkan kode tiket <code>{{ $claimCode }}</code> pada layar smartphone Anda kepada petugas loket posko.</p>
                                </div>
                            </div>

                            <div class="claim-requirement-item">
                                <div class="claim-req-number">3</div>
                                <div class="claim-req-content">
                                    <strong>Pencocokan Ciri Khusus Bersama Petugas</strong>
                                    <p>Petugas akan mencocokkan ciri khusus, detail fisik, nomor seri, atau isi barang temuan yang Anda laporkan.</p>
                                </div>
                            </div>

                            <div class="claim-requirement-item">
                                <div class="claim-req-number">4</div>
                                <div class="claim-req-content">
                                    <strong>Konfirmasi & Penandatanganan Berita Acara</strong>
                                    <p>Setelah barang diverifikasi sesuai, petugas dan Anda akan menandatangani tanda terima/berita acara serah terima barang di tempat.</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- Blok 3: Hubungi Pengelola --}}
                    <section class="claim-instruction-section print-hidden">
                        <div class="claim-section-title">
                            <div class="claim-section-icon icon-contact">
                                <iconify-icon icon="mdi:phone-message-outline" width="20"></iconify-icon>
                            </div>
                            <div>
                                <h3>Hubungi Pengelola Posko</h3>
                                <p>Konfirmasi waktu kedatangan atau tanyakan arah lokasi posko layanan:</p>
                            </div>
                        </div>

                        <div class="claim-contact-grid">
                            @if(!empty($waUrl))
                                <a href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer" class="btn-claim-contact btn-contact-wa" title="WhatsApp: {{ $contactRaw }}">
                                    <iconify-icon icon="mdi:whatsapp" width="18"></iconify-icon>
                                    <span class="btn-contact-text">Hubungi via WhatsApp</span>
                                </a>
                            @endif

                            @if(!empty($telHref))
                                <a href="{{ $telHref }}" class="btn-claim-contact btn-contact-tel" title="Telepon: {{ $contactRaw }}">
                                    <iconify-icon icon="mdi:phone" width="18"></iconify-icon>
                                    <span class="btn-contact-text">Telepon Posko</span>
                                </a>
                            @endif

                            @if(!empty($adminEmail))
                                <a href="mailto:{{ $adminEmail }}?subject={{ rawurlencode('Pertanyaan Pengambilan Klaim ' . $claimCode) }}" class="btn-claim-contact btn-contact-email" title="Email: {{ $adminEmail }}">
                                    <iconify-icon icon="mdi:email-outline" width="18"></iconify-icon>
                                    <span class="btn-contact-text">Email Posko</span>
                                </a>
                            @endif

                            @if(empty($waUrl) && empty($telHref) && empty($adminEmail))
                                <div class="text-muted p-2 bg-light rounded w-100 small">
                                    Kontak pengelola belum terdaftar secara spesifik. Silakan langsung datangi alamat posko layanan di atas sesuai jam operasional.
                                </div>
                            @endif
                        </div>
                    </section>
                </div>
            </article>
        </div>

        {{-- Kolom Kanan: Ringkasan Barang & Bukti Klaim --}}
        <div class="claim-ticket-side-column">
            {{-- Card Detail Barang Temuan --}}
            <article class="report-card claim-item-summary-card mb-4" style="margin-bottom: 2rem;">
                <header class="claim-side-header">
                    <h2>Barang Temuan Terkait</h2>
                </header>
                <div class="claim-item-summary-body">
                    <div class="claim-item-image-wrap">
                        <img src="{{ $itemImageUrl }}" alt="{{ $barang?->nama_barang ?? 'Barang Temuan' }}" loading="lazy" decoding="async" data-fallback="{{ asset('img/login-image.png') }}" onerror="this.onerror=null;this.src=this.dataset.fallback;">
                    </div>
                    <div class="claim-item-info">
                        <h4 class="claim-item-name">{{ $barang?->nama_barang ?? $klaim->laporanHilang?->nama_barang ?? 'Barang Temuan' }}</h4>
                        <span class="badge bg-light text-secondary border px-2 py-1 mb-2">
                            {{ $barang?->kategori?->nama_kategori ?? 'Umum' }}
                        </span>

                        <div class="claim-item-kv">
                            <span>Lokasi Ditemukan</span>
                            <strong>{{ $barang?->lokasi_ditemukan ?? '-' }}</strong>
                        </div>

                        <div class="claim-item-kv">
                            <span>Tanggal Ditemukan</span>
                            <strong>
                                {{ !empty($barang?->tanggal_ditemukan) ? \Illuminate\Support\Carbon::parse($barang->tanggal_ditemukan)->format('d M Y') : '-' }}
                            </strong>
                        </div>

                        @if(!empty($barang?->deskripsi))
                            <div class="claim-item-kv">
                                <span>Deskripsi Barang</span>
                                <p class="mb-0 text-muted small">{{ $barang->deskripsi }}</p>
                            </div>
                        @endif

                        @if(!empty($barang?->ciri_khusus))
                            <div class="claim-item-kv">
                                <span>Ciri Khusus Terdaftar</span>
                                <p class="mb-0 text-muted small">{{ $barang->ciri_khusus }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </article>

            {{-- Card Ringkasan Bukti Klaim Pemohon --}}
            <article class="report-card claim-evidence-summary-card mb-4" style="margin-bottom: 2rem;">
                <header class="claim-side-header">
                    <h2>Bukti Klaim yang Anda Ajukan</h2>
                </header>
                <div class="claim-evidence-body">
                    <div class="claim-item-kv">
                        <span>Bukti Kepemilikan</span>
                        <strong>{{ $klaim->bukti_kepemilikan ?: ($klaim->catatan ?: '-') }}</strong>
                    </div>

                    @if(!empty($klaim->bukti_ciri_khusus))
                        <div class="claim-item-kv">
                            <span>Ciri Khusus Anda</span>
                            <strong>{{ $klaim->bukti_ciri_khusus }}</strong>
                        </div>
                    @endif

                    @if(!empty($klaim->bukti_detail_isi))
                        <div class="claim-item-kv">
                            <span>Detail Isi Barang</span>
                            <strong>{{ $klaim->bukti_detail_isi }}</strong>
                        </div>
                    @endif

                    @if(!empty($klaim->bukti_lokasi_spesifik))
                        <div class="claim-item-kv">
                            <span>Lokasi Spesifik Kejadian</span>
                            <strong>{{ $klaim->bukti_lokasi_spesifik }}</strong>
                        </div>
                    @endif

                    @if(!empty($buktiFotoList))
                        <div class="claim-item-kv">
                            <span>Foto Bukti Terlampir</span>
                            <div class="claim-evidence-photos d-flex flex-wrap gap-2 mt-2">
                                @foreach($buktiFotoList as $foto)
                                    <a href="{{ $foto['url'] }}" target="_blank" class="claim-photo-thumb" title="Lihat Foto Bukti">
                                        <img src="{{ $foto['url'] }}" alt="Bukti {{ $loop->iteration }}" loading="lazy">
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(!empty($klaim->catatan_verifikasi_admin))
                        <div class="claim-item-kv claim-admin-verification-note">
                            <span class="text-primary-emphasis fw-bold">Catatan Verifikator Pengelola</span>
                            <div class="p-2 mt-1 rounded bg-info-subtle text-info-emphasis border border-info-subtle small">
                                {{ $klaim->catatan_verifikasi_admin }}
                            </div>
                        </div>
                    @endif

                    @if(!empty($klaim->alasan_penolakan))
                        <div class="claim-item-kv claim-admin-rejection-note">
                            <span class="text-danger fw-bold">Alasan Penolakan</span>
                            <div class="p-2 mt-1 rounded bg-danger-subtle text-danger border border-danger-subtle small">
                                {{ $klaim->alasan_penolakan }}
                            </div>
                        </div>
                    @endif

                    {{-- Info Serah Terima (Jika sudah diserahkan / selesai) --}}
                    @if($isCompleted && !empty($klaim->diserahkan_at))
                        <div class="claim-item-kv claim-handover-record mt-3 pt-3 border-top">
                            <span class="text-success fw-bold d-flex align-items-center gap-1">
                                <iconify-icon icon="mdi:check-all"></iconify-icon>
                                Data Serah Terima Fisik
                            </span>
                            <div class="small mt-1 text-muted">
                                <div>Penerima: <strong>{{ $klaim->nama_penerima ?? $user?->nama }}</strong></div>
                                <div>No. Identitas: <strong>{{ $klaim->nomor_identitas_penerima ?? '-' }}</strong></div>
                                <div>Waktu Serah Terima: <strong>{{ $klaim->diserahkan_at->translatedFormat('d F Y, H:i') }} WIB</strong></div>
                                @if(!empty($klaim->catatan_serah_terima))
                                    <div>Catatan: {{ $klaim->catatan_serah_terima }}</div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </article>
        </div>
    </div>

    {{-- =========================================================================
         DOKUMEN RESMI CETAK TIKET PENGAMBILAN (A4 PORTRAIT SINGLE PAGE)
         Ditampilkan hanya saat pencetakan (@media print)
         ========================================================================= --}}
    <div id="printable-ticket" class="official-print-ticket printable-area">
        <div class="print-ticket-content-wrap">
            {{-- 1. Kop Surat / Header Dokumen Resmi --}}
            <header class="print-ticket-kop">
                <div class="print-kop-brand">
                    <img src="{{ asset('img/logo.png') }}" alt="Logo SiNemu" class="print-kop-logo" onerror="this.style.display='none';">
                    <div class="print-kop-brand-text">
                        <span class="print-brand-title">SiNemu</span>
                        <span class="print-brand-subtitle">Sistem Informasi Pengelolaan Barang Hilang & Temuan</span>
                    </div>
                </div>
                <div class="print-kop-meta">
                    <div class="print-meta-row"><strong>Nomor Registrasi:</strong> <span class="print-code-text">{{ $claimCode }}</span></div>
                    <div class="print-meta-row"><strong>Tanggal Cetak:</strong> {{ now()->translatedFormat('d F Y, H:i') }} WIB</div>
                </div>
            </header>

            {{-- 2. Judul Dokumen Resmi --}}
            <div class="print-doc-title-box">
                <h1 class="print-doc-title">BUKTI PENGAMBILAN BARANG TEMUAN</h1>
                <p class="print-doc-subtitle">Dokumen Serah Terima Resmi Posko Layanan SiNemu</p>
            </div>

            {{-- Status Ringkas Dokumen --}}
            <div class="print-status-bar">
                <span class="print-status-label">STATUS DOKUMEN:</span>
                <span class="print-status-value">
                    @if($isReadyForPickup)
                        SIAP DIAMBIL (TERVERIFIKASI RESMI)
                    @elseif($isCompleted)
                        SUDAH DISERAHKAN KEPADA PEMILIK (SELESAI)
                    @elseif($statusKey === 'ditolak')
                        TIDAK DISETUJUI
                    @else
                        MENUNGGU TINJAUAN PETUGAS
                    @endif
                </span>
            </div>

            {{-- 3. Grid Rincian Barang & Rincian Pengambil (Tabel Formal) --}}
            <div class="print-section-grid">
                {{-- Kolom Kiri: Rincian Barang yang Diambil --}}
                <div class="print-card">
                    <div class="print-card-header">
                        <h2>I. RINCIAN BARANG TEMUAN</h2>
                    </div>
                    <div class="print-card-body">
                        <div class="print-item-content">
                            <table class="print-table">
                                <tr>
                                    <td class="print-label">Nama Barang</td>
                                    <td class="print-val"><strong>{{ $barang?->nama_barang ?? $klaim->laporanHilang?->nama_barang ?? 'Barang Temuan' }}</strong></td>
                                </tr>
                                <tr>
                                    <td class="print-label">Kategori</td>
                                    <td class="print-val">{{ $barang?->kategori?->nama_kategori ?? 'Umum' }}</td>
                                </tr>
                                <tr>
                                    <td class="print-label">Lokasi Ditemukan</td>
                                    <td class="print-val">{{ $barang?->lokasi_ditemukan ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="print-label">Tanggal Temuan</td>
                                    <td class="print-val">
                                        {{ !empty($barang?->tanggal_ditemukan) ? \Illuminate\Support\Carbon::parse($barang->tanggal_ditemukan)->translatedFormat('d F Y') : '-' }}
                                    </td>
                                </tr>
                                @if(!empty($barang?->ciri_khusus))
                                    <tr>
                                        <td class="print-label">Ciri Khusus</td>
                                        <td class="print-val">{{ $barang->ciri_khusus }}</td>
                                    </tr>
                                @endif
                                @if(!empty($barang?->deskripsi))
                                    <tr>
                                        <td class="print-label">Deskripsi Fisik</td>
                                        <td class="print-val">{{ $barang->deskripsi }}</td>
                                    </tr>
                                @endif
                            </table>
                            @if(!empty($itemImageUrl))
                                <div class="print-item-thumb">
                                    <img src="{{ $itemImageUrl }}" alt="Foto Barang" onerror="this.style.display='none';">
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Kolom Kanan: Rincian Pemilik / Pengambil --}}
                <div class="print-card">
                    <div class="print-card-header">
                        <h2>II. RINCIAN PEMILIK / PENGAJU KLAIM</h2>
                    </div>
                    <div class="print-card-body">
                        <table class="print-table">
                            <tr>
                                <td class="print-label">Nama Pengambil</td>
                                <td class="print-val"><strong>{{ $user?->nama ?? $user?->name ?? 'Pemilik Klaim' }}</strong></td>
                            </tr>
                            <tr>
                                <td class="print-label">No. Telepon / WA</td>
                                <td class="print-val">{{ $user?->nomor_telepon ?? $user?->phone ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="print-label">Email Terdaftar</td>
                                <td class="print-val print-email-val">{{ $user?->email ?? '-' }}</td>
                            </tr>
                            @if(!empty($user?->nomor_induk) || !empty($user?->identitas))
                                <tr>
                                    <td class="print-label">No. Identitas / KTM</td>
                                    <td class="print-val">{{ $user?->nomor_induk ?? $user?->identitas }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td class="print-label">Bukti Kepemilikan</td>
                                <td class="print-val">{{ $klaim->bukti_kepemilikan ?: ($klaim->catatan ?: '-') }}</td>
                            </tr>
                            @if(!empty($klaim->bukti_ciri_khusus))
                                <tr>
                                    <td class="print-label">Ciri dari Pemilik</td>
                                    <td class="print-val">{{ $klaim->bukti_ciri_khusus }}</td>
                                </tr>
                            @endif
                        </table>
                    </div>
                </div>
            </div>

            {{-- 4. Informasi Posko Penyerahan Barang (Tanpa Header Pastel/Ungu) --}}
            <div class="print-card print-posko-card">
                <div class="print-card-header">
                    <h2>III. INFORMASI POSKO PENYERAHAN BARANG</h2>
                </div>
                <div class="print-card-body">
                    <div class="print-posko-grid">
                        <div>
                            <span class="print-meta-label">Nama Posko / Instansi:</span>
                            <strong class="print-meta-val">{{ $poskoName }}</strong>
                        </div>
                        <div>
                            <span class="print-meta-label">Wilayah / Kecamatan:</span>
                            <span class="print-meta-val">{{ $poskoWilayah }}</span>
                        </div>
                        <div>
                            <span class="print-meta-label">Alamat Lengkap Posko:</span>
                            <span class="print-meta-val">{{ $poskoAddress }}</span>
                        </div>
                        <div>
                            <span class="print-meta-label">Jam Layanan Operasional:</span>
                            <strong class="print-meta-val">{{ $jamLayanan }}</strong>
                        </div>
                        <div>
                            <span class="print-meta-label">Petugas Penanggung Jawab:</span>
                            <span class="print-meta-val">{{ $penanggungJawab }}</span>
                        </div>
                        <div>
                            <span class="print-meta-label">Kontak Layanan Posko:</span>
                            <span class="print-meta-val">{{ $contactRaw ?: '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 5. Instruksi Pengambilan (SOP Serah Terima Formal) --}}
            <div class="print-sop-box">
                <strong>Catatan Pengambilan:</strong> Wajib membawa kartu identitas asli (KTP/KTM) dan menunjukkan bukti tiket ini saat pengambilan barang di lokasi posko layanan.
            </div>

            {{-- 6. Titimangsa & Kolom Tanda Tangan Fisik Resmi --}}
            <div class="print-titimangsa-wrap">
                <div class="print-titimangsa-text">
                    Indramayu, {{ now()->translatedFormat('d F Y') }}
                </div>
            </div>

            <div class="print-signatures">
                <div class="print-signature-col">
                    <p class="print-sign-role">Penerima / Pemilik Barang,</p>
                    <div class="print-sign-space"></div>
                    <p class="print-sign-name">( {{ $user?->nama ?? $user?->name ?? '........................................' }} )</p>
                    <p class="print-sign-note">Tanda Tangan & Nama Terang</p>
                </div>
                <div class="print-signature-col">
                    <p class="print-sign-role">Petugas Penyerah Posko,</p>
                    <div class="print-sign-space"></div>
                    <p class="print-sign-name">( {{ $penanggungJawab ?: '........................................' }} )</p>
                    <p class="print-sign-note">Tanda Tangan & Cap Posko</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.body.classList.add('claim-ticket-page-mode');
});
function copyClaimCode(code) {
    if (!code) return;
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(code).then(showCopiedFeedback);
    } else {
        const tempInput = document.createElement('input');
        tempInput.value = code;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
        showCopiedFeedback();
    }
}

function showCopiedFeedback() {
    const feedbackText = document.getElementById('copyFeedbackText');
    if (!feedbackText) return;
    const oldText = feedbackText.innerText;
    feedbackText.innerText = 'Tersalin!';
    setTimeout(() => {
        feedbackText.innerText = oldText;
    }, 2000);
}
</script>
@endsection
