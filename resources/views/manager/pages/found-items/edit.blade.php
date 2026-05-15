@extends('manager::layouts.app')

@php
    $pageTitle = 'Edit Data Barang Temuan - SiNemu';
    $activeMenu = 'found-items';
    $hideSearch = true;
    $hideSidebar = true;
    $topbarBackUrl = manager_route('found-items');
    $topbarBackLabel = 'Kembali ke Daftar Barang Temuan';
    $statusOptionLabels = [
        'tersedia' => 'Tersedia',
        'dalam_proses_klaim' => 'Dalam Proses Klaim',
        'sudah_diklaim' => 'Sudah Diklaim',
        'sudah_dikembalikan' => 'Selesai',
    ];
    $statusSekarang = $statusOptionLabels[$barang->status_barang] ?? 'Tidak diketahui';
    $fotoPath = trim((string) ($barang->foto_barang ?? ''), '/');
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
    $createdAtLabel = !empty($barang->created_at)
        ? \Illuminate\Support\Carbon::parse($barang->created_at)->format('d M Y, H:i')
        : '-';
    $updatedAtLabel = !empty($barang->updated_at)
        ? \Illuminate\Support\Carbon::parse($barang->updated_at)->format('d M Y, H:i')
        : '-';
@endphp

@section('page-content')
    <section class="edit-report-page">
