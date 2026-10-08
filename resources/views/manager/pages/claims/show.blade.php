@extends('manager::layouts.app')

@php
    $pageTitle = 'Detail Verifikasi Klaim - SiNemu';
    $activeMenu = 'claim-verifications';
    $hideSearch = true;
    $hideSidebar = true;
    $topbarBackUrl = manager_route('claim-verifications');
    $topbarBackLabel = 'Kembali ke Verifikasi Klaim';
    $managerRoleLabel = \App\Support\RoleLabels::manager();
    $managerRoleLabelLower = \App\Support\RoleLabels::managerLower();
    $hasPelaporEmail = filter_var($pelaporEmail, FILTER_VALIDATE_EMAIL) !== false;
    $emailContactHref = $hasPelaporEmail ? ('mailto:' . $pelaporEmail) : '#';
    $contactSubject = rawurlencode('Tindak lanjut verifikasi klaim #' . $klaim->id);
    $contactBody = rawurlencode('Halo ' . $pelaporNama . ', kami ingin menindaklanjuti pengajuan klaim Anda.');
    $hubungiHref = $hasPelaporEmail
        ? ('mailto:' . $pelaporEmail . '?subject=' . $contactSubject . '&body=' . $contactBody)
        : '#';
    $ringkasanStatus = match ($statusKey ?? 'menunggu') {
        'menunggu' => 'Klaim masih menunggu keputusan ' . $managerRoleLabelLower . '. Pastikan data pelapor dan kecocokan barang sudah tervalidasi.',
        'disetujui' => 'Klaim telah disetujui. Lanjutkan koordinasi penyerahan barang kepada pemilik.',
        'ditolak' => 'Klaim telah ditolak. Pastikan alasan penolakan terdokumentasi dengan jelas.',
        'selesai' => 'Proses klaim sudah selesai. Barang telah diserahkan kepada pihak yang berhak.',
        default => 'Status klaim belum terdefinisi.',
    };
    $langkahLanjutAdmin = match ($statusKey ?? 'menunggu') {
        'menunggu' => 'Verifikasi checklist wajib, lalu pilih Setujui atau Tolak berdasarkan bukti.',
        'disetujui' => 'Koordinasikan serah terima dan tandai selesai hanya setelah barang benar-benar diserahkan.',
        'ditolak' => 'Arsipkan alasan penolakan agar dapat ditinjau jika user mengajukan klaim ulang.',
        'selesai' => 'Simpan dokumentasi proses untuk audit operasional.',
        default => 'Lanjutkan proses sesuai kebijakan verifikasi.',
    };
    $catatanPengaju = trim((string) ($klaim->catatan ?? ''));
    $catatanLaporanHilang = trim((string) ($klaim->laporanHilang?->keterangan ?? ''));
    $catatanVerifikasiAdmin = trim((string) ($klaim->catatan_verifikasi_admin ?? ''));
    $alasanPenolakan = trim((string) ($klaim->alasan_penolakan ?? ''));
    $ciriKhususPengaju = trim((string) ($klaim->bukti_ciri_khusus ?? $klaim->laporanHilang?->ciri_khusus ?? ''));
    $buktiKepemilikanPengaju = trim((string) ($klaim->bukti_kepemilikan ?? $klaim->laporanHilang?->bukti_kepemilikan ?? ''));
    $detailIsiPengaju = trim((string) ($klaim->bukti_detail_isi ?? ''));
    $lokasiSpesifikPengaju = trim((string) ($klaim->bukti_lokasi_spesifik ?? ''));
    $waktuHilangPengaju = trim((string) ($klaim->bukti_waktu_hilang ?? ''));
    $skorValiditas = is_numeric($klaim->skor_validitas ?? null) ? (int) $klaim->skor_validitas : null;
    $hasilChecklist = (array) ($klaim->hasil_checklist ?? []);
    $checklistLabels = [
        'identitas_pelapor_valid' => 'Identitas pelapor valid',
        'detail_barang_valid' => 'Detail barang sesuai',
        'kronologi_valid' => 'Kronologi kejadian konsisten',
        'bukti_visual_valid' => 'Bukti visual meyakinkan',
        'kecocokan_data_laporan' => 'Data cocok dengan laporan hilang',
    ];
    $buktiFotoUrls = collect((array) ($klaim->bukti_foto ?? []))
        ->map(function ($path, $index) use ($klaim) {
            return is_string($path) && trim($path) !== ''
                ? route('claims.evidence.show', ['klaim' => $klaim->id, 'index' => $index])
                : null;
        })
        ->filter()
        ->values();

    // AI Matching variables
    $aiScore             = $aiScore ?? null;
    $aiRecommendation    = $aiRecommendation ?? null;
    $aiRecommendationLabel = $aiRecommendationLabel ?? 'Belum Dianalisis';
    $aiRecommendationClass = $aiRecommendationClass ?? 'ai-badge--none';
    $aiReasoning         = trim((string) ($aiReasoning ?? ''));
    $aiMatchedAt         = $aiMatchedAt ?? null;
    $aiModelUsed         = trim((string) ($aiModelUsed ?? ''));
    $canRunAiMatch       = !is_null($klaim->barang_id);
    $aiRunMatchRoute     = manager_route('claim-verifications.run-ai-analysis', $klaim->id);
    $pelaporTelepon      = $klaim->user?->nomor_telepon ?? null;
    $pelaporPhone        = trim((string) ($klaim->kontak ?: ($pelaporTelepon ?: '')));
    $pelaporPhoneClean   = preg_replace('/[^0-9]/', '', $pelaporPhone) ?? '';
    $pelaporWaUrl        = null;
    $ticketCode          = '#KLM-' . str_pad((string) $klaim->id, 5, '0', STR_PAD_LEFT);
    if ($pelaporPhoneClean !== '') {
        $cleanWa = $pelaporPhoneClean;
        if (str_starts_with($cleanWa, '0')) {
            $cleanWa = '62' . substr($cleanWa, 1);
        } elseif (!str_starts_with($cleanWa, '62')) {
            $cleanWa = '62' . $cleanWa;
        }
        $waText = rawurlencode('Halo ' . $pelaporNama . ', kami dari posko pengelola SiNemu terkait tiket klaim ' . $ticketCode . ' (' . $namaBarang . ').');
        $pelaporWaUrl = 'https://wa.me/' . $cleanWa . '?text=' . $waText;
    }
    $isAiHighMatch       = ($aiScore !== null && (int) $aiScore >= 75)
        || in_array(strtolower((string) ($aiRecommendation ?? '')), ['tinggi', 'high', 'sangat_cocok'], true);
    $aiGaugeColor        = match (true) {
        $aiScore === null     => '#94a3b8',
        (int) $aiScore >= 75 => '#16a34a',
        (int) $aiScore >= 50 => '#d97706',
        default              => '#64748b',
    };
