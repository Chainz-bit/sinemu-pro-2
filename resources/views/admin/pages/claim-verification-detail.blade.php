@extends('admin.layouts.app')

@php
    $pageTitle = 'Detail Verifikasi Klaim - SiNemu';
    $activeMenu = 'claim-verifications';
    $hideSearch = true;
    $hideSidebar = true;
    $topbarBackUrl = route('admin.claim-verifications');
    $topbarBackLabel = 'Kembali ke Verifikasi Klaim';
    $hasPelaporEmail = filter_var($pelaporEmail, FILTER_VALIDATE_EMAIL) !== false;
    $emailContactHref = $hasPelaporEmail ? ('mailto:' . $pelaporEmail) : '#';
    $contactSubject = rawurlencode('Tindak lanjut verifikasi klaim #' . $klaim->id);
    $contactBody = rawurlencode('Halo ' . $pelaporNama . ', kami ingin menindaklanjuti pengajuan klaim Anda.');
    $hubungiHref = $hasPelaporEmail
        ? ('mailto:' . $pelaporEmail . '?subject=' . $contactSubject . '&body=' . $contactBody)
        : '#';
    $ringkasanStatus = match ($statusKey ?? 'menunggu') {
        'menunggu' => 'Klaim masih menunggu keputusan admin. Pastikan data pelapor dan kecocokan barang sudah tervalidasi.',
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
    $buktiKepemilikanPengaju = trim((string) ($klaim->laporanHilang?->bukti_kepemilikan ?? ''));
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
        ->filter(fn ($path) => is_string($path) && trim($path) !== '')
        ->map(function ($path) {
            $cleanPath = str_replace('\\', '/', ltrim((string) $path, '/'));
            if ($cleanPath === '') {
                return null;
            }

            if (\Illuminate\Support\Str::startsWith($cleanPath, ['http://', 'https://'])) {
                return $cleanPath;
            }

            if (\Illuminate\Support\Str::startsWith($cleanPath, 'storage/')) {
                $cleanPath = substr($cleanPath, 8);
            } elseif (\Illuminate\Support\Str::startsWith($cleanPath, 'public/')) {
                $cleanPath = substr($cleanPath, 7);
            }

            [$folder, $subPath] = array_pad(explode('/', $cleanPath, 2), 2, '');
            return in_array($folder, ['barang-hilang', 'barang-temuan', 'verifikasi-klaim', 'profil-admin', 'profil-user'], true) && $subPath !== ''
                ? route('media.image', ['folder' => $folder, 'path' => $subPath])
                : asset('storage/' . $cleanPath);
        })
        ->filter()
        ->values();
@endphp

@section('page-content')
    <section class="claim-detail-page">
        <div class="claim-detail-header">
            <div>
                <p class="claim-detail-breadcrumb">
                    <a href="{{ route('admin.claim-verifications') }}">Verifikasi Klaim</a>
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
                            <h3>Panduan Keputusan Admin</h3>
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
                            <small>Langkah Lanjut Admin</small>
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
                                        <a href="{{ $proofUrl }}" target="_blank" rel="noopener noreferrer" class="claim-proof-item">
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
                                <small>Checklist Verifikasi Admin</small>
                                <p>
                                    @foreach($checklistLabels as $checklistKey => $checklistLabel)
                                        {{ $checklistLabel }}: {{ (($hasilChecklist[$checklistKey] ?? false) ? 'Ya' : 'Tidak') }}@if(!$loop->last)<br>@endif
                                    @endforeach
                                </p>
                            </div>
                        @endif
                        @if($catatanVerifikasiAdmin !== '')
                            <div class="claim-note-box">
                                <small>Catatan Verifikasi Admin</small>
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

                <article class="report-card claim-side-card claim-panel-requester">
                    <header><h2>Informasi Pengaju</h2></header>
                    <div class="claim-side-body">
                        <strong>{{ $pelaporNama }}</strong>
                        <small>{{ $pelaporEmail }}</small>
                        <div class="claim-contact-actions">
                            <a href="{{ $hubungiHref }}" class="filter-btn {{ $hasPelaporEmail ? '' : 'is-disabled' }}">Hubungi</a>
                            <a href="{{ $emailContactHref }}" class="filter-btn {{ $hasPelaporEmail ? '' : 'is-disabled' }}">Email</a>
                        </div>
                    </div>
                </article>
            </aside>
        </section>

        @if(($statusKey ?? 'menunggu') === 'menunggu')
            <div class="claim-detail-bottom-actions claim-detail-bottom-actions-stack">
                <form method="POST" action="{{ route('admin.claim-verifications.approve', $klaim->id) }}" class="claim-verification-form" data-confirm-delete>
                    @csrf
                    <div class="claim-verification-head">
                        <h3>Form Verifikasi Klaim</h3>
                        <p>Isi checklist dulu, lalu pilih aksi setujui atau tolak.</p>
                    </div>
                    <div class="claim-verification-rule">
                        <strong>Aturan Otomatis Persetujuan</strong>
                        <span>Klaim hanya bisa disetujui jika skor minimal 75 dan semua poin kritikal bernilai "Ya".</span>
                    </div>
                    <div class="claim-note-box claim-note-box-spacing">
                        <small>Checklist Verifikasi (Wajib)</small>
                        <div class="claim-verification-grid">
                            @foreach($checklistLabels as $checklistKey => $checklistLabel)
                                <label class="claim-verification-field">
                                    <span>{{ $checklistLabel }}</span>
                                    <select name="{{ $checklistKey }}" class="form-select" required>
                                        <option value="">Pilih</option>
                                        <option value="1" @selected(old($checklistKey) === '1')>Ya</option>
                                        <option value="0" @selected(old($checklistKey) === '0')>Tidak</option>
                                    </select>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="claim-note-box claim-note-box-spacing">
                        <small>Catatan Verifikasi Admin</small>
                        <textarea name="catatan_verifikasi_admin" class="form-control" rows="2" maxlength="2000" placeholder="Tambahkan catatan validasi jika diperlukan.">{{ old('catatan_verifikasi_admin') }}</textarea>
                    </div>
                    <div class="claim-note-box claim-note-box-spacing">
                        <small>Alasan Penolakan (Wajib jika ditolak)</small>
                        <textarea name="alasan_penolakan" class="form-control" rows="2" maxlength="2000" placeholder="Isi alasan jika Anda akan menolak klaim.">{{ old('alasan_penolakan') }}</textarea>
                    </div>
                    <div class="claim-verification-actions">
                        <button type="submit"
                            formaction="{{ route('admin.claim-verifications.reject', $klaim->id) }}"
                            data-confirm-title="Konfirmasi Tolak Klaim"
                            data-confirm-message="Tolak klaim ini? Pastikan alasan penolakan sudah diisi dengan jelas."
                            data-confirm-submit-label="Ya, Tolak"
                            data-confirm-submit-variant="danger"
                            class="claim-action-btn danger">
                            Tolak Klaim
                        </button>
                        <button type="submit"
                            formaction="{{ route('admin.claim-verifications.approve', $klaim->id) }}"
                            data-confirm-title="Konfirmasi Setujui Klaim"
                            data-confirm-message="Setujui klaim ini? Status klaim akan berubah menjadi disetujui."
                            data-confirm-submit-label="Ya, Setujui"
                            data-confirm-submit-variant="primary"
                            class="claim-action-btn success">
                            Setujui Klaim
                        </button>
                    </div>
                </form>
            </div>
        @endif
        @if(($statusKey ?? 'menunggu') === 'disetujui')
            <div class="claim-detail-bottom-actions">
                <form method="POST" action="{{ route('admin.claim-verifications.complete', $klaim->id) }}"
                    data-confirm-delete
                    data-confirm-title="Tandai Klaim Selesai"
                    data-confirm-message="Barang sudah diserahkan ke pemilik dan klaim akan ditutup sebagai selesai."
                    data-confirm-submit-label="Tandai Selesai"
                    data-confirm-submit-variant="primary">
                    @csrf
                    <button type="submit" class="claim-action-btn success">Tandai Selesai</button>
                </form>
            </div>
        @endif
    </section>
@endsection
