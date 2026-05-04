@extends('admin.layouts.app')

@php
    /** BAGIAN: Meta Halaman */
    $pageTitle = 'Detail Barang Temuan - SiNemu';
    $activeMenu = 'found-items';
    $searchAction = route('admin.found-items');
    $searchPlaceholder = 'Cari laporan atau barang';
    $hideSearch = true;
    $hideSidebar = true;
    $topbarBackUrl = route('admin.found-items');
    $topbarBackLabel = 'Kembali ke Daftar Barang Temuan';

    $fotoUrlDefault = route('media.image', ['folder' => 'barang-temuan', 'path' => 'hp.webp']);
    $fotoUrl = $fotoUrlDefault;
    $fotoSrc = $fotoUrlDefault;
    $localFotoPath = null;

    $rawFotoPath = str_replace('\\', '/', trim((string) ($barang->foto_barang ?? '')));
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
        $mimeType = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($localFotoPath) ?: 'image/jpeg';
        $binary = @file_get_contents($absolutePath);
        if ($binary !== false) {
            $fotoSrc = 'data:' . $mimeType . ';base64,' . base64_encode($binary);
        } else {
            $fotoSrc = $fotoUrl;
        }
    } else {
        $fotoSrc = $fotoUrl;
    }

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
    $reportStatus = \App\Support\ReportStatusPresenter::key($barang->status_laporan ?? null);
    $reportStatusLabel = \App\Support\ReportStatusPresenter::label($reportStatus);
    $reportStatusClass = \App\Support\ReportStatusPresenter::cssClass($reportStatus);
    [$statusLabel, $statusClass] = $statusMap[$barang->status_barang] ?? ['UNKNOWN', 'status-diproses'];
    $petugasName = $barang->admin?->nama ?? 'Admin';
    $petugasEmail = $barang->admin?->email ?? 'Email tidak tersedia';
    $hasPetugasEmail = filter_var($petugasEmail, FILTER_VALIDATE_EMAIL) !== false;
    $emailContactHref = $hasPetugasEmail
        ? 'mailto:' . $petugasEmail
        : '#';
    $contactSubject = rawurlencode('Tindak lanjut laporan barang temuan #' . $barang->id);
    $contactBody = rawurlencode('Halo ' . $petugasName . ', kami ingin menindaklanjuti laporan barang temuan ini.');
    $hubungiHref = $hasPetugasEmail
        ? ('mailto:' . $petugasEmail . '?subject=' . $contactSubject . '&body=' . $contactBody)
        : '#';
    $createdAtLabel = !empty($barang->created_at)
        ? \Illuminate\Support\Carbon::parse($barang->created_at)->format('d M Y, H:i')
        : '-';
    $updatedAtLabel = !empty($barang->updated_at)
        ? \Illuminate\Support\Carbon::parse($barang->updated_at)->format('d M Y, H:i')
        : '-';
    $initials = collect(explode(' ', trim($petugasName)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
        ->implode('');
    $statusHistories = $barang->statusHistories->take(8);
    $tanggalDitemukanLabel = !empty($barang->tanggal_ditemukan)
        ? \Illuminate\Support\Carbon::parse($barang->tanggal_ditemukan)->format('d M Y')
        : '-';
    $waktuDitemukanRaw = (string) ($barang->waktu_ditemukan ?? '');
    $waktuDitemukanLabel = $waktuDitemukanRaw !== ''
        ? (date('H:i', strtotime($waktuDitemukanRaw)) ?: $waktuDitemukanRaw)
        : '-';
    $penemuName = $barang->nama_penemu ?: $petugasName;
    $penemuContact = $barang->kontak_penemu ?: '-';
    $penemuInitials = collect(explode(' ', trim((string) $penemuName)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
        ->implode('');
@endphp

@section('page-content')
    <section class="found-detail-page">
<div class="found-detail-header">
            <div>
                <p class="found-detail-breadcrumb">
                    <a href="{{ route('admin.found-items') }}">Daftar Barang Temuan</a>
                    <span>/</span>
                    <strong>Detail Barang</strong>
                </p>
                <h1>Detail Laporan Barang Temuan</h1>
                <div class="found-detail-header-meta">
                    <span>Laporan #{{ $barang->id }}</span>
                    <span>Dibuat {{ $createdAtLabel }} WIB</span>
                    <span>Diperbarui {{ $updatedAtLabel }} WIB</span>
                </div>
            </div>
        </div>

        <div class="found-detail-grid">
            <article class="report-card found-detail-main">
                <div class="found-detail-main-content">
                    <div class="found-detail-image-wrap">
                        <span class="found-detail-image-label">Foto Barang</span>
                        <img
                            src="{{ $fotoSrc }}"
                            alt="{{ $barang->nama_barang }}"
                            class="found-detail-image"
                            loading="lazy"
                            decoding="async"
                            onerror="this.onerror=null;this.src='{{ $fotoUrlDefault }}';"
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
                                <strong>{{ $tanggalDitemukanLabel }}</strong>
                            </div>
                            <div>
                                <span>Lokasi Ditemukan</span>
                                <strong>{{ $barang->lokasi_ditemukan ?: '-' }}</strong>
                            </div>
                            <div>
                                <span>ID Laporan</span>
                                <strong>#{{ $barang->id }}</strong>
                            </div>
                        </div>

                        <div class="found-detail-meta">
                            <div>
                                <span>Warna</span>
                                <strong>{{ $barang->warna_barang ?: '-' }}</strong>
                            </div>
                            <div>
                                <span>Merek</span>
                                <strong>{{ $barang->merek_barang ?: '-' }}</strong>
                            </div>
                            <div>
                                <span>Nomor Seri / Kode</span>
                                <strong>{{ $barang->nomor_seri ?: '-' }}</strong>
                            </div>
                            <div>
                                <span>Jam Ditemukan</span>
                                <strong>{{ $waktuDitemukanLabel !== '-' ? $waktuDitemukanLabel.' WIB' : '-' }}</strong>
                            </div>
                        </div>

                        @if(!empty($barang->detail_lokasi_ditemukan) || !empty($barang->ciri_khusus))
                            <div class="found-detail-meta">
                                <div>
                                    <span>Detail Lokasi Ditemukan</span>
                                    <strong>{{ $barang->detail_lokasi_ditemukan ?: '-' }}</strong>
                                </div>
                                <div>
                                    <span>Ciri Unik</span>
                                    <strong>{{ $barang->ciri_khusus ?: '-' }}</strong>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </article>

            <div class="found-detail-side">
                <article class="report-card found-detail-panel found-panel-status">
                    <header>
                        <h2>Status Saat Ini</h2>
                    </header>
                    <div class="found-detail-panel-body">
                        <span class="status-chip {{ $reportStatusClass }}">{{ $reportStatusLabel }}</span>

                        <div class="found-verify-box">
                            <small>Verifikasi Laporan</small>
                            <p>Tentukan apakah laporan ini layak ditampilkan di halaman publik.</p>
                            <div class="found-verify-actions">
                                <form method="POST" action="{{ route('admin.found-items.verify', $barang->id) }}" data-confirm-delete data-confirm-title="Setujui Laporan" data-confirm-message="Setujui laporan ini? Laporan akan bisa dipublikasikan ke Home." data-confirm-submit-label="Setujui" data-confirm-submit-variant="primary">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status_laporan" value="approved">
                                    <button type="submit" class="filter-btn found-action-btn found-action-btn-primary">Setujui Laporan</button>
                                </form>
                                <form method="POST" action="{{ route('admin.found-items.verify', $barang->id) }}" data-confirm-delete data-confirm-title="Tolak Laporan" data-confirm-message="Tolak laporan ini? Laporan tidak akan tampil di Home." data-confirm-submit-label="Tolak" data-confirm-submit-variant="danger">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status_laporan" value="rejected">
                                    <button type="submit" class="filter-btn found-action-btn found-action-btn-ghost">Tolak Laporan</button>
                                </form>
                            </div>
                        </div>

                        <span class="status-chip {{ $statusClass }}">{{ $statusLabel }}</span>

                        <form method="POST" action="{{ route('admin.found-items.update-status', $barang->id) }}" class="status-edit-form" id="status-update-form" data-confirm-delete data-confirm-title="Konfirmasi Perbarui Status" data-confirm-submit-label="Perbarui" data-confirm-submit-variant="primary" data-confirm-message="Perbarui status barang temuan ini? Pastikan data sudah sesuai sebelum menyimpan.">
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

                <article class="report-card found-detail-panel found-panel-reporter">
                    <header><h2>Informasi Penemu</h2></header>
                    <div class="found-detail-panel-body">
                        <div class="found-person-row">
                            <span class="found-person-avatar">{{ $penemuInitials ?: ($initials ?: 'US') }}</span>
                            <div>
                                <p><strong>{{ $penemuName }}</strong></p>
                                <small>Pelapor / Penemu</small>
                            </div>
                        </div>
                        <div class="found-contact-actions">
                            <a href="{{ $hubungiHref }}" class="filter-btn {{ $hasPetugasEmail ? '' : 'is-disabled' }}">Hubungi</a>
                            <a href="{{ $emailContactHref }}" class="filter-btn {{ $hasPetugasEmail ? '' : 'is-disabled' }}">Email</a>
                        </div>
                        <p>{{ $penemuContact !== '-' ? ('WA: '.$penemuContact) : $petugasEmail }}</p>
                    </div>
                </article>

                <article class="report-card found-detail-panel found-panel-location">
                    <header><h2>Lokasi &amp; Waktu Penyimpanan</h2></header>
                    <div class="found-detail-panel-body">
                        <div class="found-info-item">
                            <span class="found-info-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 7a1 1 0 0 1 1 1v4.1l2.2 1.47a1 1 0 1 1-1.1 1.66l-2.65-1.76A1 1 0 0 1 11 13V8a1 1 0 0 1 1-1zM12 3a9 9 0 1 1 0 18a9 9 0 0 1 0-18zm0 2a7 7 0 1 0 0 14a7 7 0 0 0 0-14z" fill="currentColor"/></svg>
                            </span>
                            <div>
                                <small>Ditemukan</small>
                                <p><strong>{{ $tanggalDitemukanLabel }}{{ $waktuDitemukanLabel !== '-' ? ', '.$waktuDitemukanLabel : '' }} WIB</strong></p>
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

                <article class="report-card found-detail-panel found-panel-activity">
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

        <article class="report-card mt-3 found-matching-card" id="kandidat-pencocokan">
            <header class="mb-3 found-matching-header">
                <span class="found-matching-header-icon" aria-hidden="true">
                    <i class="fa-solid fa-link"></i>
                </span>
                <div class="found-matching-header-copy">
                    <h2 class="mb-1">Kandidat Laporan Barang Hilang</h2>
                    <p class="mb-0">Sistem menilai kandidat berdasarkan kategori, nama barang, warna, merek, nomor seri, lokasi, tanggal, deskripsi, dan ciri khusus.</p>
                </div>
                <a href="{{ route('admin.found-items.show', $barang->id) }}#kandidat-pencocokan" class="filter-btn found-matching-refresh">
                    <i class="fa-solid fa-rotate-right"></i>Muat Ulang Kandidat
                </a>
            </header>

            @if((string) ($barang->status_laporan ?? '') !== \App\Support\WorkflowStatus::REPORT_APPROVED)
                <div class="found-matching-empty">
                    <span class="found-matching-empty-icon"><i class="fa-solid fa-clock"></i></span>
                    <div>
                        <h3>Kandidat belum tersedia</h3>
                        <p>Kandidat baru akan muncul setelah laporan barang temuan disetujui admin.</p>
                    </div>
                </div>
            @elseif(($matchingCandidates ?? collect())->isEmpty())
                <div class="found-matching-empty">
                    <span class="found-matching-empty-icon"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <div>
                        <h3>Belum ada kandidat kuat</h3>
                        <p>Belum ada kandidat dengan skor kecocokan yang cukup, atau semua kandidat sudah ditinjau.</p>
                    </div>
                </div>
            @else
                <div class="report-table-wrap">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Laporan Hilang</th>
                                <th>Skor</th>
                                <th>Ringkasan</th>
                                <th>Parameter Skor</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($matchingCandidates as $candidate)
                                @php
                                    $laporan = $candidate->laporan;
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
                                            <strong>{{ $laporan->nama_barang }}</strong>
                                            <small style="display:block;">{{ $laporan->lokasi_hilang }} - {{ \Illuminate\Support\Carbon::parse($laporan->tanggal_hilang)->format('d M Y') }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="found-matching-score">
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
                                        <div class="found-matching-metrics">
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
                                        <div class="found-matching-actions">
                                            <form method="POST" action="{{ route('admin.matches.store') }}">
                                                @csrf
                                                <input type="hidden" name="laporan_hilang_id" value="{{ $laporan->id }}">
                                                <input type="hidden" name="barang_id" value="{{ $barang->id }}">
                                                <input type="hidden" name="catatan" value="{{ $catatanSkor }}">
                                                <button type="submit" class="filter-btn found-matching-btn">Tandai Diduga Cocok</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.matches.dismiss') }}">
                                                @csrf
                                                <input type="hidden" name="laporan_hilang_id" value="{{ $laporan->id }}">
                                                <input type="hidden" name="barang_id" value="{{ $barang->id }}">
                                                <input type="hidden" name="catatan" value="{{ 'Skor otomatis: '.$candidate->score.'/100 | Ditandai tidak cocok oleh admin.' }}">
                                                <button type="submit" class="filter-btn found-action-btn found-action-btn-ghost found-matching-btn">Tidak Cocok</button>
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

        <div class="found-detail-bottom-actions">
            <button type="submit" form="status-update-form" class="filter-btn found-action-btn found-action-btn-primary" disabled>Perbarui Status</button>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.body.classList.add('found-detail-page-mode');

            const form = document.getElementById('status-update-form');
            if (!form) return;

            const submitButton = document.querySelector('button[form="status-update-form"][type="submit"]');
            if (!submitButton) return;

            const statusInput = form.querySelector('#status_barang');
            const noteInput = form.querySelector('#catatan_status');

            if (!statusInput || !noteInput || statusInput.disabled || noteInput.disabled) return;

            const initialStatus = statusInput.value;
            const initialNote = noteInput.value;

            const syncSubmitState = function () {
                const hasChanged = statusInput.value !== initialStatus || noteInput.value !== initialNote;
                submitButton.disabled = !hasChanged;
            };

            statusInput.addEventListener('change', syncSubmitState);
            noteInput.addEventListener('input', syncSubmitState);

            form.addEventListener('submit', function () {
                submitButton.disabled = true;
                submitButton.textContent = 'Menyimpan...';
            });

            syncSubmitState();
        });
    </script>
@endsection