<div class="edit-report-top">
            <div class="edit-report-header">
                <p class="edit-report-breadcrumb">
                    <a href="{{ manager_route('found-items') }}">Daftar Barang Temuan</a>
                    <span>/</span>
                    <strong>Edit Data</strong>
                </p>
                <h1>Edit Data Barang Temuan</h1>
                <p class="edit-report-subtitle">Perbarui data laporan barang temuan lalu simpan perubahan.</p>
                <div class="edit-report-meta">
                    <span>Laporan #{{ $barang->id }}</span>
                    <span>Dibuat {{ $createdAtLabel }} WIB</span>
                    <span>Diperbarui {{ $updatedAtLabel }} WIB</span>
                </div>
            </div>

            <aside class="edit-report-summary">
                <span class="edit-summary-label">Foto Saat Ini</span>
                <div class="edit-summary-photo-wrap">
                    <img id="editCurrentPhotoPreview" src="{{ $fotoDataUri ?? $fotoUrl }}" alt="{{ $barang->nama_barang }}" onerror="this.onerror=null;this.src='{{ route('media.image', ['folder' => 'barang-temuan', 'path' => 'hp.webp']) }}';">
                </div>
                <div class="edit-summary-grid">
                    <div class="edit-summary-item">
                        <small>Status</small>
                        <strong>{{ $statusSekarang }}</strong>
                    </div>
                    <div class="edit-summary-item">
                        <small>Kategori</small>
                        <strong>{{ $barang->kategori?->nama_kategori ?? 'Tanpa Kategori' }}</strong>
                    </div>
                </div>
            </aside>
        </div>

        <section class="report-card edit-report-card">
            <header>
                <h2>Form Edit Data Barang Temuan</h2>
                <p>Laporan #{{ $barang->id }}</p>
            </header>

            <form method="POST" action="{{ manager_route('found-items.update', $barang->id) }}" enctype="multipart/form-data" class="edit-report-form">
                @csrf
                @method('PATCH')

                <div class="edit-form-section">
                    <h3>Informasi Utama</h3>
                    <div class="edit-form-grid edit-form-grid-two">
                        <div class="edit-form-col-full">
                            <label class="edit-form-label" for="nama_barang">Nama Barang</label>
                            <input class="form-input edit-form-input" id="nama_barang" name="nama_barang" type="text" required maxlength="255" value="{{ old('nama_barang', $barang->nama_barang) }}">
                        </div>

                        <div>
                            <label class="edit-form-label" for="kategori_id">Kategori</label>
                            <select class="form-input edit-form-input" id="kategori_id" name="kategori_id">
                                <option value="">Pilih kategori</option>
                                @foreach($kategoriOptions as $kategori)
                                    <option value="{{ $kategori->id }}" @selected((string) old('kategori_id', $barang->kategori_id) === (string) $kategori->id)>{{ $kategori->nama_kategori }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="edit-form-label" for="warna_barang">Warna Dominan</label>
                            <input class="form-input edit-form-input" id="warna_barang" name="warna_barang" type="text" maxlength="100" value="{{ old('warna_barang', $barang->warna_barang) }}">
                        </div>

                        <div>
                            <label class="edit-form-label" for="merek_barang">Merek / Brand</label>
                            <input class="form-input edit-form-input" id="merek_barang" name="merek_barang" type="text" maxlength="120" value="{{ old('merek_barang', $barang->merek_barang) }}">
                        </div>

                        <div>
                            <label class="edit-form-label" for="nomor_seri">Nomor Seri / Kode Unik</label>
                            <input class="form-input edit-form-input" id="nomor_seri" name="nomor_seri" type="text" maxlength="150" value="{{ old('nomor_seri', $barang->nomor_seri) }}">
                        </div>

                        <div>
                            <label class="edit-form-label" for="tanggal_ditemukan">Tanggal Ditemukan</label>
                            <input class="form-input edit-form-input" id="tanggal_ditemukan" name="tanggal_ditemukan" type="date" required value="{{ old('tanggal_ditemukan', !empty($barang->tanggal_ditemukan) ? \Illuminate\Support\Carbon::parse($barang->tanggal_ditemukan)->format('Y-m-d') : '') }}">
                        </div>

                        <div>
                            <label class="edit-form-label" for="waktu_ditemukan">Perkiraan Jam Ditemukan</label>
                            <input class="form-input edit-form-input" id="waktu_ditemukan" name="waktu_ditemukan" type="time" value="{{ old('waktu_ditemukan', !empty($barang->waktu_ditemukan) ? date('H:i', strtotime($barang->waktu_ditemukan)) : '') }}">
                        </div>

                        <div>
                            <label class="edit-form-label" for="lokasi_ditemukan">Lokasi Ditemukan</label>
                            <input class="form-input edit-form-input" id="lokasi_ditemukan" name="lokasi_ditemukan" type="text" required maxlength="255" value="{{ old('lokasi_ditemukan', $barang->lokasi_ditemukan) }}">
                        </div>

                        <div class="edit-form-col-full">
                            <label class="edit-form-label" for="detail_lokasi_ditemukan">Detail Lokasi Ditemukan</label>
                            <textarea class="form-input edit-form-input edit-form-textarea" id="detail_lokasi_ditemukan" name="detail_lokasi_ditemukan" maxlength="2000">{{ old('detail_lokasi_ditemukan', $barang->detail_lokasi_ditemukan) }}</textarea>
                        </div>

                        <div class="edit-form-col-full">
                            <label class="edit-form-label" for="deskripsi">Deskripsi/Ciri-ciri</label>
                            <textarea class="form-input edit-form-input edit-form-textarea" id="deskripsi" name="deskripsi" maxlength="2000">{{ old('deskripsi', $barang->deskripsi) }}</textarea>
                            <small class="edit-form-help">Jelaskan ciri penting agar barang mudah dikenali (warna, merek, ukuran, kondisi).</small>
                            <small class="edit-form-counter" id="deskripsi_counter" aria-live="polite">0/2000 karakter</small>
                        </div>

                        <div class="edit-form-col-full">
                            <label class="edit-form-label" for="ciri_khusus">Ciri Unik Barang</label>
                            <textarea class="form-input edit-form-input edit-form-textarea" id="ciri_khusus" name="ciri_khusus" maxlength="2000">{{ old('ciri_khusus', $barang->ciri_khusus) }}</textarea>
                        </div>

                        <div>
                            <label class="edit-form-label" for="nama_penemu">Nama Penemu</label>
                            <input class="form-input edit-form-input" id="nama_penemu" name="nama_penemu" type="text" maxlength="150" value="{{ old('nama_penemu', $barang->nama_penemu) }}">
                        </div>

                        <div>
                            <label class="edit-form-label" for="kontak_penemu">No. WA Penemu</label>
                            <input class="form-input edit-form-input" id="kontak_penemu" name="kontak_penemu" type="text" maxlength="50" value="{{ old('kontak_penemu', $barang->kontak_penemu) }}">
                        </div>
                    </div>
                </div>

                <div class="edit-form-section">
                    <h3>Informasi Pengambilan</h3>
                    <div class="edit-form-grid edit-form-grid-two">
                        <div>
                            <label class="edit-form-label" for="lokasi_pengambilan">Lokasi Pengambilan</label>
                            <input class="form-input edit-form-input" id="lokasi_pengambilan" name="lokasi_pengambilan" type="text" maxlength="255" value="{{ old('lokasi_pengambilan', $barang->lokasi_pengambilan) }}">
                        </div>

                        <div>
                            <label class="edit-form-label" for="alamat_pengambilan">Alamat Pengambilan</label>
                            <input class="form-input edit-form-input" id="alamat_pengambilan" name="alamat_pengambilan" type="text" maxlength="255" value="{{ old('alamat_pengambilan', $barang->alamat_pengambilan) }}">
                        </div>

                        <div>
                            <label class="edit-form-label" for="penanggung_jawab_pengambilan">Penanggung Jawab</label>
                            <input class="form-input edit-form-input" id="penanggung_jawab_pengambilan" name="penanggung_jawab_pengambilan" type="text" maxlength="255" value="{{ old('penanggung_jawab_pengambilan', $barang->penanggung_jawab_pengambilan) }}">
                        </div>

                        <div>
                            <label class="edit-form-label" for="kontak_pengambilan">Kontak Pengambilan</label>
                            <input class="form-input edit-form-input" id="kontak_pengambilan" name="kontak_pengambilan" type="text" maxlength="255" value="{{ old('kontak_pengambilan', $barang->kontak_pengambilan) }}">
                        </div>

                        <div>
                            <label class="edit-form-label" for="jam_layanan_pengambilan">Jam Layanan</label>
                            <input class="form-input edit-form-input" id="jam_layanan_pengambilan" name="jam_layanan_pengambilan" type="text" maxlength="255" value="{{ old('jam_layanan_pengambilan', $barang->jam_layanan_pengambilan) }}">
                        </div>

                        <div>
                            <label class="edit-form-label" for="catatan_pengambilan">Catatan Pengambilan</label>
                            <textarea class="form-input edit-form-input edit-form-textarea-sm" id="catatan_pengambilan" name="catatan_pengambilan" maxlength="2000">{{ old('catatan_pengambilan', $barang->catatan_pengambilan) }}</textarea>
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

                <div class="edit-form-section">
                    <h3>Status Barang</h3>
                    <div class="edit-form-grid">
                        <p class="edit-form-help mb-0">
                            Perubahan status diproses dari halaman detail agar keputusan verifikasi tidak tercampur dengan perubahan data.
                            <a href="{{ manager_route('found-items.show', $barang->id) }}">Buka detail barang</a>.
                        </p>
                    </div>
                </div>

                <div class="edit-report-form-actions">
                    <a href="{{ manager_route('found-items.show', $barang->id) }}" class="filter-btn">Batal</a>
                    <button type="submit" class="filter-btn primary">Simpan Perubahan</button>
                </div>
            </form>
        </section>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const photoInput = document.getElementById('foto_barang');
            const photoFileName = document.getElementById('foto_barang_filename');
            const deskripsi = document.getElementById('deskripsi');
            const deskripsiCounter = document.getElementById('deskripsi_counter');
            const maxDeskripsi = Number(deskripsi?.getAttribute('maxlength') || 2000);

            function syncDeskripsiCounter() {
                if (!deskripsi || !deskripsiCounter) return;
                const count = deskripsi.value.length;
                deskripsiCounter.textContent = `${count}/${maxDeskripsi} karakter`;
            }

            if (deskripsi) {
                syncDeskripsiCounter();
                deskripsi.addEventListener('input', syncDeskripsiCounter);
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
