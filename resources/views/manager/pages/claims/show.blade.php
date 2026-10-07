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
        <div class="claim-detail-header">
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
                                        <svg viewBox="0 0 20 20" fill="currentColor" class="panel-header-svg text-success" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        Tindakan Pengelola
                                    </h2>
                                </header>
                                <div class="claim-side-body">
                                    <p class="success-callout-text">Klaim telah disetujui. Lakukan serah terima barang kepada pemilik dan tandai selesai.</p>
                                    <form method="POST" action="{{ manager_route('claim-verifications.complete', $klaim->id) }}"
                                        data-confirm-delete
                                        data-confirm-title="Tandai Klaim Selesai"
                                        data-confirm-message="Barang sudah diserahkan ke pemilik dan klaim akan ditutup sebagai selesai."
                                        data-confirm-submit-label="Tandai Selesai"
                                        data-confirm-submit-variant="primary">
                                        @csrf
                                        <button type="submit" class="claim-action-btn success w-100">Tandai Selesai / Serah Terima</button>
                                    </form>
                                </div>
                            </article>
                        @endif
                    </div>
                </div>
            </aside>
        </section>
    </section>

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
        });
    </script>
@endsection