@endphp

@section('page-content')
    <section class="claim-detail-page">
        <div class="claim-detail-header d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <div>
                <p class="claim-detail-breadcrumb">
                    <a href="{{ manager_route('claim-verifications') }}">Verifikasi Klaim</a>
                    <span>/</span>
                    <strong>Detail Klaim</strong>
                </p>
                <h1>Detail Verifikasi Klaim</h1>
                <div class="claim-detail-header-meta">
                    <span>Klaim #{{ $klaim->id }}</span>
                    <span>Dibuat {{ $klaim->created_at?->translatedFormat('d M Y, H:i') }} WIB</span>
                    <span>Diperbarui {{ $klaim->updated_at?->translatedFormat('d M Y, H:i') }} WIB</span>
                </div>
            </div>
        </div>

        {{-- Card Kode Tiket Resmi Pengambilan --}}
        <div class="claim-ticket-card-wrapper mb-3">
            <div class="claim-ticket-hero-card">
                <div class="claim-ticket-hero-main">
                    <div class="claim-ticket-tag-row">
                        <span class="claim-ticket-tag">
                            <iconify-icon icon="mdi:ticket-confirmation-outline" width="16"></iconify-icon>
                            KODE TIKET RESMI
                        </span>
                        @if(($statusKey ?? 'menunggu') === 'disetujui')
                            <span class="claim-ticket-ready-badge">
                                <iconify-icon icon="mdi:clock-check-outline" width="14"></iconify-icon>
                                Siap Diambil
                            </span>
                        @endif
                    </div>
                    <div class="claim-ticket-code-display-row">
                        <span class="claim-ticket-code-text" id="official-ticket-code">{{ $ticketCode }}</span>
                        <button type="button" class="btn-copy-ticket-inline" id="btn-copy-ticket-code" data-code="{{ $ticketCode }}" title="Salin kode tiket">
                            <iconify-icon icon="mdi:content-copy" width="15"></iconify-icon>
                            <span class="copy-label">Salin</span>
                        </button>
                    </div>
                    <p class="claim-ticket-instruction-text">
                        <iconify-icon icon="mdi:information-outline" width="15" style="vertical-align: -2px; color: #2563eb;"></iconify-icon>
                        Cocokkan kode ini dengan tiket fisik/digital yang ditunjukkan oleh pengambil barang.
                    </p>
                </div>
                @if(($statusKey ?? 'menunggu') === 'disetujui')
                    <div class="claim-ticket-hero-cta">
                        <button type="button" class="btn-handover-primary btn-handover-prominent" id="hero-handover-trigger-btn">
                            <iconify-icon icon="mdi:handshake-outline" width="20"></iconify-icon>
                            <span>Konfirmasi Serah Terima Barang</span>
                        </button>
                    </div>
                @endif
            </div>
        </div>

        @if(($statusKey ?? 'menunggu') === 'disetujui')
            <div class="claim-handover-status-banner mb-3">
                <div class="claim-handover-banner-icon">
                    <iconify-icon icon="mdi:information-outline" width="22"></iconify-icon>
                </div>
                <div class="claim-handover-banner-text">
                    <h4 class="claim-handover-banner-title">Klaim telah disetujui. Menunggu serah terima fisik barang kepada pemilik.</h4>
                    <p class="claim-handover-banner-desc">Barang temuan belum diserahkan ke pemilik. Pastikan penerima menunjukkan kartu identitas asli (KTP/KTM) dan tiket pengambilan resmi sebelum melakukan konfirmasi serah terima fisik.</p>
                </div>
            </div>
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
                        <span class="claim-item-visual-label">Foto Barang</span>
                        <img src="{{ $fotoUrl }}" alt="{{ $namaBarang }}" loading="lazy" decoding="async">
                    </div>
                    <div class="claim-item-info">
                        {{-- Ringkasan Identitas Pengaju & Panduan Kroscek Fisik --}}
                        <article class="claim-requester-crosscheck-card">
                            <div class="crosscheck-card-header">
                                <div class="crosscheck-header-title">
                                    <iconify-icon icon="mdi:account-check-outline" width="22" style="color: #2563eb;"></iconify-icon>
                                    <div>
                                        <small class="crosscheck-label-badge">IDENTITAS PENGAJU &amp; PANDUAN KROSCEK FISIK</small>
                                        <h3 class="crosscheck-user-name">{{ $pelaporNama }}</h3>
                                    </div>
                                </div>
                                <div class="crosscheck-contact-links">
                                    <a href="{{ $emailContactHref }}" class="crosscheck-pill {{ $hasPelaporEmail ? '' : 'is-disabled' }}" title="Kirim Email">
                                        <iconify-icon icon="mdi:email-outline" width="14"></iconify-icon>
                                        <span>{{ $pelaporEmail }}</span>
                                    </a>
                                    @if($pelaporPhone !== '')
                                        @if($pelaporWaUrl)
                                            <a href="{{ $pelaporWaUrl }}" target="_blank" rel="noopener noreferrer" class="crosscheck-pill crosscheck-pill-wa" title="Hubungi via WhatsApp">
                                                <iconify-icon icon="mdi:whatsapp" width="14"></iconify-icon>
                                                <span>{{ $pelaporPhone }}</span>
                                            </a>
                                        @else
                                            <span class="crosscheck-pill">
                                                <iconify-icon icon="mdi:phone-outline" width="14"></iconify-icon>
                                                <span>{{ $pelaporPhone }}</span>
                                            </span>
                                        @endif
                                    @endif
                                </div>
                            </div>
                            <div class="crosscheck-card-body">
                                <div class="crosscheck-grid-details">
                                    <div class="crosscheck-point-box">
                                        <span class="crosscheck-point-title">
                                            <iconify-icon icon="mdi:tag-outline" width="15" style="color: #0284c7;"></iconify-icon>
                                            Ciri Khusus Menurut Pengaju
                                        </span>
                                        <p class="crosscheck-point-desc">{{ $ciriKhususPengaju !== '' ? $ciriKhususPengaju : 'Tidak dicantumkan oleh pengaju.' }}</p>
                                    </div>
                                    <div class="crosscheck-point-box">
                                        <span class="crosscheck-point-title">
                                            <iconify-icon icon="mdi:shield-check-outline" width="15" style="color: #16a34a;"></iconify-icon>
                                            Bukti Kepemilikan yang Diajukan
                                        </span>
                                        <p class="crosscheck-point-desc">{{ $buktiKepemilikanPengaju !== '' ? $buktiKepemilikanPengaju : 'Tidak ada keterangan bukti khusus.' }}</p>
                                    </div>
                                </div>
                                @if($detailIsiPengaju !== '')
                                    <div class="crosscheck-point-box mt-2">
                                        <span class="crosscheck-point-title">
                                            <iconify-icon icon="mdi:format-list-bulleted" width="15" style="color: #6366f1;"></iconify-icon>
                                            Detail Isi / Kelengkapan Barang
                                        </span>
                                        <p class="crosscheck-point-desc">{{ $detailIsiPengaju }}</p>
                                    </div>
                                @endif
                            </div>
                        </article>

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
                                <small>ID Klaim</small>
                                <strong>#{{ $klaim->id }}</strong>
                            </article>
                        </div>
                        <article class="claim-description-box">
                            <h3>Deskripsi</h3>
                            <p>{{ $deskripsi }}</p>
                        </article>
                        <article class="claim-verification-summary">
                            <h3>Ringkasan Verifikasi</h3>
                            <p>{{ $ringkasanStatus }}</p>
                        </article>
                        <article class="claim-decision-guide">
                            <h3>Panduan Keputusan {{ $managerRoleLabel }}</h3>
                            <div class="claim-decision-guide-grid">
                                <div class="claim-decision-guide-item approve">
                                    <strong>Setujui Klaim Jika:</strong>
                                    <ul>
                                        <li>Skor validitas minimal 75.</li>
                                        <li>Poin kritikal (detail barang, kronologi, bukti visual) lolos.</li>
                                        <li>Bukti kepemilikan konsisten dengan laporan hilang dan barang temuan.</li>
                                    </ul>
                                </div>
                                <div class="claim-decision-guide-item reject">
                                    <strong>Tolak Klaim Jika:</strong>
                                    <ul>
                                        <li>Data klaim bertentangan dengan laporan/barang temuan.</li>
                                        <li>Bukti terlalu umum, lemah, atau tidak relevan.</li>
                                        <li>Nomor seri/ciri unik tidak sesuai dengan barang terkait.</li>
                                    </ul>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </article>

            <aside class="claim-side-column">
                <article class="report-card claim-side-card claim-panel-status">
                    <header><h2>Status & Riwayat</h2></header>
                    <div class="claim-side-body">
                        <div class="claim-status-current">
                            <small>Status Saat Ini</small>
                            <span class="status-chip {{ $statusClass }}">{{ strtoupper($statusLabel) }}</span>
                        </div>
                        <div class="claim-status-current">
                            <small>Status Barang Terkait</small>
                            <span class="status-chip {{ $statusBarangClass }}">{{ strtoupper($statusBarangLabel) }}</span>
                        </div>
                        <div class="claim-note-box claim-note-next-step">
                            <small>Langkah Lanjut {{ $managerRoleLabel }}</small>
                            <p>{{ $langkahLanjutAdmin }}</p>
                        </div>
                        <ul class="claim-timeline">
                            <li>
                                <strong>Klaim diajukan</strong>
                                <span>{{ $klaim->created_at?->translatedFormat('d M Y, H:i') }} WIB</span>
                            </li>
                            <li>
                                <strong>Status klaim saat ini</strong>
                                <span>{{ strtoupper($statusLabel) }}</span>
                            </li>
                            <li>
                                <strong>Terakhir diperbarui</strong>
                                <span>{{ $klaim->updated_at?->translatedFormat('d M Y, H:i') }} WIB</span>
                            </li>
                        </ul>
                        @if($catatanPengaju !== '')
                            <div class="claim-note-box claim-note-requester">
                                <small>Catatan Klaim Pengaju</small>
                                <p>{{ $catatanPengaju }}</p>
                            </div>
                        @endif
                        @if($catatanLaporanHilang !== '')
                            <div class="claim-note-box claim-note-requester">
                                <small>Catatan di Laporan Hilang</small>
                                <p>{{ $catatanLaporanHilang }}</p>
                            </div>
                        @endif
                        @if($ciriKhususPengaju !== '')
                            <div class="claim-note-box claim-note-requester">
                                <small>Ciri Unik Menurut Pengaju</small>
                                <p>{{ $ciriKhususPengaju }}</p>
                            </div>
                        @endif
                        @if($detailIsiPengaju !== '')
                            <div class="claim-note-box claim-note-requester">
                                <small>Detail Isi / Kondisi</small>
                                <p>{{ $detailIsiPengaju }}</p>
                            </div>
                        @endif
                        @if($lokasiSpesifikPengaju !== '' || $waktuHilangPengaju !== '')
                            <div class="claim-note-box claim-note-requester">
                                <small>Lokasi & Waktu Hilang Versi Pengaju</small>
                                <p>
                                    {{ $lokasiSpesifikPengaju !== '' ? $lokasiSpesifikPengaju : '-' }}
                                    @if($waktuHilangPengaju !== '')
                                        ({{ $waktuHilangPengaju }})
                                    @endif
                                </p>
                            </div>
                        @endif
                        @if($buktiKepemilikanPengaju !== '')
                            <div class="claim-note-box claim-note-requester">
                                <small>Bukti Kepemilikan</small>
                                <p>{{ $buktiKepemilikanPengaju }}</p>
                            </div>
                        @endif
                        @if($buktiFotoUrls->isNotEmpty())
                            <div class="claim-proof-gallery">
                                <small>Foto Bukti Kepemilikan</small>
                                <div class="claim-proof-grid">
                                    @foreach($buktiFotoUrls as $proofUrl)
                                        <a href="{{ $proofUrl }}" target="_blank" rel="noopener noreferrer" class="claim-proof-item" title="Lihat foto bukti kepemilikan (Buka di tab baru)">
                                            <img src="{{ $proofUrl }}" alt="Bukti kepemilikan klaim #{{ $klaim->id }}" loading="lazy" decoding="async">
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        @if(!is_null($skorValiditas))
                            <div class="claim-note-box">
                                <small>Skor Validitas Klaim</small>
                                <p>{{ $skorValiditas }} / 100</p>
                            </div>
                        @endif
                        @if($hasilChecklist !== [])
                            <div class="claim-note-box">
                                <small>Checklist Verifikasi {{ $managerRoleLabel }}</small>
                                <p>
                                    @foreach($checklistLabels as $checklistKey => $checklistLabel)
                                        {{ $checklistLabel }}: {{ (($hasilChecklist[$checklistKey] ?? false) ? 'Ya' : 'Tidak') }}@if(!$loop->last)<br>@endif
                                    @endforeach
                                </p>
                            </div>
                        @endif
                        @if($catatanVerifikasiAdmin !== '')
                            <div class="claim-note-box">
                                <small>Catatan Verifikasi {{ $managerRoleLabel }}</small>
                                <p>{{ $catatanVerifikasiAdmin }}</p>
                            </div>
                        @endif
                        @if($alasanPenolakan !== '')
                            <div class="claim-note-box">
                                <small>Alasan Penolakan</small>
                                <p>{{ $alasanPenolakan }}</p>
                            </div>
                        @endif
                    </div>
                </article>

                {{-- ═══════════════════════════════════════════════════════════════
                     WORKSPACE SEIMBANG 50:50 (KOLOM KIRI vs KOLOM KANAN)
                     ═══════════════════════════════════════════════════════════════ --}}
                <div class="claim-balanced-workspace">
                    {{-- ── KOLOM KIRI (Informasi Pengaju & Analisis AI) ── --}}
                    <div class="claim-side-col-left">
                        {{-- 1. Card "Informasi Pengaju" --}}
                        <article class="report-card claim-side-card claim-panel-requester">
                            <header>
                                <h2>
                                    <svg viewBox="0 0 20 20" fill="currentColor" class="panel-header-svg" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                    </svg>
                                    Informasi Pengaju
                                </h2>
                            </header>
                            <div class="claim-side-body requester-card-body">
                                <div class="requester-profile-row">
                                    <div class="requester-avatar" aria-hidden="true">
                                        {{ strtoupper(mb_substr($pelaporNama, 0, 1)) }}
                                    </div>
                                    <div class="requester-info">
                                        <div class="requester-name-badge">
                                            <strong>{{ $pelaporNama }}</strong>
                                            <span class="requester-role-badge">Pengaju</span>
                                        </div>
                                        <div class="requester-meta-list">
                                            <div class="requester-meta-item">
                                                <svg viewBox="0 0 20 20" fill="currentColor" class="meta-icon" aria-hidden="true">
                                                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                                </svg>
                                                <span>{{ $pelaporEmail }}</span>
                                            </div>
                                            @if(!empty($pelaporTelepon))
                                                <div class="requester-meta-item">
                                                    <svg viewBox="0 0 20 20" fill="currentColor" class="meta-icon" aria-hidden="true">
                                                        <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                                                    </svg>
                                                    <span>{{ $pelaporTelepon }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="claim-contact-actions">
                                    <a href="{{ $hubungiHref }}" class="filter-btn {{ $hasPelaporEmail ? '' : 'is-disabled' }}" title="Kirim email tindak lanjut">
                                        <svg viewBox="0 0 20 20" fill="currentColor" class="btn-icon" aria-hidden="true">
                                            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                                        </svg>
                                        Hubungi
                                    </a>
                                    <a href="{{ $emailContactHref }}" class="filter-btn {{ $hasPelaporEmail ? '' : 'is-disabled' }}" title="Kirim email langsung">
                                        <svg viewBox="0 0 20 20" fill="currentColor" class="btn-icon" aria-hidden="true">
                                            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                        </svg>
                                        Email
                                    </a>
                                </div>
                            </div>
                        </article>

                        {{-- 2. Card "Analisis AI" (Equal Height & Balanced Layout) --}}
                        <article class="report-card claim-side-card claim-panel-ai" id="ai-matching-panel">
                            <header>
                                <h2>
                                    <svg viewBox="0 0 20 20" fill="currentColor" class="panel-header-svg" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" />
                                    </svg>
                                    Analisis AI
                                </h2>
                            </header>
                            <div class="claim-side-body ai-card-body">
                                <div class="ai-card-content">
                                    {{-- Sisi Atas: Gauge Skor di Kiri & Kotak Penjelasan AI (Flex-Grow) di Kanan --}}
                                    <div class="ai-card-top">
                                        <div class="ai-score-column">
                                            <div class="ai-score-gauge" style="--ai-score: {{ $aiScore ?? 0 }}; --ai-gauge-color: {{ $aiGaugeColor }};">
                                                <svg viewBox="0 0 120 70" class="ai-gauge-svg" aria-hidden="true">
                                                    <path d="M10,60 A50,50 0 0,1 110,60" fill="none" stroke="#e2e8f0" stroke-width="10" stroke-linecap="round"/>
                                                    <path d="M10,60 A50,50 0 0,1 110,60" fill="none"
                                                          id="ai-gauge-path"
                                                          stroke="var(--ai-gauge-color, #16a34a)"
                                                          stroke-width="10"
                                                          stroke-linecap="round"
                                                          stroke-dasharray="157"
                                                          stroke-dashoffset="{{ $aiScore !== null ? round(157 - ($aiScore / 100) * 157) : 157 }}"
                                                          style="transition: stroke-dashoffset 1s ease, stroke 0.4s ease"/>
                                                </svg>
                                                <div class="ai-gauge-value">
                                                    <strong id="ai-score-display">{{ $aiScore !== null ? $aiScore : '–' }}</strong>
                                                    <small id="ai-score-unit">{{ $aiScore !== null ? '/100' : 'belum' }}</small>
                                                </div>
                                            </div>
                                            <div class="ai-score-meta">
                                                <span class="ai-badge {{ $aiRecommendationClass }}" id="ai-recommendation-badge">
                                                    {{ strtoupper($aiRecommendationLabel) }}
                                                </span>
                                                <small class="ai-matched-at" id="ai-matched-at-display">
                                                    {{ $aiMatchedAt ? ('Dianalisis ' . $aiMatchedAt->translatedFormat('d M Y, H:i') . ' WIB') : '' }}
                                                </small>
                                                <small class="ai-model-label" id="ai-model-display">
                                                    {{ $aiModelUsed !== '' ? ('Model: ' . $aiModelUsed) : '' }}
                                                </small>
                                            </div>
                                        </div>

                                        {{-- Area Penjelasan AI (Otomatis Melebar & Mengisi Ruang Tengah) --}}
                                        <div class="claim-note-box claim-note-ai" id="ai-reasoning-box">
                                            <small class="claim-note-title">Keterangan Analisis AI</small>
                                            <p id="ai-reasoning-text" class="{{ $aiReasoning === '' ? 'text-muted' : '' }}">
                                                {{ $aiReasoning !== '' ? $aiReasoning : 'Analisis AI belum dijalankan. Klik tombol di bawah untuk mencocokkan data secara instan.' }}
                                            </p>
                                        </div>
                                    </div>

                                    {{-- Sisi Bawah: Tombol Analisis AI (Card Footer) --}}
                                    <div class="ai-card-footer">
                                        @if($canRunAiMatch)
                                            <form id="ai-match-form"
                                                  method="POST"
                                                  action="{{ $aiRunMatchRoute }}"
                                                  class="ai-match-form-footer">
                                                @csrf
                                                <button type="submit"
                                                        id="ai-match-btn"
                                                        class="filter-btn ai-match-trigger-btn"
                                                        title="{{ $aiScore !== null ? 'Jalankan ulang analisis AI untuk klaim ini' : 'Jalankan analisis AI sekarang' }}">
                                                    <svg viewBox="0 0 20 20" fill="currentColor" class="btn-svg-icon" id="ai-btn-icon" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" />
                                                    </svg>
                                                    <span id="ai-btn-spinner" class="ai-spinner" style="display:none" aria-hidden="true"></span>
                                                    <span id="ai-btn-text">{{ $aiScore !== null ? 'Analisis AI Ulang' : 'Jalankan Analisis AI Sekarang' }}</span>
                                                </button>
                                            </form>
                                            <p class="ai-panel-hint" id="ai-panel-hint">
                                                {{ $klaim->laporan_hilang_id ? 'Mencocokkan data laporan barang hilang dengan barang temuan via Gemini AI.' : 'Mencocokkan bukti kepemilikan dan ciri khusus klaim dengan barang temuan via Gemini AI.' }}
                                            </p>
                                        @else
                                            <p class="ai-panel-hint text-muted">Data barang temuan diperlukan untuk analisis AI.</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>

                    {{-- ── KOLOM KANAN (Form Verifikasi Klaim) ── --}}
                    <div class="claim-side-col-right">
                        @if(($statusKey ?? 'menunggu') === 'menunggu')
                            <article class="report-card claim-side-card claim-panel-verification">
                                <header>
                                    <h2>
                                        <svg viewBox="0 0 20 20" fill="currentColor" class="panel-header-svg" aria-hidden="true">
                                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm9.707 5.707a1 1 0 00-1.414-1.414L9 12.586l-1.293-1.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        Form Verifikasi Klaim
                                    </h2>
                                </header>
                                <div class="claim-side-body">
                                    <form method="POST"
                                          action="{{ manager_route('claim-verifications.approve', $klaim->id) }}"
                                          class="claim-verification-form-compact"
                                          data-confirm-delete
                                          data-claim-verification-form>
                                        @csrf

                                        <div class="claim-verification-rule-compact">
                                            <strong>Aturan Otomatis Persetujuan</strong>
                                            <span>Klaim hanya bisa disetujui jika skor minimal 75 dan semua poin bernilai "Ya".</span>
                                        </div>

                                        <div class="claim-checklist-compact-list">
                                            <div class="checklist-header-row">
                                                <div class="checklist-header-title-wrap">
                                                    <small class="checklist-header-label">Checklist Verifikasi (Wajib)</small>
                                                    <span class="checklist-header-badge">5 Poin</span>
                                                </div>
                                                <div class="checklist-quick-actions">
                                                    <button type="button"
                                                            class="checklist-quick-btn checklist-quick-btn--autofill"
                                                            id="btn-fill-ai"
                                                            title="Isi otomatis checklist via AI"
                                                            data-ai-score="{{ $aiScore ?? '' }}">
                                                        <svg viewBox="0 0 20 20" fill="currentColor" class="btn-svg-icon" aria-hidden="true">
                                                            <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" />
                                                        </svg>
                                                        <span>Isi via AI</span>
                                                    </button>
                                                    <button type="button"
                                                            class="checklist-quick-btn checklist-quick-btn--reset"
                                                            id="btn-reset-checklist"
                                                            title="Kosongkan semua pilihan checklist">
                                                        <svg viewBox="0 0 20 20" fill="currentColor" class="btn-svg-icon" aria-hidden="true">
                                                            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
                                                        </svg>
                                                        <span>Reset</span>
                                                    </button>
                                                </div>
                                            </div>

                                            @if($isAiHighMatch)
                                                <div class="checklist-ai-autofill-banner" id="checklist-ai-banner" role="status">
                                                    <svg viewBox="0 0 20 20" fill="currentColor" class="banner-svg-icon" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                                    </svg>
                                                    <span class="autofill-banner-text" id="checklist-ai-banner-text">
                                                        Checklist terisi otomatis berdasarkan rekomendasi AI (<strong>{{ $aiScore ?? 100 }}%</strong>).
                                                    </span>
                                                </div>
                                            @else
                                                <div class="checklist-ai-autofill-banner is-manual" id="checklist-ai-banner" role="status" style="display: none;">
                                                    <svg viewBox="0 0 20 20" fill="currentColor" class="banner-svg-icon" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                                    </svg>
                                                    <span class="autofill-banner-text" id="checklist-ai-banner-text">
                                                        Checklist terisi otomatis berdasarkan rekomendasi AI.
                                                    </span>
                                                </div>
                                            @endif

                                            <div class="claim-verification-grid claim-verification-list-stack">
                                                @foreach($checklistLabels as $checklistKey => $checklistLabel)
                                                    @php
                                                        $oldVal = old($checklistKey);
                                                        if ($oldVal !== null) {
                                                            $defaultVal = (string) $oldVal;
                                                        } elseif ($isAiHighMatch) {
                                                            $defaultVal = '1';
                                                        } else {
                                                            $defaultVal = null;
                                                        }
                                                    @endphp
                                                    <div class="claim-checklist-row">
                                                        <span class="checklist-field-label">{{ $checklistLabel }}</span>
                                                        <div class="checklist-pill-group" role="radiogroup" aria-label="{{ $checklistLabel }}">
                                                            <label class="checklist-pill-btn {{ $defaultVal === '1' ? 'is-active is-yes' : '' }}">
                                                                <input type="radio"
                                                                       name="{{ $checklistKey }}"
                                                                       value="1"
                                                                       class="checklist-radio-input"
                                                                       @checked($defaultVal === '1')
                                                                       required>
                                                                <span class="pill-text">Ya</span>
                                                            </label>
                                                            <label class="checklist-pill-btn {{ $defaultVal === '0' ? 'is-active is-no' : '' }}">
                                                                <input type="radio"
                                                                       name="{{ $checklistKey }}"
                                                                       value="0"
                                                                       class="checklist-radio-input"
                                                                       @checked($defaultVal === '0')
                                                                       required>
                                                                <span class="pill-text">Tidak</span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        <div class="claim-field-compact">
                                            <small>Catatan Verifikasi {{ $managerRoleLabel }}</small>
                                            <textarea name="catatan_verifikasi_admin" class="form-control" rows="2" maxlength="2000" placeholder="Tambahkan catatan validasi jika diperlukan.">{{ old('catatan_verifikasi_admin') }}</textarea>
                                        </div>

                                        <div class="claim-field-compact">
                                            <small>Alasan Penolakan (Wajib jika ditolak)</small>
                                            <textarea name="alasan_penolakan" class="form-control" rows="2" maxlength="2000" placeholder="Isi alasan jika Anda akan menolak klaim.">{{ old('alasan_penolakan') }}</textarea>
                                        </div>

                                        <div class="claim-verification-actions-compact">
                                            <button type="submit"
                                                formaction="{{ manager_route('claim-verifications.reject', $klaim->id) }}"
                                                data-confirm-title="Konfirmasi Tolak Klaim"
                                                data-confirm-message="Tolak klaim ini? Pastikan alasan penolakan sudah diisi dengan jelas."
                                                data-confirm-submit-label="Ya, Tolak"
                                                data-confirm-submit-variant="danger"
                                                class="claim-action-btn danger">
                                                Tolak Klaim
                                            </button>
                                            <button type="submit"
                                                formaction="{{ manager_route('claim-verifications.approve', $klaim->id) }}"
                                                data-confirm-title="Konfirmasi Setujui Klaim"
                                                data-confirm-message="Setujui klaim ini? Status klaim akan berubah menjadi disetujui."
                                                data-confirm-submit-label="Ya, Setujui"
                                                data-confirm-submit-variant="primary"
                                                class="claim-action-btn success"
                                                data-approve-btn>
                                                Setujui Klaim
                                            </button>
                                        </div>
                                        <p class="claim-validation-hint" data-approve-hint aria-live="polite"></p>
                                    </form>
                                </div>
                            </article>
                        @endif

                        @if(($statusKey ?? 'menunggu') === 'disetujui')
                            <article class="report-card claim-side-card claim-panel-verification">
                                <header>
                                    <h2>
                                        <iconify-icon icon="mdi:clipboard-check-outline" width="18" style="color: #1e3a8a; vertical-align: middle; margin-right: 6px;"></iconify-icon>
                                        Tindakan Pengelola
                                    </h2>
                                </header>
                                <div class="claim-side-body">
                                    <div class="handover-side-status-box" style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #16a34a; border-radius: 8px; padding: 14px 16px;">
                                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                            <span style="font-size: 0.8rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.03em;">Status Klaim</span>
                                            <span style="display: inline-flex; align-items: center; gap: 4px; background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; border-radius: 999px; padding: 2px 10px; font-size: 0.75rem; font-weight: 700;">
                                                <iconify-icon icon="mdi:check-circle" width="13"></iconify-icon>
                                                Disetujui
                                            </span>
                                        </div>
                                        <p style="margin: 0; color: #334155; font-size: 0.825rem; line-height: 1.5;">
                                            Menunggu serah terima fisik barang. Gunakan tombol pada kartu <strong>Kode Tiket</strong> di atas untuk menyelesaikan berita acara penyerahan.
                                        </p>
                                    </div>
                                </div>
                            </article>
                        @endif

                        @if(($statusKey ?? 'menunggu') === 'selesai')
                            <article class="report-card claim-side-card claim-panel-verification">
                                <header>
                                    <h2>
                                        <svg viewBox="0 0 20 20" fill="currentColor" class="panel-header-svg text-success" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        Data Serah Terima
                                    </h2>
                                </header>
                                <div class="claim-side-body">
                                    <div class="handover-summary-box" style="font-size: 0.875rem; color: #334155;">
                                        <div class="mb-2">
                                            <small class="text-muted d-block" style="font-size: 0.75rem;">Penerima Barang:</small>
                                            <strong style="color: #0f172a;">{{ $klaim->nama_penerima ?: $pelaporNama }}</strong>
                                        </div>
                                        @if(!empty($klaim->nomor_identitas_penerima))
                                            <div class="mb-2">
                                                <small class="text-muted d-block" style="font-size: 0.75rem;">No. Identitas:</small>
                                                <span>{{ $klaim->nomor_identitas_penerima }}</span>
                                            </div>
                                        @endif
                                        @if(!empty($klaim->catatan_serah_terima))
                                            <div class="mb-2">
                                                <small class="text-muted d-block" style="font-size: 0.75rem;">Catatan Penyerahan:</small>
                                                <span>{{ $klaim->catatan_serah_terima }}</span>
                                            </div>
                                        @endif
                                        @if(!empty($klaim->diserahkan_at))
                                            <div class="mb-2">
                                                <small class="text-muted d-block" style="font-size: 0.75rem;">Waktu Penyerahan:</small>
                                                <span>{{ $klaim->diserahkan_at->translatedFormat('d M Y, H:i') }} WIB</span>
                                            </div>
                                        @endif
                                        @if(!empty($klaim->foto_serah_terima))
                                            <div class="mt-2">
                                                <small class="text-muted d-block mb-1" style="font-size: 0.75rem;">Dokumentasi Foto:</small>
                                                <a href="{{ asset('storage/' . $klaim->foto_serah_terima) }}" target="_blank" rel="noopener noreferrer">
                                                    <img src="{{ asset('storage/' . $klaim->foto_serah_terima) }}" alt="Foto Dokumentasi Serah Terima" style="max-width: 100%; border-radius: 6px; border: 1px solid #e2e8f0; max-height: 140px; object-fit: cover;">
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @endif
                    </div>
                </div>
            </aside>
        </section>
    </section>

    @php
        /** @var \Illuminate\Support\ViewErrorBag $errors */
        $shouldOpenHandoverModal = $errors->has('kode_tiket') || $errors->has('nama_penerima') || $errors->has('nomor_identitas_penerima');
        $expectedTicketNumber = str_pad((string) $klaim->id, 5, '0', STR_PAD_LEFT);
    @endphp
    <div class="handover-modal-backdrop"
         id="handover-modal-backdrop"
         data-auto-open="{{ $shouldOpenHandoverModal ? 'true' : 'false' }}"
         data-expected-ticket="{{ $ticketCode }}"
         data-expected-number="{{ $expectedTicketNumber }}"
         data-claim-id="{{ $klaim->id }}"
         style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 1050; align-items: center; justify-content: center; padding: 16px;">
        <div class="handover-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="handover-modal-title" style="background: #ffffff; width: 100%; max-width: 520px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); overflow: hidden;">
            <header style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; background: #f8fafc;">
                <div>
                    <h3 id="handover-modal-title" style="margin: 0; font-size: 1.1rem; font-weight: 600; color: #0f172a;">Konfirmasi Serah Terima Barang</h3>
                    <small style="color: #64748b; font-size: 0.8rem;">Catat penyerahan fisik barang temuan kepada pemilik</small>
                </div>
                <button type="button" id="close-handover-modal-btn" style="background: none; border: none; cursor: pointer; color: #94a3b8; padding: 4px; border-radius: 6px;" aria-label="Tutup modal">
                    <svg viewBox="0 0 20 20" fill="currentColor" style="width: 20px; height: 20px;">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </header>

            <form method="POST" action="{{ manager_route('claim-verifications.complete', $klaim->id) }}" enctype="multipart/form-data" id="handover-form">
                @csrf
                <div style="padding: 20px; display: flex; flex-direction: column; gap: 14px;">
                    {{-- Kode Tiket Pengambilan (Wajib) --}}
                    <div>
                        <label for="handover_kode_tiket" style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; font-weight: 600; color: #1e293b; margin-bottom: 4px;">
                            <span>Kode Tiket Pengambilan <span style="color: #dc2626;">*</span></span>
                            <span style="font-size: 0.75rem; color: #2563eb; font-weight: 600; font-family: monospace;">{{ $ticketCode }}</span>
                        </label>
                        <input type="text"
                               id="handover_kode_tiket"
                               name="kode_tiket"
                               class="form-control"
                               required
                               autocomplete="off"
                               placeholder="Contoh: KLM-00005 atau #KLM-00005"
                               value="{{ old('kode_tiket') }}"
                               style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; font-family: monospace; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase;">
                        <small style="color: #64748b; font-size: 0.75rem; display: block; margin-top: 3px;">Ketik kode tiket yang tertera pada lembar bukti pengambilan milik penerima.</small>
                        <div id="handover-ticket-feedback" style="display: none; font-size: 0.775rem; color: #dc2626; margin-top: 4px; font-weight: 500;"></div>
                        @error('kode_tiket')
                            <div style="font-size: 0.775rem; color: #dc2626; margin-top: 4px; font-weight: 500;">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label for="handover_nama_penerima" style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px;">Nama Pengambil / Penerima</label>
                        <input type="text" id="handover_nama_penerima" name="nama_penerima" class="form-control" value="{{ old('nama_penerima', $pelaporNama) }}" placeholder="Nama lengkap penerima" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.875rem;">
                        <small style="color: #64748b; font-size: 0.75rem;">Default terisi sesuai nama pengaju klaim.</small>
                    </div>

                    <div>
                        <label for="handover_nomor_identitas" style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px;">Nomor Identitas (KTP / KTM / SIM)</label>
                        <input type="text" id="handover_nomor_identitas" name="nomor_identitas_penerima" class="form-control" placeholder="Nomor KTP, KTM, atau SIM penerima" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.875rem;">
                    </div>

                    <div>
                        <label for="handover_catatan" style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px;">Catatan Serah Terima</label>
                        <textarea id="handover_catatan" name="catatan_serah_terima" rows="2" class="form-control" placeholder="Contoh: Barang diserahkan dalam kondisi baik beserta kelengkapannya." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.875rem;"></textarea>
                    </div>

                    <div>
                        <label for="handover_foto" style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px;">Foto Dokumentasi Serah Terima (Opsional)</label>
                        <input type="file" id="handover_foto" name="foto_serah_terima" accept="image/*" class="form-control" style="width: 100%; padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem;">
                        <small style="color: #64748b; font-size: 0.75rem;">Unggah foto dokumentasi saat penyerahan barang (JPG, PNG, atau WEBP, maks 5MB).</small>
                    </div>
                </div>

                <footer style="padding: 12px 20px; border-top: 1px solid #e2e8f0; background: #f8fafc; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" id="cancel-handover-modal-btn" class="claim-action-btn neutral" style="padding: 8px 16px; font-size: 0.875rem; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; border-radius: 6px; cursor: pointer;">
                        Batal
                    </button>
                    <button type="submit" class="btn-handover-primary" id="submit-handover-btn" style="padding: 8px 18px; font-size: 0.875rem; border-radius: 6px;">
                        <iconify-icon icon="mdi:check-circle-outline" width="18"></iconify-icon>
                        <span>Konfirmasi & Selesaikan</span>
                    </button>
                </footer>
            </form>
        </div>
    </div>

    <style>
        .ai-spinner {
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: ai-spin 0.8s linear infinite;
            display: inline-block;
            flex-shrink: 0;
            vertical-align: middle;
        }
        @keyframes ai-spin {
            to { transform: rotate(360deg); }
        }
        .ai-match-trigger-btn:disabled {
            opacity: 0.75;
            cursor: not-allowed;
            pointer-events: none;
        }
        .ai-panel-hint.text-success {
            color: #16a34a !important;
            font-weight: 500;
        }
        .ai-panel-hint.text-danger {
            color: #dc2626 !important;
            font-weight: 500;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const aiForm = document.getElementById('ai-match-form');
            const aiBtn = document.getElementById('ai-match-btn');
            const aiBtnText = document.getElementById('ai-btn-text');
            const aiBtnIcon = document.getElementById('ai-btn-icon');
            const aiBtnSpinner = document.getElementById('ai-btn-spinner');
            const aiPanelHint = document.getElementById('ai-panel-hint');
            const aiScoreGauge = document.querySelector('.ai-score-gauge');
            const aiGaugePath = document.getElementById('ai-gauge-path');
            const aiScoreDisplay = document.getElementById('ai-score-display');
            const aiScoreUnit = document.getElementById('ai-score-unit');
            const aiBadge = document.getElementById('ai-recommendation-badge');
            const aiReasoningText = document.getElementById('ai-reasoning-text');
            const aiMatchedAtDisplay = document.getElementById('ai-matched-at-display');
            const aiModelDisplay = document.getElementById('ai-model-display');

            if (!aiForm || !aiBtn) return;

            aiForm.addEventListener('submit', async function (e) {
                e.preventDefault();

                aiBtn.disabled = true;
                if (aiBtnIcon) aiBtnIcon.style.display = 'none';
                if (aiBtnSpinner) aiBtnSpinner.style.display = 'inline-block';
                if (aiBtnText) aiBtnText.textContent = 'Sedang Menganalisis...';
                if (aiPanelHint) {
                    aiPanelHint.textContent = 'Menghubungi Gemini AI dan menganalisis kecocokan data...';
                    aiPanelHint.classList.remove('text-danger', 'text-success');
                }

                try {
                    const formData = new FormData(aiForm);
                    const response = await fetch(aiForm.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': formData.get('_token') || ''
                        },
                        body: formData
                    });

                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Gagal menjalankan analisis AI.');
                    }

                    const score = data.data.similarity_score;
                    if (aiScoreDisplay) aiScoreDisplay.textContent = score;
                    if (aiScoreUnit) aiScoreUnit.textContent = '/100';

                    if (aiGaugePath) {
                        aiGaugePath.style.strokeDashoffset = data.data.gauge_offset;
                        aiGaugePath.setAttribute('stroke', data.data.gauge_color);
                    }
                    if (aiScoreGauge) {
                        aiScoreGauge.style.setProperty('--ai-score', score);
                        aiScoreGauge.style.setProperty('--ai-gauge-color', data.data.gauge_color);
                    }

                    if (aiBadge) {
                        aiBadge.className = 'ai-badge ' + data.data.recommendation_class;
                        aiBadge.textContent = (data.data.recommendation_label || '').toUpperCase();
                    }

                    if (aiMatchedAtDisplay) {
                        aiMatchedAtDisplay.textContent = 'Dianalisis ' + data.data.matched_at;
                    }
                    if (aiModelDisplay && data.data.model_used) {
                        aiModelDisplay.textContent = 'Model: ' + data.data.model_used;
                    }

                    if (aiReasoningText) {
                        aiReasoningText.textContent = data.data.reasoning;
                        aiReasoningText.classList.remove('text-muted');
                    }

                    if (aiPanelHint) {
                        aiPanelHint.textContent = 'Analisis AI selesai dan berhasil diperbarui.';
                        aiPanelHint.classList.add('text-success');
                    }
                    if (aiBtnText) {
                        aiBtnText.textContent = 'Analisis AI Ulang';
                    }

                    const autofillBtn = document.getElementById('btn-fill-ai') || document.getElementById('btn-autofill-ai');
                    if (autofillBtn) {
                        autofillBtn.dataset.aiScore = score;
                    }

                    const verificationForm = document.querySelector('form[data-claim-verification-form]');
                    if (verificationForm) {
                        verificationForm.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                } catch (error) {
                    console.error('AI Matching Error:', error);
                    if (aiPanelHint) {
                        aiPanelHint.textContent = 'Error: ' + error.message;
                        aiPanelHint.classList.add('text-danger');
                    }
                } finally {
                    aiBtn.disabled = false;
                    if (aiBtnIcon) aiBtnIcon.style.display = 'inline-block';
                    if (aiBtnSpinner) aiBtnSpinner.style.display = 'none';
                    if (aiBtnText && aiBtnText.textContent === 'Sedang Menganalisis...') {
                        aiBtnText.textContent = 'Jalankan Analisis AI Sekarang';
                    }
                }
            });

            // Handover modal handling
            const handoverModal = document.getElementById('handover-modal-backdrop');
            const heroModalBtn = document.getElementById('hero-handover-trigger-btn');
            const closeModalBtn = document.getElementById('close-handover-modal-btn');
            const cancelModalBtn = document.getElementById('cancel-handover-modal-btn');
            const handoverForm = document.getElementById('handover-form');
            const submitHandoverBtn = document.getElementById('submit-handover-btn');
            const ticketInput = document.getElementById('handover_kode_tiket');
            const ticketFeedback = document.getElementById('handover-ticket-feedback');
            const copyTicketBtn = document.getElementById('btn-copy-ticket-code');
            const expectedTicketCode = (handoverModal && handoverModal.dataset.expectedTicket) || '';
            const expectedTicketNumber = (handoverModal && handoverModal.dataset.expectedNumber) || '';
            const expectedClaimId = (handoverModal && handoverModal.dataset.claimId) || '';

            if (copyTicketBtn) {
                copyTicketBtn.addEventListener('click', function () {
                    const code = copyTicketBtn.dataset.code || expectedTicketCode;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(code).then(function () {
                            const label = copyTicketBtn.querySelector('.copy-label');
                            if (label) {
                                const orig = label.textContent;
                                label.textContent = 'Tersalin!';
                                setTimeout(function () { label.textContent = orig; }, 2000);
                            }
                        }).catch(function () {
                            prompt('Salin kode tiket:', code);
                        });
                    } else {
                        prompt('Salin kode tiket:', code);
                    }
                });
            }

            function openHandoverModal() {
                if (handoverModal) {
                    handoverModal.style.display = 'flex';
                    if (ticketInput) {
                        setTimeout(function () { ticketInput.focus(); }, 100);
                    }
                }
            }
            function closeHandoverModal() {
                if (handoverModal) handoverModal.style.display = 'none';
            }

            if (heroModalBtn) heroModalBtn.addEventListener('click', openHandoverModal);
            if (closeModalBtn) closeModalBtn.addEventListener('click', closeHandoverModal);
            if (cancelModalBtn) cancelModalBtn.addEventListener('click', closeHandoverModal);

            if (handoverModal) {
                handoverModal.addEventListener('click', function (e) {
                    if (e.target === handoverModal) closeHandoverModal();
                });
            }

            function normalizeTicket(code) {
                return (code || '').trim().toUpperCase().replace(/^#/, '');
            }

            function isTicketValid(inputVal) {
                const norm = normalizeTicket(inputVal);
                return norm === normalizeTicket(expectedTicketCode)
                    || norm === expectedTicketNumber
                    || norm === expectedClaimId;
            }

            if (ticketInput) {
                ticketInput.addEventListener('input', function () {
                    ticketInput.style.borderColor = '#cbd5e1';
                    if (ticketFeedback) {
                        ticketFeedback.style.display = 'none';
                        ticketFeedback.textContent = '';
                    }
                });
            }

            if (handoverForm && submitHandoverBtn) {
                handoverForm.addEventListener('submit', function (e) {
                    if (ticketInput) {
                        const entered = ticketInput.value.trim();
                        if (!entered) {
                            e.preventDefault();
                            alert('Kode tiket pengambilan wajib diisi.');
                            ticketInput.focus();
                            return;
                        }

                        if (!isTicketValid(entered)) {
                            e.preventDefault();
                            if (ticketFeedback) {
                                ticketFeedback.style.display = 'block';
                                ticketFeedback.textContent = 'Kode tiket tidak valid. Pastikan kode sesuai dengan tiket yang dibawa penerima.';
                            }
                            ticketInput.style.borderColor = '#dc2626';
                            alert('Kode tiket tidak valid. Pastikan kode sesuai dengan tiket yang dibawa penerima.');
                            ticketInput.focus();
                            return;
                        }
                    }

                    submitHandoverBtn.disabled = true;
                    submitHandoverBtn.innerHTML = '<span class="ai-spinner" style="margin-right: 6px;"></span> Memproses Serah Terima...';
                });
            }

            if (handoverModal && handoverModal.dataset.autoOpen === 'true') {
                openHandoverModal();
            }
        });
    </script>
@endsection
