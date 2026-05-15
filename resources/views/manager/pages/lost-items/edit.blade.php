@extends('manager::layouts.app')

@php
    $pageTitle = 'Edit Data Barang Hilang - SiNemu';
    $activeMenu = 'lost-items';
    $hideSearch = true;
    $hideSidebar = true;
    $topbarBackUrl = manager_route('lost-items');
    $topbarBackLabel = 'Kembali ke Daftar Barang Hilang';
    $fotoPath = trim((string) ($laporanBarangHilang->foto_barang ?? ''), '/');
    [$folder, $subPath] = array_pad(explode('/', $fotoPath, 2), 2, '');
    $fotoUrl = !empty($fotoPath) && in_array($folder, ['barang-hilang', 'barang-temuan', 'verifikasi-klaim'], true) && $subPath !== ''
        ? route('media.image', ['folder' => $folder, 'path' => $subPath])
        : route('media.image', ['folder' => 'barang-temuan', 'path' => 'hp.webp']);
    $fotoDataUri = null;
    if (!empty($fotoPath) && \Illuminate\Support\Facades\Storage::disk('public')->exists($fotoPath)) {
        $absoluteFotoPath = \Illuminate\Support\Facades\Storage::disk('public')->path($fotoPath);
        $fotoBinary = @file_get_contents($absoluteFotoPath);
        if ($fotoBinary !== false) {
            $mimeType = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($fotoPath) ?: 'image/jpeg';
            $fotoDataUri = 'data:' . $mimeType . ';base64,' . base64_encode($fotoBinary);
        }
    }
    $createdAtLabel = !empty($laporanBarangHilang->created_at)
        ? \Illuminate\Support\Carbon::parse($laporanBarangHilang->created_at)->format('d M Y, H:i')
        : '-';
    $updatedAtLabel = !empty($laporanBarangHilang->updated_at)
        ? \Illuminate\Support\Carbon::parse($laporanBarangHilang->updated_at)->format('d M Y, H:i')
        : '-';
@endphp

@section('page-content')
    <section class="edit-report-page">
