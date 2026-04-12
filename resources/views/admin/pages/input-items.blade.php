@extends('admin.layouts.app')

@php
    /** BAGIAN: Meta Halaman */
    $pageTitle = 'Input Barang - SiNemu';
    $activeMenu = 'input-items';
    $searchAction = route('admin.input-items');
    $searchPlaceholder = 'Cari laporan atau barang';
    $hideSearch = true;
    $jenisLaporan = old('jenis_laporan', 'hilang');
@endphp

@section('page-content')
    <div class="input-page-content">
        <section class="intro">
            <h1>Input Laporan Baru</h1>
            <p>Masukkan detail barang yang ditemukan atau hilang ke dalam sistem.</p>
        </section>

        @if(session('status'))
            <div class="feedback-alert success">{{ session('status') }}</div>
        @endif

        @if(session('error'))
            <div class="feedback-alert error">{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="feedback-alert error">{{ $errors->first() }}</div>
        @endif

        <section class="input-card">
            <form method="POST" action="{{ route('admin.input-items.store') }}" enctype="multipart/form-data" class="input-form">
                @csrf

                <div class="form-group form-col-12">
                    <label class="form-label">Jenis Laporan <span>*</span></label>
                    <div class="report-type-wrap">
                        <label class="report-type-option">
                            <input type="radio" name="jenis_laporan" value="hilang" @checked($jenisLaporan === 'hilang')>
                            <span>Barang Hilang</span>
                        </label>
                        <label class="report-type-option">
                            <input type="radio" name="jenis_laporan" value="temuan" @checked($jenisLaporan === 'temuan')>
                            <span>Barang Temuan</span>
                        </label>
                    </div>
                </div>

                <div class="form-group form-col-6">
                    <label class="form-label" for="nama_barang">Nama Barang <span>*</span></label>
                    <input id="nama_barang" type="text" name="nama_barang" class="form-input" value="{{ old('nama_barang') }}" placeholder="Masukkan nama barang" required>
                </div>

                <div class="form-group form-col-6">
                    <label class="form-label" for="kategori_id">Kategori</label>
                    <select id="kategori_id" name="kategori_id" class="form-input">
                        <option value="">Pilih kategori</option>
                        @foreach($kategoriOptions as $kategori)
                            <option value="{{ $kategori->id }}" @selected((string) old('kategori_id') === (string) $kategori->id)>{{ $kategori->nama_kategori }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group form-col-6">
                    <label class="form-label" for="tanggal_waktu">Tanggal &amp; Waktu <span>*</span></label>
                    <input id="tanggal_waktu" type="datetime-local" name="tanggal_waktu" class="form-input" value="{{ old('tanggal_waktu') }}" required>
                </div>

                <div class="form-group form-col-6">
                    <label class="form-label" for="lokasi">Lokasi <span>*</span></label>
                    <input id="lokasi" type="text" name="lokasi" class="form-input" value="{{ old('lokasi') }}" placeholder="Detail lokasi kejadian" required>
                </div>

                <div class="form-group form-col-12">
                    <label class="form-label" for="nama_pelapor">Nama Pelapor/Penemu <span>*</span></label>
                    <input id="nama_pelapor" type="text" name="nama_pelapor" class="form-input" value="{{ old('nama_pelapor') }}" placeholder="Masukkan nama pelapor atau penemu" required>
                    <small class="form-note">Untuk laporan barang hilang, isikan nama/email akun pengguna yang sudah terdaftar.</small>
                </div>

                <div class="form-group form-col-12">
                    <label class="form-label" for="deskripsi">Deskripsi/Ciri-ciri</label>
                    <textarea id="deskripsi" name="deskripsi" class="form-input form-textarea" placeholder="Tuliskan deskripsi lengkap barang, seperti warna, merek, atau ciri khusus lainnya.">{{ old('deskripsi') }}</textarea>
                </div>

                <div class="form-col-12 pickup-section" id="pickupSection">
                    <div class="pickup-section-head">
                        <h3>Informasi Pengambilan Barang</h3>
                        <p>Lengkapi data lokasi pengambilan agar pengguna tahu barang harus diambil di mana.</p>
                    </div>
                </div>

                <div class="form-group form-col-6 pickup-field">
                    <label class="form-label" for="lokasi_pengambilan">Lokasi Pengambilan <span>*</span></label>
                    <input id="lokasi_pengambilan" type="text" name="lokasi_pengambilan" class="form-input" value="{{ old('lokasi_pengambilan') }}" placeholder="Contoh: Kantor Kecamatan Indramayu">
                </div>

                <div class="form-group form-col-6 pickup-field">
                    <label class="form-label" for="alamat_pengambilan">Alamat Lengkap Pengambilan <span>*</span></label>
                    <input id="alamat_pengambilan" type="text" name="alamat_pengambilan" class="form-input" value="{{ old('alamat_pengambilan') }}" placeholder="Masukkan alamat lengkap lokasi pengambilan">
                </div>

                <div class="form-group form-col-6 pickup-field">
                    <label class="form-label" for="penanggung_jawab_pengambilan">Penanggung Jawab <span>*</span></label>
                    <input id="penanggung_jawab_pengambilan" type="text" name="penanggung_jawab_pengambilan" class="form-input" value="{{ old('penanggung_jawab_pengambilan', $admin?->nama) }}" placeholder="Nama admin/petugas penanggung jawab">
                </div>

                <div class="form-group form-col-6 pickup-field">
                    <label class="form-label" for="kontak_pengambilan">Kontak Pengambilan <span>*</span></label>
                    <input id="kontak_pengambilan" type="text" name="kontak_pengambilan" class="form-input" value="{{ old('kontak_pengambilan') }}" placeholder="Nomor WhatsApp / Telepon aktif">
                </div>

                <div class="form-group form-col-6 pickup-field">
                    <label class="form-label" for="jam_layanan_pengambilan">Jam Layanan Pengambilan</label>
                    <input id="jam_layanan_pengambilan" type="text" name="jam_layanan_pengambilan" class="form-input" value="{{ old('jam_layanan_pengambilan') }}" placeholder="Contoh: Senin - Jumat, 08.00 - 16.00 WIB">
                </div>

                <div class="form-group form-col-6 pickup-field">
                    <label class="form-label" for="catatan_pengambilan">Catatan Pengambilan</label>
                    <textarea id="catatan_pengambilan" name="catatan_pengambilan" class="form-input form-textarea form-textarea-sm" placeholder="Syarat tambahan, dokumen yang wajib dibawa, atau informasi penting lainnya.">{{ old('catatan_pengambilan') }}</textarea>
                </div>

                <div class="form-group form-col-12">
                    <label class="form-label" for="foto_barang">Unggah Foto</label>
                    <label class="upload-box" for="foto_barang">
                        <span class="upload-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M7 7h2l1.3-2h3.4L15 7h2a3 3 0 0 1 3 3v7a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3v-7a3 3 0 0 1 3-3Zm5 4.3a3.2 3.2 0 1 0 0 6.4a3.2 3.2 0 0 0 0-6.4Z" fill="currentColor"/></svg>
                        </span>
                        <span id="uploadText">Klik untuk mengunggah atau seret foto ke sini</span>
                        <small>Ukuran maks. 3MB (JPG, JPEG, PNG, WEBP)</small>
                    </label>
                    <input id="foto_barang" type="file" name="foto_barang" accept=".jpg,.jpeg,.png,.webp" class="form-file">
                </div>

                <div class="form-actions form-col-12">
                    <button type="reset" class="btn-secondary">Batal</button>
                    <button type="submit" class="btn-primary">Simpan Laporan</button>
                </div>
            </form>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.body.classList.add('input-page-mode');
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const reportTypeInputs = document.querySelectorAll('input[name="jenis_laporan"]');
            const pickupSection = document.getElementById('pickupSection');
            const pickupFields = Array.from(document.querySelectorAll('.pickup-field'));
            const pickupRequiredInputs = [
                document.getElementById('lokasi_pengambilan'),
                document.getElementById('alamat_pengambilan'),
                document.getElementById('penanggung_jawab_pengambilan'),
                document.getElementById('kontak_pengambilan'),
            ].filter(Boolean);

            const fileInput = document.getElementById('foto_barang');
            const uploadText = document.getElementById('uploadText');

            function applyPickupState() {
                const selected = document.querySelector('input[name="jenis_laporan"]:checked');
                const isTemuan = selected && selected.value === 'temuan';

                if (pickupSection) {
                    pickupSection.style.display = isTemuan ? '' : 'none';
                }

                pickupFields.forEach(function (field) {
                    field.style.display = isTemuan ? '' : 'none';
                });

                pickupRequiredInputs.forEach(function (input) {
                    input.required = isTemuan;
                });
            }

            reportTypeInputs.forEach(function (input) {
                input.addEventListener('change', applyPickupState);
            });
            applyPickupState();

            if (!fileInput || !uploadText) return;

            fileInput.addEventListener('change', function () {
                if (fileInput.files && fileInput.files.length > 0) {
                    uploadText.textContent = fileInput.files[0].name;
                } else {
                    uploadText.textContent = 'Klik untuk mengunggah atau seret foto ke sini';
                }
            });
        });
    </script>
@endsection
