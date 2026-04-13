@extends('admin.layouts.app')

@php
    /** BAGIAN: Meta Halaman */
    $pageTitle = 'Dashboard Admin - SiNemu';
    $activeMenu = 'dashboard';
    $searchAction = route('admin.dashboard');
    $searchPlaceholder = 'Cari laporan atau barang';
@endphp

@section('page-content')
    <div class="dashboard-page-content">
        @if(session('status'))
            <div class="report-card" style="margin-bottom:12px;">
                <header><h2 style="font-size:14px;">{{ session('status') }}</h2></header>
            </div>
        @endif
        @if(session('error'))
            <div class="report-card" style="margin-bottom:12px;">
                <header><h2 style="font-size:14px;color:#b91c1c;">{{ session('error') }}</h2></header>
            </div>
        @endif

        {{-- BAGIAN: Pembuka --}}
        <section class="intro">
            <h1>Ringkasan Dashboard Admin</h1>
            <p>Selamat Datang, {{ $admin?->nama ?? 'Admin' }}! Kelola barang hilang &amp; temuan dengan efisien.</p>
        </section>

        {{-- BAGIAN: Kartu Statistik --}}
        <section class="stats-grid">
            <article class="stat-card">
                <span>Total Laporan Hilang</span>
                <strong>{{ $totalHilang }}</strong>
            </article>
            <article class="stat-card">
                <span>Total Laporan Temuan</span>
                <strong>{{ $totalTemuan }}</strong>
            </article>
            <article class="stat-card">
                <span>Menunggu Verifikasi</span>
                <strong>{{ $menungguVerifikasi }}</strong>
            </article>
        </section>

        {{-- BAGIAN: Tabel Laporan Terbaru --}}
        <section class="report-card dashboard-report-card">
            <header>
                <h2>Laporan Terbaru</h2>
                <div class="report-actions">
                    <button type="button" class="filter-btn">Filter</button>
                    <a href="#">Lihat Semua</a>
                </div>
            </header>

            <div class="report-table-wrap">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Detail Barang</th>
                            <th>Tanggal Laporan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($latestReports as $index => $report)
                            <tr>
                                <td>
                                    <div class="item-cell">
                                        <div class="item-avatar {{ $report->avatar_class ?? '' }}">
                                            <span class="item-avatar-fallback">{{ $report->avatar ?? '?' }}</span>
                                            @if(!empty($report->foto_barang))
                                                @php
                                                    $fotoPath = trim((string) $report->foto_barang, '/');
                                                    [$folder, $subPath] = array_pad(explode('/', $fotoPath, 2), 2, '');
                                                    $fotoUrl = in_array($folder, ['barang-hilang', 'barang-temuan', 'verifikasi-klaim'], true) && $subPath !== ''
                                                        ? route('media.image', ['folder' => $folder, 'path' => $subPath], false)
                                                        : asset('storage/' . $fotoPath);
                                                @endphp
                                                <img
                                                    src="{{ $fotoUrl }}"
                                                    alt="{{ $report->item_name ?? 'Barang' }}"
                                                    loading="lazy"
                                                    decoding="async"
                                                    width="30"
                                                    height="30"
                                                    onerror="this.remove()"
                                                >
                                            @elseif(($report->type ?? null) === 'temuan')
                                                <img
                                                    src="{{ route('media.image', ['folder' => 'barang-temuan', 'path' => 'hp.webp'], false) }}"
                                                    alt="{{ $report->item_name ?? 'Barang' }}"
                                                    loading="lazy"
                                                    decoding="async"
                                                    width="30"
                                                    height="30"
                                                    onerror="this.remove()"
                                                >
                                            @elseif(
                                                ($report->type ?? null) === 'hilang'
                                                && \Illuminate\Support\Str::contains(
                                                    \Illuminate\Support\Str::lower((string) ($report->item_name ?? '')),
                                                    'dompet'
                                                )
                                            )
                                                <img
                                                    src="{{ route('media.image', ['folder' => 'barang-hilang', 'path' => 'dompet.webp'], false) }}"
                                                    alt="{{ $report->item_name ?? 'Barang Hilang' }}"
                                                    loading="lazy"
                                                    decoding="async"
                                                    width="30"
                                                    height="30"
                                                    onerror="this.remove()"
                                                >
                                            @endif
                                        </div>
                                        <div>
                                            <strong>{{ $report->item_name ?? '-' }}</strong>
                                            <small>{{ $report->item_detail ?? '-' }}</small>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <div class="date-cell">
                                        <strong>
                                            {{ !empty($report->incident_date) ? \Carbon\Carbon::parse($report->incident_date)->format('d M Y') : '-' }}
                                        </strong>
                                        <small>
                                            {{ !empty($report->created_at) ? \Carbon\Carbon::parse($report->created_at)->format('H:i') : '-' }} WIB
                                        </small>
                                    </div>
                                </td>

                                <td>
                                    <span class="status-chip status-{{ $report->status ?? 'default' }}">
                                        {{ strtoupper(str_replace('_', ' ', $report->status ?? 'TIDAK DIKETAHUI')) }}
                                    </span>
                                </td>

                                <td class="menu-cell">
                                    <button
                                        type="button"
                                        class="row-menu-trigger"
                                        data-menu-target="menu-{{ $index }}"
                                        aria-label="Aksi"
                                    >
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M12 5.5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3zm0 5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3zm0 5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3z" fill="currentColor"/>
                                        </svg>
                                </button>

                                <div class="row-menu" id="menu-{{ $index }}">
                                        <a href="{{ $report->detail_url ?? $report->target_url }}">Lihat Detail</a>
                                        <button
                                            type="button"
                                            class="row-menu-action"
                                            data-edit-report-trigger
                                            data-report-type="{{ $report->type ?? '' }}"
                                            data-status-label="{{ $report->status_label ?? 'Laporan' }}"
                                            data-item-name="{{ $report->item_name ?? 'laporan ini' }}"
                                            data-update-url="{{ $report->update_url ?? '' }}"
                                            data-nama-barang="{{ $report->edit_nama_barang ?? '' }}"
                                            data-lokasi-hilang="{{ $report->edit_lokasi_hilang ?? '' }}"
                                            data-tanggal-hilang="{{ $report->edit_tanggal_hilang ?? '' }}"
                                            data-keterangan="{{ $report->edit_keterangan ?? '' }}"
                                            data-kategori-id="{{ $report->edit_kategori_id ?? '' }}"
                                            data-deskripsi="{{ $report->edit_deskripsi ?? '' }}"
                                            data-lokasi-ditemukan="{{ $report->edit_lokasi_ditemukan ?? '' }}"
                                            data-tanggal-ditemukan="{{ $report->edit_tanggal_ditemukan ?? '' }}"
                                            data-status-barang="{{ $report->edit_status_barang ?? '' }}"
                                            data-lokasi-pengambilan="{{ $report->edit_lokasi_pengambilan ?? '' }}"
                                            data-alamat-pengambilan="{{ $report->edit_alamat_pengambilan ?? '' }}"
                                            data-penanggung-jawab-pengambilan="{{ $report->edit_penanggung_jawab_pengambilan ?? '' }}"
                                            data-kontak-pengambilan="{{ $report->edit_kontak_pengambilan ?? '' }}"
                                            data-jam-layanan-pengambilan="{{ $report->edit_jam_layanan_pengambilan ?? '' }}"
                                            data-catatan-pengambilan="{{ $report->edit_catatan_pengambilan ?? '' }}"
                                            data-status-klaim="{{ $report->edit_status_klaim ?? '' }}"
                                            data-catatan="{{ $report->edit_catatan ?? '' }}"
                                        >
                                            Edit Laporan
                                        </button>
                                        @if(!empty($report->upload_home_url) && !($report->home_published ?? false))
                                        <form method="POST" action="{{ $report->upload_home_url }}">
                                            @csrf
                                            <button type="submit" class="menu-submit">Upload</button>
                                        </form>
                                        @elseif($report->home_published ?? false)
                                            <span class="row-menu-note">Sudah di-upload</span>
                                        @endif
                                        @if(!empty($report->delete_url))
                                        <form method="POST" action="{{ $report->delete_url }}" data-confirm-delete data-confirm-message="Yakin ingin menghapus data ini?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="menu-submit danger">Hapus</button>
                                        </form>
                                        @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty-row">Belum ada data laporan terbaru.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <footer class="pagination">
                @if($latestReports->onFirstPage())
                    <button type="button" disabled>Sebelumnya</button>
                @else
                    <button type="button" onclick="window.location.href='{{ $latestReports->previousPageUrl() }}'">Sebelumnya</button>
                @endif

                @for($page = 1; $page <= $latestReports->lastPage(); $page++)
                    <button type="button" class="{{ $latestReports->currentPage() === $page ? 'active' : '' }}" onclick="window.location.href='{{ $latestReports->url($page) }}'">{{ $page }}</button>
                @endfor

                @if($latestReports->hasMorePages())
                    <button type="button" onclick="window.location.href='{{ $latestReports->nextPageUrl() }}'">Selanjutnya</button>
                @else
                    <button type="button" disabled>Selanjutnya</button>
                @endif
            </footer>
        </section>
    </div>

    <div class="info-modal-backdrop" id="edit-report-modal-backdrop" hidden>
        <div class="info-modal edit-report-modal-wide" id="edit-report-modal" role="dialog" aria-modal="true" aria-labelledby="edit-report-modal-title">
            <h3 id="edit-report-modal-title">Edit Laporan</h3>
            <p id="edit-report-modal-message">Perbarui data laporan lalu simpan perubahan.</p>
            <form method="POST" id="edit-report-form" enctype="multipart/form-data">
                @csrf
                @method('PATCH')
                <div class="edit-form-grid edit-form-grid-two" id="edit-report-form-fields"></div>
                <div class="confirm-modal-actions">
                    <button type="button" class="confirm-btn-cancel" id="edit-report-modal-cancel">Batal</button>
                    <button type="submit" class="confirm-btn-primary" id="edit-report-modal-submit">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.body.classList.add('dashboard-fixed-mode');

            const editTriggers = document.querySelectorAll('[data-edit-report-trigger]');
            const editModalBackdrop = document.getElementById('edit-report-modal-backdrop');
            const editModalMessage = document.getElementById('edit-report-modal-message');
            const editModalCancel = document.getElementById('edit-report-modal-cancel');
            const editModalForm = document.getElementById('edit-report-form');
            const editFields = document.getElementById('edit-report-form-fields');
            const kategoriOptions = @json(($kategoriOptions ?? collect())->map(fn ($kategori) => ['id' => (int) $kategori->id, 'nama_kategori' => $kategori->nama_kategori])->values());

            function closeEditReportModal() {
                if (!editModalBackdrop) return;
                editModalBackdrop.hidden = true;
                if (editModalForm) {
                    editModalForm.setAttribute('action', '#');
                }
                if (editFields) {
                    editFields.innerHTML = '';
                }
            }

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function renderKategoriOptions(selectedKategoriId) {
                let html = '';
                kategoriOptions.forEach(function (kategori) {
                    const isSelected = String(selectedKategoriId || '') === String(kategori.id) ? 'selected' : '';
                    html += `<option value="${kategori.id}" ${isSelected}>${escapeHtml(kategori.nama_kategori)}</option>`;
                });
                return html;
            }

            function renderLostFields(payload) {
                return `
                    <div class="edit-form-col-full">
                        <label class="edit-form-label" for="edit-nama-barang">Nama Barang</label>
                        <input class="form-input edit-form-input" id="edit-nama-barang" name="nama_barang" type="text" required maxlength="255" value="" placeholder="${escapeHtml(payload.nama_barang)}">
                    </div>

                    <div>
                        <label class="edit-form-label" for="edit-tanggal-hilang">Tanggal Hilang</label>
                        <input class="form-input edit-form-input" id="edit-tanggal-hilang" name="tanggal_hilang" type="date" required value="">
                        <small class="edit-form-help">Tanggal saat ini: ${escapeHtml(payload.tanggal_hilang || '-')}</small>
                    </div>

                    <div>
                        <label class="edit-form-label" for="edit-lokasi-hilang">Lokasi Hilang</label>
                        <input class="form-input edit-form-input" id="edit-lokasi-hilang" name="lokasi_hilang" type="text" required maxlength="255" value="" placeholder="${escapeHtml(payload.lokasi_hilang)}">
                    </div>

                    <div class="edit-form-col-full">
                        <label class="edit-form-label" for="edit-keterangan">Keterangan</label>
                        <textarea class="form-input edit-form-input edit-form-textarea" id="edit-keterangan" name="keterangan" maxlength="2000" placeholder="${escapeHtml(payload.keterangan || 'Tuliskan ciri-ciri barang atau informasi tambahan.')}"></textarea>
                    </div>

                    <div class="edit-form-col-full">
                        <label class="edit-form-label" for="edit-foto-barang">Foto Barang (Opsional)</label>
                        <input class="form-input edit-form-input" id="edit-foto-barang" name="foto_barang" type="file" accept=".jpg,.jpeg,.png,.webp">
                        <small class="edit-form-help">Biarkan kosong jika tidak ingin mengganti foto.</small>
                    </div>
                `;
            }

            function renderFoundFields(payload) {
                const status = payload.status_barang || '';
                const statusLabel = status === 'tersedia'
                    ? 'Tersedia'
                    : status === 'dalam_proses_klaim'
                        ? 'Dalam Proses Klaim'
                        : status === 'sudah_diklaim'
                            ? 'Sudah Diklaim'
                            : status === 'sudah_dikembalikan'
                                ? 'Sudah Dikembalikan'
                                : '-';

                return `
                    <div class="edit-form-col-full">
                        <label class="edit-form-label" for="edit-nama-barang">Nama Barang</label>
                        <input class="form-input edit-form-input" id="edit-nama-barang" name="nama_barang" type="text" required maxlength="255" value="" placeholder="${escapeHtml(payload.nama_barang)}">
                    </div>

                    <div>
                        <label class="edit-form-label" for="edit-kategori-id">Kategori</label>
                        <select class="form-input edit-form-input" id="edit-kategori-id" name="kategori_id">
                            <option value="" selected>Pilih kategori (saat ini: ${escapeHtml(payload.kategori_id || '-')} )</option>
                            ${renderKategoriOptions('')}
                        </select>
                    </div>

                    <div>
                        <label class="edit-form-label" for="edit-status-barang">Status Barang</label>
                        <select class="form-input edit-form-input" id="edit-status-barang" name="status_barang" required>
                            <option value="" selected disabled>Pilih status (saat ini: ${statusLabel})</option>
                            <option value="tersedia">Tersedia</option>
                            <option value="dalam_proses_klaim">Dalam Proses Klaim</option>
                            <option value="sudah_diklaim">Sudah Diklaim</option>
                            <option value="sudah_dikembalikan">Sudah Dikembalikan</option>
                        </select>
                    </div>

                    <div>
                        <label class="edit-form-label" for="edit-tanggal-ditemukan">Tanggal Ditemukan</label>
                        <input class="form-input edit-form-input" id="edit-tanggal-ditemukan" name="tanggal_ditemukan" type="date" required value="">
                        <small class="edit-form-help">Tanggal saat ini: ${escapeHtml(payload.tanggal_ditemukan || '-')}</small>
                    </div>

                    <div>
                        <label class="edit-form-label" for="edit-lokasi-ditemukan">Lokasi Ditemukan</label>
                        <input class="form-input edit-form-input" id="edit-lokasi-ditemukan" name="lokasi_ditemukan" type="text" required maxlength="255" value="" placeholder="${escapeHtml(payload.lokasi_ditemukan)}">
                    </div>

                    <div class="edit-form-col-full">
                        <label class="edit-form-label" for="edit-deskripsi">Deskripsi/Ciri-ciri</label>
                        <textarea class="form-input edit-form-input edit-form-textarea" id="edit-deskripsi" name="deskripsi" maxlength="2000" placeholder="${escapeHtml(payload.deskripsi || 'Tuliskan deskripsi barang.')}"></textarea>
                    </div>

                    <div class="edit-form-col-full edit-form-section-title">Informasi Pengambilan</div>

                    <div>
                        <label class="edit-form-label" for="edit-lokasi-pengambilan">Lokasi Pengambilan</label>
                        <input class="form-input edit-form-input" id="edit-lokasi-pengambilan" name="lokasi_pengambilan" type="text" maxlength="255" value="" placeholder="${escapeHtml(payload.lokasi_pengambilan)}">
                    </div>

                    <div>
                        <label class="edit-form-label" for="edit-alamat-pengambilan">Alamat Pengambilan</label>
                        <input class="form-input edit-form-input" id="edit-alamat-pengambilan" name="alamat_pengambilan" type="text" maxlength="255" value="" placeholder="${escapeHtml(payload.alamat_pengambilan)}">
                    </div>

                    <div>
                        <label class="edit-form-label" for="edit-penanggung-jawab">Penanggung Jawab</label>
                        <input class="form-input edit-form-input" id="edit-penanggung-jawab" name="penanggung_jawab_pengambilan" type="text" maxlength="255" value="" placeholder="${escapeHtml(payload.penanggung_jawab_pengambilan)}">
                    </div>

                    <div>
                        <label class="edit-form-label" for="edit-kontak-pengambilan">Kontak Pengambilan</label>
                        <input class="form-input edit-form-input" id="edit-kontak-pengambilan" name="kontak_pengambilan" type="text" maxlength="255" value="" placeholder="${escapeHtml(payload.kontak_pengambilan)}">
                    </div>

                    <div>
                        <label class="edit-form-label" for="edit-jam-layanan">Jam Layanan</label>
                        <input class="form-input edit-form-input" id="edit-jam-layanan" name="jam_layanan_pengambilan" type="text" maxlength="255" value="" placeholder="${escapeHtml(payload.jam_layanan_pengambilan)}">
                    </div>

                    <div>
                        <label class="edit-form-label" for="edit-catatan-pengambilan">Catatan Pengambilan</label>
                        <textarea class="form-input edit-form-input edit-form-textarea-sm" id="edit-catatan-pengambilan" name="catatan_pengambilan" maxlength="2000" placeholder="${escapeHtml(payload.catatan_pengambilan)}"></textarea>
                    </div>

                    <div class="edit-form-col-full">
                        <label class="edit-form-label" for="edit-foto-barang">Foto Barang (Opsional)</label>
                        <input class="form-input edit-form-input" id="edit-foto-barang" name="foto_barang" type="file" accept=".jpg,.jpeg,.png,.webp">
                        <small class="edit-form-help">Biarkan kosong jika tidak ingin mengganti foto.</small>
                    </div>
                `;
            }

            function renderClaimFields(payload) {
                const status = payload.status_klaim || '';
                const statusLabel = status === 'pending'
                    ? 'Pending'
                    : status === 'disetujui'
                        ? 'Disetujui'
                        : status === 'ditolak'
                            ? 'Ditolak'
                            : '-';

                return `
                    <div>
                        <label class="edit-form-label" for="edit-status-klaim">Status Klaim</label>
                        <select class="form-input edit-form-input" id="edit-status-klaim" name="status_klaim" required>
                            <option value="" selected disabled>Pilih status (saat ini: ${statusLabel})</option>
                            <option value="pending">Pending</option>
                            <option value="disetujui">Disetujui</option>
                            <option value="ditolak">Ditolak</option>
                        </select>
                    </div>

                    <div class="edit-form-col-full">
                        <label class="edit-form-label" for="edit-catatan">Catatan</label>
                        <textarea class="form-input edit-form-input edit-form-textarea" id="edit-catatan" name="catatan" maxlength="2000" placeholder="${escapeHtml(payload.catatan || 'Tambahkan catatan (opsional)')}"></textarea>
                    </div>
                `;
            }

            function openEditReportModal(trigger) {
                if (!editModalBackdrop || !editModalMessage || !editModalForm || !editFields) return;

                const payload = {
                    type: trigger.getAttribute('data-report-type') || '',
                    status_label: trigger.getAttribute('data-status-label') || 'Laporan',
                    item_name: trigger.getAttribute('data-item-name') || 'laporan ini',
                    update_url: trigger.getAttribute('data-update-url') || '',
                    nama_barang: trigger.getAttribute('data-nama-barang') || '',
                    lokasi_hilang: trigger.getAttribute('data-lokasi-hilang') || '',
                    tanggal_hilang: trigger.getAttribute('data-tanggal-hilang') || '',
                    keterangan: trigger.getAttribute('data-keterangan') || '',
                    kategori_id: trigger.getAttribute('data-kategori-id') || '',
                    deskripsi: trigger.getAttribute('data-deskripsi') || '',
                    lokasi_ditemukan: trigger.getAttribute('data-lokasi-ditemukan') || '',
                    tanggal_ditemukan: trigger.getAttribute('data-tanggal-ditemukan') || '',
                    status_barang: trigger.getAttribute('data-status-barang') || '',
                    lokasi_pengambilan: trigger.getAttribute('data-lokasi-pengambilan') || '',
                    alamat_pengambilan: trigger.getAttribute('data-alamat-pengambilan') || '',
                    penanggung_jawab_pengambilan: trigger.getAttribute('data-penanggung-jawab-pengambilan') || '',
                    kontak_pengambilan: trigger.getAttribute('data-kontak-pengambilan') || '',
                    jam_layanan_pengambilan: trigger.getAttribute('data-jam-layanan-pengambilan') || '',
                    catatan_pengambilan: trigger.getAttribute('data-catatan-pengambilan') || '',
                    status_klaim: trigger.getAttribute('data-status-klaim') || '',
                    catatan: trigger.getAttribute('data-catatan') || '',
                };

                const reportType = payload.type || '';
                const updateUrl = payload.update_url || '';
                const itemName = payload.item_name || 'laporan ini';
                const itemType = payload.status_label || 'Laporan';

                if (!reportType || !updateUrl) return;

                editModalForm.setAttribute('action', updateUrl);
                editModalMessage.textContent = `Edit data untuk ${itemType}: ${itemName}`;

                if (reportType === 'hilang') {
                    editFields.innerHTML = renderLostFields(payload);
                } else if (reportType === 'temuan') {
                    editFields.innerHTML = renderFoundFields(payload);
                } else if (reportType === 'klaim') {
                    editFields.innerHTML = renderClaimFields(payload);
                } else {
                    editFields.innerHTML = '';
                }

                editModalBackdrop.hidden = false;
            }

            editTriggers.forEach(function (trigger) {
                trigger.addEventListener('click', function (event) {
                    event.preventDefault();

                    document.querySelectorAll('.row-menu.open').forEach(function (menu) {
                        menu.classList.remove('open', 'open-up', 'open-down');
                    });

                    openEditReportModal(trigger);
                });
            });

            editModalCancel?.addEventListener('click', function () {
                closeEditReportModal();
            });

            // Modal hanya ditutup lewat tombol "Batal" agar form tidak tertutup tidak sengaja.
        });
    </script>
@endsection