<div class="edit-report-top">
            <div class="edit-report-header">
                <p class="edit-report-breadcrumb">
                    <a href="{{ manager_route('lost-items') }}">Daftar Barang Hilang</a>
                    <span>/</span>
                    <strong>Edit Data</strong>
                </p>
                <h1>Edit Data Barang Hilang</h1>
                <p class="edit-report-subtitle">Perbarui data laporan barang hilang lalu simpan perubahan.</p>
                <div class="edit-report-meta">
                    <span>Laporan #{{ $laporanBarangHilang->id }}</span>
                    <span>Dibuat {{ $createdAtLabel }} WIB</span>
                    <span>Diperbarui {{ $updatedAtLabel }} WIB</span>
                </div>
            </div>

            <aside class="edit-report-summary">
                <span class="edit-summary-label">Foto Saat Ini</span>
                <div class="edit-summary-photo-wrap">
                    <img id="editCurrentPhotoPreview" src="{{ $fotoDataUri ?? $fotoUrl }}" alt="{{ $laporanBarangHilang->nama_barang }}" onerror="this.onerror=null;this.src='{{ route('media.image', ['folder' => 'barang-temuan', 'path' => 'hp.webp']) }}';">
                </div>
                <div class="edit-summary-grid">
                    <div class="edit-summary-item">
                        <small>Tanggal Hilang</small>
                        <strong>{{ !empty($laporanBarangHilang->tanggal_hilang) ? \Illuminate\Support\Carbon::parse($laporanBarangHilang->tanggal_hilang)->format('d M Y') : '-' }}</strong>
                    </div>
                    <div class="edit-summary-item">
                        <small>Lokasi</small>
                        <strong>{{ $laporanBarangHilang->lokasi_hilang ?: '-' }}</strong>
                    </div>
                </div>
            </aside>
        </div>

        <section class="report-card edit-report-card">
            <header>
                <h2>Form Edit Data Barang Hilang</h2>
                <p>Laporan #{{ $laporanBarangHilang->id }}</p>
            </header>

            <form method="POST" action="{{ manager_route('lost-items.update', $laporanBarangHilang->id) }}" enctype="multipart/form-data" class="edit-report-form">
                @csrf
                @method('PATCH')

                <div class="edit-form-section">
                    <h3>Informasi Laporan</h3>
                    <div class="edit-form-grid edit-form-grid-two">
                        <div class="edit-form-col-full">
                            <label class="edit-form-label" for="nama_barang">Nama Barang</label>
                            <input class="form-input edit-form-input" id="nama_barang" name="nama_barang" type="text" required maxlength="255" value="{{ old('nama_barang', $laporanBarangHilang->nama_barang) }}">
                        </div>

                        <div>
                            <label class="edit-form-label" for="kategori_barang">Kategori Barang</label>
                            @php
                                $selectedLostCategory = (string) old('kategori_barang', $laporanBarangHilang->kategori_barang);
                                $lostCategoryNames = collect($lostCategoryOptions ?? [])->map(fn ($name) => trim((string) $name))->filter()->values();
                                $hasSelectedLostCategory = $selectedLostCategory !== '' && $lostCategoryNames->contains($selectedLostCategory);
                            @endphp
                            <select class="form-input edit-form-input" id="kategori_barang" name="kategori_barang">
                                <option value="">Pilih kategori</option>
                                @foreach($lostCategoryNames as $categoryName)
                                    <option value="{{ $categoryName }}" @selected($selectedLostCategory === $categoryName)>{{ $categoryName }}</option>
                                @endforeach
                                @if($selectedLostCategory !== '' && !$hasSelectedLostCategory)
                                    <option value="{{ $selectedLostCategory }}" selected>{{ $selectedLostCategory }}</option>
                                @endif
                            </select>
                        </div>

                        <div>
                            <label class="edit-form-label" for="warna_barang">Warna Dominan</label>
                            <input class="form-input edit-form-input" id="warna_barang" name="warna_barang" type="text" maxlength="100" value="{{ old('warna_barang', $laporanBarangHilang->warna_barang) }}">
                        </div>

                        <div>
                            <label class="edit-form-label" for="merek_barang">Merek / Brand</label>
                            <input class="form-input edit-form-input" id="merek_barang" name="merek_barang" type="text" maxlength="120" value="{{ old('merek_barang', $laporanBarangHilang->merek_barang) }}">
                        </div>

                        <div>
                            <label class="edit-form-label" for="nomor_seri">Nomor Seri / Kode Unik</label>
                            <input class="form-input edit-form-input" id="nomor_seri" name="nomor_seri" type="text" maxlength="150" value="{{ old('nomor_seri', $laporanBarangHilang->nomor_seri) }}">
                        </div>

                        <div>
                            <label class="edit-form-label" for="tanggal_hilang">Tanggal Hilang</label>
                            <input class="form-input edit-form-input" id="tanggal_hilang" name="tanggal_hilang" type="date" required value="{{ old('tanggal_hilang', !empty($laporanBarangHilang->tanggal_hilang) ? \Illuminate\Support\Carbon::parse($laporanBarangHilang->tanggal_hilang)->format('Y-m-d') : '') }}">
                        </div>

                        <div>
                            <label class="edit-form-label" for="waktu_hilang">Perkiraan Jam Hilang</label>
                            <input class="form-input edit-form-input" id="waktu_hilang" name="waktu_hilang" type="time" value="{{ old('waktu_hilang', $laporanBarangHilang->waktu_hilang) }}">
                        </div>

                        <div>
                            <label class="edit-form-label" for="lokasi_hilang">Lokasi Hilang</label>
                            <input class="form-input edit-form-input" id="lokasi_hilang" name="lokasi_hilang" type="text" required maxlength="255" value="{{ old('lokasi_hilang', $laporanBarangHilang->lokasi_hilang) }}">
                        </div>

                        <div class="edit-form-col-full">
                            <label class="edit-form-label" for="detail_lokasi_hilang">Detail Lokasi Hilang</label>
                            <textarea class="form-input edit-form-input edit-form-textarea" id="detail_lokasi_hilang" name="detail_lokasi_hilang" maxlength="2000">{{ old('detail_lokasi_hilang', $laporanBarangHilang->detail_lokasi_hilang) }}</textarea>
                        </div>

                        <div class="edit-form-col-full">
                            <label class="edit-form-label" for="keterangan">Deskripsi Barang dan Kronologi Singkat</label>
                            <textarea class="form-input edit-form-input edit-form-textarea" id="keterangan" name="keterangan" maxlength="2000" required>{{ old('keterangan', $laporanBarangHilang->keterangan) }}</textarea>
                            <small class="edit-form-help">Tambahkan ciri khas barang (warna, merek, nomor seri, atau detail pembeda).</small>
                            <small class="edit-form-counter" id="keterangan_counter" aria-live="polite">0/2000 karakter</small>
                        </div>

                        <div class="edit-form-col-full">
                            <label class="edit-form-label" for="ciri_khusus">Ciri Unik Barang</label>
                            <textarea class="form-input edit-form-input edit-form-textarea" id="ciri_khusus" name="ciri_khusus" maxlength="2000">{{ old('ciri_khusus', $laporanBarangHilang->ciri_khusus) }}</textarea>
                        </div>

                        <div>
                            <label class="edit-form-label" for="kontak_pelapor">No. WA Pelapor</label>
                            <input class="form-input edit-form-input" id="kontak_pelapor" name="kontak_pelapor" type="text" maxlength="50" value="{{ old('kontak_pelapor', $laporanBarangHilang->kontak_pelapor) }}">
                        </div>

                        <div class="edit-form-col-full">
                            <label class="edit-form-label" for="bukti_kepemilikan">Bukti Kepemilikan (Catatan)</label>
                            <textarea class="form-input edit-form-input edit-form-textarea" id="bukti_kepemilikan" name="bukti_kepemilikan" maxlength="2000">{{ old('bukti_kepemilikan', $laporanBarangHilang->bukti_kepemilikan) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="edit-form-section">
                    <h3>Media</h3>
                    <div class="edit-form-grid">
                        <label class="edit-form-label" for="foto_barang">Foto Barang (Opsional)</label>
                        <input class="form-input edit-form-input" id="foto_barang" name="foto_barang" type="file" accept="image/jpeg,image/png,image/webp">
                        <small class="edit-form-help">Biarkan kosong jika tidak ingin mengganti foto. Foto Saat Ini tetap foto kiriman user sampai perubahan disimpan.</small>
                        <small class="edit-form-file-name" id="foto_barang_filename">Belum ada file dipilih.</small>
                    </div>
                </div>

                <div class="edit-report-form-actions">
                    <a href="{{ manager_route('lost-items.show', $laporanBarangHilang->id) }}" class="filter-btn">Batal</a>
                    <button type="submit" class="filter-btn primary">Simpan Perubahan</button>
                </div>
            </form>
        </section>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const photoInput = document.getElementById('foto_barang');
            const photoFileName = document.getElementById('foto_barang_filename');
            const keterangan = document.getElementById('keterangan');
            const keteranganCounter = document.getElementById('keterangan_counter');
            const maxKeterangan = Number(keterangan?.getAttribute('maxlength') || 2000);

            function syncKeteranganCounter() {
                if (!keterangan || !keteranganCounter) return;
                const count = keterangan.value.length;
                keteranganCounter.textContent = `${count}/${maxKeterangan} karakter`;
            }

            if (keterangan) {
                syncKeteranganCounter();
                keterangan.addEventListener('input', syncKeteranganCounter);
            }

            if (!photoInput) return;

            photoInput.addEventListener('change', function () {
                const file = photoInput.files?.[0];
                if (!file) {
                    if (photoFileName) photoFileName.textContent = 'Belum ada file dipilih.';
                    return;
                }

                if (photoFileName) {
                    photoFileName.textContent = `File dipilih: ${file.name}`;
                }
            });
        });
    </script>
@endsection
