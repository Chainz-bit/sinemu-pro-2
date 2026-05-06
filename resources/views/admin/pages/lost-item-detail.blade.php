@extends('admin.layouts.app')

@php
    $pageTitle = 'Detail Barang Hilang - SiNemu';
    $activeMenu = 'lost-items';
    $searchAction = route('admin.lost-items');
    $searchPlaceholder = 'Cari laporan atau barang';
    $hideSearch = true;
    $hideSidebar = true;
    $topbarBackUrl = route('admin.lost-items');
    $topbarBackLabel = 'Kembali ke Daftar Barang Hilang';

    $fotoUrlDefault = str_contains(strtolower((string) $laporanBarangHilang->nama_barang), 'dompet')
        ? route('media.image', ['folder' => 'barang-hilang', 'path' => 'dompet.webp'])
        : route('media.image', ['folder' => 'barang-temuan', 'path' => 'hp.webp']);
    $fotoUrl = $fotoUrlDefault;
    $fotoSrc = $fotoUrlDefault;
    $localFotoPath = null;

    $rawFotoPath = str_replace('\\', '/', trim((string) ($laporanBarangHilang->foto_barang ?? '')));
    if ($rawFotoPath !== '') {
        if (\Illuminate\Support\Str::startsWith($rawFotoPath, ['http://', 'https://'])) {
            $fotoUrl = $rawFotoPath;
        } else {
            $fotoPath = ltrim($rawFotoPath, '/');
            if (\Illuminate\Support\Str::startsWith($fotoPath, 'storage/')) {
                $fotoPath = substr($fotoPath, 8);
            } elseif (\Illuminate\Support\Str::startsWith($fotoPath, 'public/')) {
                $fotoPath = substr($fotoPath, 7);
            }

            [$folder, $subPath] = array_pad(explode('/', $fotoPath, 2), 2, '');
            $fotoUrl = in_array($folder, ['barang-hilang', 'barang-temuan', 'verifikasi-klaim'], true) && $subPath !== ''
                ? route('media.image', ['folder' => $folder, 'path' => $subPath])
                : asset('storage/' . $fotoPath);
            $localFotoPath = $fotoPath;
        }
    }

    if (!empty($localFotoPath) && \Illuminate\Support\Facades\Storage::disk('public')->exists($localFotoPath)) {
        $absolutePath = \Illuminate\Support\Facades\Storage::disk('public')->path($localFotoPath);
        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $mimeMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
        $mimeType = $mimeMap[$ext] ?? 'image/jpeg';
        $binary = @file_get_contents($absolutePath);
        if ($binary !== false) {
            $fotoSrc = 'data:' . $mimeType . ';base64,' . base64_encode($binary);
        } else {
            $fotoSrc = $fotoUrl;
        }
    } else {
        $fotoSrc = $fotoUrl;
    }

    $reportStatus = \App\Support\ReportStatusPresenter::key($laporanBarangHilang->status_laporan ?? null);
    $statusLabel = \App\Support\ReportStatusPresenter::label($reportStatus);
    $statusClass = \App\Support\ReportStatusPresenter::cssClass($reportStatus);

    $pelaporName = $laporanBarangHilang->user?->nama ?? $laporanBarangHilang->user?->name ?? 'Pengguna';
    $claimStatusHistoryLabel = match ($latestKlaim->status_klaim ?? null) {
        'disetujui' => 'Disetujui',
        'ditolak' => 'Ditolak',
        'pending' => 'Menunggu Verifikasi',
        default => 'Belum Ada Klaim',
    };
    $pelaporEmail = $laporanBarangHilang->user?->email ?? 'Email tidak tersedia';
    $hasPelaporEmail = filter_var($pelaporEmail, FILTER_VALIDATE_EMAIL) !== false;
    $emailContactHref = $hasPelaporEmail
        ? 'mailto:' . $pelaporEmail
        : '#';
    $contactSubject = rawurlencode('Tindak lanjut laporan barang hilang #' . $laporanBarangHilang->id);
    $contactBody = rawurlencode('Halo ' . $pelaporName . ', kami ingin menindaklanjuti laporan barang hilang Anda.');
    $hubungiHref = $hasPelaporEmail
        ? ('mailto:' . $pelaporEmail . '?subject=' . $contactSubject . '&body=' . $contactBody)
        : '#';
    $createdAtLabel = !empty($laporanBarangHilang->created_at)
        ? \Illuminate\Support\Carbon::parse($laporanBarangHilang->created_at)->format('d M Y, H:i')
        : '-';
    $updatedAtLabel = !empty($laporanBarangHilang->updated_at)
        ? \Illuminate\Support\Carbon::parse($laporanBarangHilang->updated_at)->format('d M Y, H:i')
        : '-';
    $tanggalHilangLabel = !empty($laporanBarangHilang->tanggal_hilang)
        ? \Illuminate\Support\Carbon::parse($laporanBarangHilang->tanggal_hilang)->format('d M Y')
        : '-';
    $waktuHilangRaw = (string) ($laporanBarangHilang->waktu_hilang ?? '');
    $waktuHilangLabel = $waktuHilangRaw !== ''
        ? (date('H:i', strtotime($waktuHilangRaw)) ?: $waktuHilangRaw)
        : '-';
    $initials = collect(explode(' ', trim($pelaporName)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
        ->implode('');
@endphp

@section('page-content')
    <section class="lost-detail-page">
<div class="lost-detail-header">
            <div>
                <p class="lost-detail-breadcrumb">
                    <a href="{{ route('admin.lost-items') }}">Daftar Barang Hilang</a>
                    <span>/</span>
                    <strong>Detail Barang</strong>
                </p>
                <h1>Detail Laporan Barang Hilang</h1>
                <div class="lost-detail-header-meta">
                    <span>Laporan #{{ $laporanBarangHilang->id }}</span>
                    <span>Dibuat {{ $createdAtLabel }} WIB</span>
                    <span>Diperbarui {{ $updatedAtLabel }} WIB</span>
                </div>
            </div>
        </div>

        <div class="lost-detail-grid">
            <article class="report-card lost-detail-main">
                <div class="lost-detail-main-content">
                    <div class="lost-detail-image-wrap">
                        <span class="lost-detail-image-label">Foto Barang</span>
                        <img
                            src="{{ $fotoSrc }}"
                            alt="{{ $laporanBarangHilang->nama_barang }}"
                            class="lost-detail-image"
                            loading="lazy"
                            decoding="async"
                            onerror="this.onerror=null;this.src='{{ $fotoUrlDefault }}';"
                        >
                    </div>

                    <div class="lost-detail-body">
                        <h2>{{ strtoupper($laporanBarangHilang->nama_barang) }}</h2>
                        <p>{{ $laporanBarangHilang->keterangan ?: 'Tidak ada deskripsi tambahan.' }}</p>

                        <div class="lost-detail-meta">
                            <div>
                                <span>Kategori</span>
                                <strong>{{ $laporanBarangHilang->kategori_barang ?: 'Tidak Dikategorikan' }}</strong>
                            </div>
                            <div>
                                <span>Tanggal Hilang</span>
                                <strong>{{ $tanggalHilangLabel }}</strong>
                            </div>
                            <div>
                                <span>Lokasi Ditemukan Terakhir</span>
                                <strong>{{ $laporanBarangHilang->lokasi_hilang ?: '-' }}</strong>
                            </div>
                            <div>
                                <span>ID Laporan</span>
                                <strong>#{{ $laporanBarangHilang->id }}</strong>
                            </div>
                        </div>

                        <div class="lost-detail-meta">
                            <div>
                                <span>Warna</span>
                                <strong>{{ $laporanBarangHilang->warna_barang ?: '-' }}</strong>
                            </div>
                            <div>
                                <span>Merek</span>
                                <strong>{{ $laporanBarangHilang->merek_barang ?: '-' }}</strong>
                            </div>
                            <div>
                                <span>Nomor Seri / Kode</span>
                                <strong>{{ $laporanBarangHilang->nomor_seri ?: '-' }}</strong>
                            </div>
                            <div>
                                <span>No. WA Pelapor</span>
                                <strong>{{ $laporanBarangHilang->kontak_pelapor ?: '-' }}</strong>
                            </div>
                        </div>

                        @if(!empty($laporanBarangHilang->detail_lokasi_hilang) || !empty($laporanBarangHilang->ciri_khusus) || !empty($laporanBarangHilang->bukti_kepemilikan))
                            <div class="lost-detail-meta">
                                <div>
                                    <span>Detail Lokasi Hilang</span>
                                    <strong>{{ $laporanBarangHilang->detail_lokasi_hilang ?: '-' }}</strong>
                                </div>
                                <div>
                                    <span>Ciri Unik</span>
                                    <strong>{{ $laporanBarangHilang->ciri_khusus ?: '-' }}</strong>
                                </div>
                                <div>
                                    <span>Bukti Kepemilikan</span>
                                    <strong>{{ $laporanBarangHilang->bukti_kepemilikan ?: '-' }}</strong>
                                </div>
                                <div>
                                    <span>Jam Hilang</span>
                                    <strong>{{ $waktuHilangLabel }} WIB</strong>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </article>

            <div class="lost-detail-side">
                <article class="report-card lost-detail-panel lost-panel-status">
                    <header><h2>Status Laporan</h2></header>
                    <div class="lost-detail-panel-body">
                        <span class="status-chip {{ $statusClass }}">{{ $statusLabel }}</span>

                        <div class="lost-verify-box">
                            <small>Verifikasi Laporan</small>
                            <p>Tentukan apakah laporan ini layak ditampilkan di halaman publik.</p>
                            <div class="lost-verify-actions">
                                <form method="POST" action="{{ route('admin.lost-items.verify', $laporanBarangHilang->id) }}" data-confirm-delete data-confirm-title="Setujui Laporan" data-confirm-message="Setujui laporan ini? Laporan akan bisa dipublikasikan ke Home." data-confirm-submit-label="Setujui" data-confirm-submit-variant="primary">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status_laporan" value="approved">
                                    <button type="submit" class="filter-btn lost-action-btn lost-action-btn-primary">Setujui Laporan</button>
                                </form>
                                <form method="POST" action="{{ route('admin.lost-items.verify', $laporanBarangHilang->id) }}" data-confirm-delete data-confirm-title="Tolak Laporan" data-confirm-message="Tolak laporan ini? Laporan tidak akan tampil di Home." data-confirm-submit-label="Tolak" data-confirm-submit-variant="danger">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status_laporan" value="rejected">
                                    <button type="submit" class="filter-btn lost-action-btn lost-action-btn-ghost">Tolak Laporan</button>
                                </form>
                            </div>
                        </div>

                        @if(!$latestKlaim)
                            <p>Belum ada klaim aktif untuk laporan ini.</p>
                        @else
                            <p>Perubahan status klaim dilakukan dari halaman Verifikasi Klaim agar checklist keamanan tetap konsisten.</p>
                            <a href="{{ route('admin.claim-verifications.show', $latestKlaim->id) }}" class="filter-btn lost-action-btn lost-action-btn-primary">
                                Buka Verifikasi Klaim
                            </a>
                        @endif
                    </div>
                </article>

                <article class="report-card lost-detail-panel lost-panel-reporter">
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
                            <a href="{{ $hubungiHref }}" class="filter-btn {{ $hasPelaporEmail ? '' : 'is-disabled' }}">Hubungi</a>
                            <a href="{{ $emailContactHref }}" class="filter-btn {{ $hasPelaporEmail ? '' : 'is-disabled' }}">Email</a>
                        </div>
                        <p>{{ $pelaporEmail }}</p>
                    </div>
                </article>

                <article class="report-card lost-detail-panel lost-panel-location">
                    <header><h2>Lokasi &amp; Waktu Laporan</h2></header>
                    <div class="lost-detail-panel-body">
                        <div class="lost-info-item">
                            <span class="lost-info-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 7a1 1 0 0 1 1 1v4.1l2.2 1.47a1 1 0 1 1-1.1 1.66l-2.65-1.76A1 1 0 0 1 11 13V8a1 1 0 0 1 1-1zM12 3a9 9 0 1 1 0 18a9 9 0 0 1 0-18zm0 2a7 7 0 1 0 0 14a7 7 0 0 0 0-14z" fill="currentColor"/></svg>
                            </span>
                            <div>
                                <small>Hilang Pada</small>
                                <p><strong>{{ $tanggalHilangLabel }} {{ $waktuHilangLabel !== '-' ? ', '.$waktuHilangLabel : '' }} WIB</strong></p>
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

                <article class="report-card lost-detail-panel lost-panel-activity">
                    <header><h2>Riwayat Aktivitas</h2></header>
                    <div class="lost-detail-panel-body">
                        <div class="lost-activity-item">
                            <p><strong>Laporan Dibuat</strong></p>
                            <small>{{ !empty($laporanBarangHilang->created_at) ? \Illuminate\Support\Carbon::parse($laporanBarangHilang->created_at)->format('d M Y, H:i') : '-' }} WIB</small>
                        </div>
                        @if($latestKlaim)
                            <div class="lost-activity-item">
                                <p><strong>Status Klaim Terakhir</strong></p>
                                <small>{{ $claimStatusHistoryLabel }} - {{ \Illuminate\Support\Carbon::parse($latestKlaim->created_at)->format('d M Y, H:i') }} WIB</small>
                            </div>
                        @endif
                    </div>
                </article>
            </div>
        </div>

        <article class="report-card mt-3 lost-matching-card" id="kandidat-pencocokan">
            <header class="mb-3 lost-matching-header">
                <span class="lost-matching-header-icon" aria-hidden="true">
                    <i class="fa-solid fa-link"></i>
                </span>
                <div class="lost-matching-header-copy">
                    <h2 class="mb-1">Kandidat Pencocokan Barang Temuan</h2>
                    <p class="mb-0">Sistem menilai kandidat berdasarkan kategori, nama barang, warna, merek, nomor seri, lokasi, tanggal, deskripsi, dan ciri khusus.</p>
                </div>
                <a href="{{ route('admin.lost-items.show', $laporanBarangHilang->id) }}#kandidat-pencocokan" class="filter-btn lost-matching-refresh">
                    <i class="fa-solid fa-rotate-right"></i>Muat Ulang Kandidat
                </a>
            </header>

            @if((string) ($laporanBarangHilang->status_laporan ?? '') !== \App\Support\WorkflowStatus::REPORT_APPROVED)
                <div class="lost-matching-empty">
                    <span class="lost-matching-empty-icon"><i class="fa-solid fa-clock"></i></span>
                    <div>
                        <h3>Kandidat belum tersedia</h3>
                        <p>Kandidat baru akan muncul setelah laporan barang hilang disetujui admin.</p>
                    </div>
                </div>
            @elseif(($matchingCandidates ?? collect())->isEmpty())
                <div class="lost-matching-empty">
                    <span class="lost-matching-empty-icon"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <div>
                        <h3>Belum ada kandidat kuat</h3>
                        <p>Belum ada kandidat dengan skor kecocokan yang cukup atau semua kandidat sudah ditinjau.</p>
                    </div>
                </div>
            @else
                <div class="report-table-wrap">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Barang Temuan</th>
                                <th>Skor</th>
                                <th>Ringkasan</th>
                                <th>Parameter Skor</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($matchingCandidates as $candidate)
                                @php
                                    $barang = $candidate->barang;
                                    $reasons = collect($candidate->reasons ?? [])->take(3)->values();
                                    $meta = collect($candidate->meta ?? []);
                                    $scoreClass = $candidate->score >= 75 ? 'status-selesai' : ($candidate->score >= 55 ? 'status-diproses' : 'status-dalam_peninjauan');
                                    $scoreSummary = $candidate->score >= 75 ? 'Tinggi' : ($candidate->score >= 55 ? 'Sedang' : 'Rendah');
                                    $catatanSkor = 'Skor otomatis: ' . $candidate->score . '/100';
                                    if ($reasons->isNotEmpty()) {
                                        $catatanSkor .= ' | ' . $reasons->implode(', ');
                                    }
                                @endphp
                                <tr>
                                    <td>
                                        <div>
                                            <strong>{{ $barang->nama_barang }}</strong>
                                            <small style="display:block;">{{ $barang->lokasi_ditemukan }} - {{ \Illuminate\Support\Carbon::parse($barang->tanggal_ditemukan)->format('d M Y') }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="lost-matching-score">
                                            <span class="status-chip {{ $scoreClass }}">{{ $candidate->score }} / 100</span>
                                            <small>{{ $scoreSummary }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        @if($reasons->isNotEmpty())
                                            <div>{{ $reasons->implode(', ') }}</div>
                                        @else
                                            <span>-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="lost-matching-metrics">
                                            <small>Nama: {{ (int) ($meta->get('nama_barang', 0)) }}</small>
                                            <small>Kategori: {{ (int) ($meta->get('kategori', 0)) }}</small>
                                            <small>Warna: {{ (int) ($meta->get('warna', 0)) }}</small>
                                            <small>Merek: {{ (int) ($meta->get('merek', 0)) }}</small>
                                            <small>No. Seri: {{ (int) ($meta->get('nomor_seri', 0)) }}</small>
                                            <small>Lokasi: {{ (int) ($meta->get('lokasi', 0)) }}</small>
                                            <small>Tanggal: {{ (int) ($meta->get('tanggal', 0)) }}</small>
                                            <small>Deskripsi/Ciri: {{ (int) ($meta->get('deskripsi', 0)) }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="lost-matching-actions">
                                            <form method="POST" action="{{ route('admin.matches.store') }}">
                                                @csrf
                                                <input type="hidden" name="laporan_hilang_id" value="{{ $laporanBarangHilang->id }}">
                                                <input type="hidden" name="barang_id" value="{{ $barang->id }}">
                                                <input type="hidden" name="catatan" value="{{ $catatanSkor }}">
                                                <button type="submit" class="filter-btn lost-matching-btn">Tandai Diduga Cocok</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.matches.dismiss') }}">
                                                @csrf
                                                <input type="hidden" name="laporan_hilang_id" value="{{ $laporanBarangHilang->id }}">
                                                <input type="hidden" name="barang_id" value="{{ $barang->id }}">
                                                <input type="hidden" name="catatan" value="{{ 'Skor otomatis: '.$candidate->score.'/100 | Ditandai tidak cocok oleh admin.' }}">
                                                <button type="submit" class="filter-btn lost-action-btn lost-action-btn-ghost lost-matching-btn">Tidak Cocok</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </article>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.body.classList.add('lost-detail-page-mode');
        });
    </script>
@endsection