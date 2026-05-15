@extends('user.layouts.app')

@php
    $managerRoleLabelLower = \App\Support\RoleLabels::managerLower();
    $pageTitle = 'Ajukan Klaim Barang - SiNemu';
    $activeMenu = 'claim-history';
    $searchAction = route('user.dashboard');
    $searchPlaceholder = 'Cari di dashboard';
    $selectedBarangIdValue = (string) ($selectedBarangId ?? '');
@endphp

@section('page-content')
    <div class="input-page-content">
        <section class="intro">
            <h1>Ajukan Klaim Barang</h1>
            <p>Lengkapi bukti kepemilikan dengan rinci. {{ \Illuminate\Support\Str::ucfirst($managerRoleLabelLower) }} akan memverifikasi klaim sebelum barang dapat diserahkan.</p>
        </section>

        <section class="input-card">
            @if(($foundItems ?? collect())->isEmpty() || ($claimableLostReports ?? collect())->isEmpty())
                <div class="claim-create-empty">
                    <strong>Belum ada data yang bisa diklaim</strong>
                    <p>Pastikan Anda sudah memiliki laporan barang hilang tervalidasi dan barang yang cocok tersedia untuk klaim.</p>
                    <div class="claim-create-empty-actions">
                        <a href="{{ route('user.lost-reports.create') }}" class="btn-primary">Buat Laporan Hilang</a>
                        <a href="{{ route('home') }}#hilang-temuan" class="btn-secondary">Lihat Barang Temuan</a>
                    </div>
                </div>
            @else
                <form method="POST" action="{{ route('user.claims.store') }}" class="input-form" enctype="multipart/form-data" novalidate>
                    @csrf

                    <div class="form-col-12 form-group">
                        <label class="form-label" for="barang_id">Pilih Barang Temuan <span>*</span></label>
                        <select id="barang_id" name="barang_id" class="form-input" required>
                            <option value="">Pilih barang</option>
                            @foreach($foundItems as $barang)
                                @php
                                    $barangId = (string) $barang->id;
                                    $barangDateLabel = !empty($barang->tanggal_ditemukan)
                                        ? \Illuminate\Support\Carbon::parse((string) $barang->tanggal_ditemukan)->format('d/m/Y')
                                        : '-';
                                @endphp
                                <option
                                    value="{{ $barangId }}"
                                    data-barang-name="{{ $barang->nama_barang }}"
                                    data-barang-category="{{ $barang->kategori?->nama_kategori ?? 'Umum' }}"
                                    data-barang-location="{{ $barang->lokasi_ditemukan }}"
                                    data-barang-date="{{ $barangDateLabel }}"
                                    @selected((string) old('barang_id', $selectedBarangIdValue) === $barangId)
                                >
                                    {{ $barang->nama_barang }} - {{ $barang->lokasi_ditemukan }} ({{ $barangDateLabel }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-col-12 form-group">
                        <div id="claimItemSummary" class="claim-create-summary" hidden>
                            <div class="claim-create-summary-title">Ringkasan Barang Temuan</div>
                            <div class="claim-create-summary-row"><span>Nama</span><strong id="claimItemSummaryName">-</strong></div>
                            <div class="claim-create-summary-row"><span>Kategori</span><strong id="claimItemSummaryCategory">-</strong></div>
                            <div class="claim-create-summary-row"><span>Lokasi</span><strong id="claimItemSummaryLocation">-</strong></div>
                            <div class="claim-create-summary-row"><span>Tanggal Temuan</span><strong id="claimItemSummaryDate">-</strong></div>
                        </div>
                    </div>

                    <div class="form-col-12 form-group">
                        <label class="form-label" for="laporan_hilang_id">Pilih Laporan Barang Hilang Anda <span>*</span></label>
                        <select name="laporan_hilang_id" id="laporan_hilang_id" class="form-input" required>
                            <option value="">Pilih laporan Anda</option>
                            @foreach($claimableLostReports as $report)
                                @php
                                    $reportDateLabel = !empty($report->tanggal_hilang)
                                        ? \Illuminate\Support\Carbon::parse((string) $report->tanggal_hilang)->format('d/m/Y')
                                        : '-';
                                @endphp
                                <option
                                    value="{{ $report->id }}"
                                    data-report-name="{{ $report->nama_barang }}"
                                    data-report-location="{{ $report->lokasi_hilang }}"
                                    data-report-date="{{ $reportDateLabel }}"
                                    data-report-contact="{{ $report->kontak_pelapor ?? '' }}"
                                    data-report-ownership="{{ $report->bukti_kepemilikan ?? '' }}"
                                    data-report-ciri="{{ $report->ciri_khusus ?? '' }}"
                                    data-report-detail-location="{{ $report->detail_lokasi_hilang ?? '' }}"
                                    data-report-time="{{ $report->waktu_hilang ?? '' }}"
                                    @selected((string) old('laporan_hilang_id') === (string) $report->id)
                                >
                                    {{ $report->nama_barang }} - {{ $report->lokasi_hilang }} ({{ $reportDateLabel }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-col-12 form-group">
                        <div id="claimLostReportSummary" class="claim-create-summary" hidden>
                            <div class="claim-create-summary-title">Ringkasan Laporan Hilang</div>
                            <div class="claim-create-summary-row"><span>Barang</span><strong id="claimSummaryName">-</strong></div>
                            <div class="claim-create-summary-row"><span>Lokasi</span><strong id="claimSummaryLocation">-</strong></div>
                            <div class="claim-create-summary-row"><span>Tanggal</span><strong id="claimSummaryDate">-</strong></div>
                        </div>
                    </div>

                    <div class="form-col-6 form-group">
                        <label class="form-label" for="claimKontakPelapor">No. WA yang Bisa Dihubungi <span>*</span></label>
                        <input type="text" name="kontak_pelapor" id="claimKontakPelapor" class="form-input" value="{{ old('kontak_pelapor', auth()->user()?->nomor_telepon) }}" placeholder="Contoh: 081234567890" required>
                    </div>

                    <div class="form-col-12 form-group">
                        <label class="form-label" for="claimBuktiKepemilikan">Bukti Kepemilikan <span>*</span></label>
                        <textarea name="bukti_kepemilikan" id="claimBuktiKepemilikan" class="form-input form-textarea" rows="3" placeholder="Tuliskan bukti yang hanya pemilik asli tahu: isi barang, foto saat dipakai, nomor seri, nota, atau detail lain." required>{{ old('bukti_kepemilikan') }}</textarea>
                    </div>

                    <div class="form-col-12 form-group">
                        <label class="form-label" for="claimBuktiCiriKhusus">Ciri Unik Barang yang Anda Ketahui <span>*</span></label>
                        <textarea name="bukti_ciri_khusus" id="claimBuktiCiriKhusus" class="form-input form-textarea" rows="2" placeholder="Contoh: ada stiker, goresan, ukiran nama, aksesoris khusus." required>{{ old('bukti_ciri_khusus') }}</textarea>
                    </div>

                    <div class="form-col-12 form-group">
                        <label class="form-label" for="claimBuktiDetailIsi">Detail Isi / Kondisi Saat Hilang</label>
                        <textarea name="bukti_detail_isi" id="claimBuktiDetailIsi" class="form-input form-textarea" rows="2" placeholder="Contoh: isi tas, casing, wallpaper, atau detail kondisi terakhir.">{{ old('bukti_detail_isi') }}</textarea>
                    </div>

                    <div class="form-col-6 form-group">
                        <label class="form-label" for="claimBuktiLokasiSpesifik">Lokasi Spesifik Hilang <span>*</span></label>
                        <input type="text" name="bukti_lokasi_spesifik" id="claimBuktiLokasiSpesifik" class="form-input" value="{{ old('bukti_lokasi_spesifik') }}" placeholder="Contoh: meja pojok kanan perpustakaan" required>
                    </div>

                    <div class="form-col-6 form-group">
                        <label class="form-label" for="claimBuktiWaktuHilang">Perkiraan Waktu Hilang <span>*</span></label>
                        <input type="time" name="bukti_waktu_hilang" id="claimBuktiWaktuHilang" class="form-input" value="{{ old('bukti_waktu_hilang') }}" required>
                    </div>

                    <div class="form-col-12 form-group">
                        <label class="form-label" for="claimBuktiFoto">Foto Bukti Kepemilikan <span>*</span></label>
                        <input type="file" name="bukti_foto[]" id="claimBuktiFoto" class="form-input" accept="image/jpeg,image/png,image/webp" multiple required>
                        <small class="form-note">Unggah 1-3 foto (JPG, PNG, WEBP), maksimal 2MB per file.</small>
                        <div id="claimBuktiPreview" class="claim-create-file-preview" aria-live="polite"></div>
                    </div>

                    <div class="form-col-12 form-group">
                        <label class="form-label" for="claimCatatan">Catatan Klaim</label>
                        <textarea name="catatan" id="claimCatatan" class="form-input form-textarea" rows="2" placeholder="Tambahkan keterangan pendukung jika diperlukan.">{{ old('catatan') }}</textarea>
                    </div>

                    <div class="form-col-12 form-group">
                        <label class="claim-create-check">
                            <input type="checkbox" id="claimPersetujuanKlaim" name="persetujuan_klaim" value="1" @checked(old('persetujuan_klaim')) required>
                            <span>Saya menyatakan data klaim ini benar dan siap diverifikasi oleh {{ $managerRoleLabelLower }}. Saya akan memantau hasilnya di Riwayat Klaim.</span>
                        </label>
                    </div>

                    <div class="form-col-12 form-actions claim-create-actions">
                        <a href="{{ route('home') }}#hilang-temuan" class="btn-secondary">Batal</a>
                        <button id="claimSubmitButton" type="submit" class="btn-primary" @disabled(!old('persetujuan_klaim'))>
                            Ajukan Klaim
                        </button>
                    </div>
                </form>
            @endif
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.body.classList.add('input-page-mode');

            const barangSelect = document.getElementById('barang_id');
            const itemSummary = document.getElementById('claimItemSummary');
            const itemName = document.getElementById('claimItemSummaryName');
            const itemCategory = document.getElementById('claimItemSummaryCategory');
            const itemLocation = document.getElementById('claimItemSummaryLocation');
            const itemDate = document.getElementById('claimItemSummaryDate');
            const reportSelect = document.getElementById('laporan_hilang_id');
            const reportSummary = document.getElementById('claimLostReportSummary');
            const reportName = document.getElementById('claimSummaryName');
            const reportLocation = document.getElementById('claimSummaryLocation');
            const reportDate = document.getElementById('claimSummaryDate');
            const kontakInput = document.getElementById('claimKontakPelapor');
            const buktiKepemilikan = document.getElementById('claimBuktiKepemilikan');
            const buktiCiri = document.getElementById('claimBuktiCiriKhusus');
            const buktiLokasi = document.getElementById('claimBuktiLokasiSpesifik');
            const buktiWaktu = document.getElementById('claimBuktiWaktuHilang');
            const buktiFoto = document.getElementById('claimBuktiFoto');
            const buktiPreview = document.getElementById('claimBuktiPreview');
            const consent = document.getElementById('claimPersetujuanKlaim');
            const submitButton = document.getElementById('claimSubmitButton');

            const updateSubmitState = function () {
                if (!submitButton) return;
                submitButton.disabled = !(consent && consent.checked);
            };

            const updateItemSummary = function () {
                if (!barangSelect || !itemSummary) return;
                const option = barangSelect.options[barangSelect.selectedIndex];
                if (!option || !option.value) {
                    itemSummary.hidden = true;
                    if (itemName) itemName.textContent = '-';
                    if (itemCategory) itemCategory.textContent = '-';
                    if (itemLocation) itemLocation.textContent = '-';
                    if (itemDate) itemDate.textContent = '-';
                    return;
                }

                if (itemName) itemName.textContent = option.dataset.barangName || '-';
                if (itemCategory) itemCategory.textContent = option.dataset.barangCategory || '-';
                if (itemLocation) itemLocation.textContent = option.dataset.barangLocation || '-';
                if (itemDate) itemDate.textContent = option.dataset.barangDate || '-';
                itemSummary.hidden = false;
            };

            const updateReportSummary = function () {
                if (!reportSelect || !reportSummary) return;
                const option = reportSelect.options[reportSelect.selectedIndex];
                if (!option || !option.value) {
                    reportSummary.hidden = true;
                    if (reportName) reportName.textContent = '-';
                    if (reportLocation) reportLocation.textContent = '-';
                    if (reportDate) reportDate.textContent = '-';
                    return;
                }

                if (reportName) reportName.textContent = option.dataset.reportName || '-';
                if (reportLocation) reportLocation.textContent = option.dataset.reportLocation || '-';
                if (reportDate) reportDate.textContent = option.dataset.reportDate || '-';
                reportSummary.hidden = false;

                if (kontakInput && kontakInput.value.trim() === '' && (option.dataset.reportContact || '') !== '') {
                    kontakInput.value = option.dataset.reportContact || '';
                }
                if (buktiKepemilikan && buktiKepemilikan.value.trim() === '' && (option.dataset.reportOwnership || '') !== '') {
                    buktiKepemilikan.value = option.dataset.reportOwnership || '';
                }
                if (buktiCiri && buktiCiri.value.trim() === '' && (option.dataset.reportCiri || '') !== '') {
                    buktiCiri.value = option.dataset.reportCiri || '';
                }
                if (buktiLokasi && buktiLokasi.value.trim() === '' && (option.dataset.reportDetailLocation || '') !== '') {
                    buktiLokasi.value = option.dataset.reportDetailLocation || '';
                }
                if (buktiWaktu && buktiWaktu.value.trim() === '' && (option.dataset.reportTime || '') !== '') {
                    buktiWaktu.value = option.dataset.reportTime || '';
                }
            };

            const renderFilePreview = function (files) {
                if (!buktiPreview) return;
                buktiPreview.innerHTML = '';
                Array.from(files || []).forEach(function (file) {
                    const chip = document.createElement('span');
                    chip.className = 'claim-create-file-chip';
                    chip.textContent = file.name;
                    buktiPreview.appendChild(chip);
                });
            };

            barangSelect?.addEventListener('change', updateItemSummary);
            reportSelect?.addEventListener('change', updateReportSummary);
            consent?.addEventListener('change', updateSubmitState);
            buktiFoto?.addEventListener('change', function () {
                renderFilePreview(this.files);
            });

            updateItemSummary();
            updateReportSummary();
            updateSubmitState();
            renderFilePreview(buktiFoto?.files);
        });
    </script>
@endsection
