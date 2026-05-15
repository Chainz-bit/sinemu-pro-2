@extends('user.layouts.app')

@php
    /** BAGIAN: Meta halaman lapor barang temuan */
    $pageTitle = 'Lapor Barang Temuan - SiNemu';
    $activeMenu = 'found-report';
    $searchAction = route('user.dashboard');
    $searchPlaceholder = 'Cari di dashboard';
@endphp

@section('page-content')
    <div class="input-page-content report-form-page found-report-page">
{{-- BAGIAN: Header halaman --}}
        <section class="intro">
            <h1>Lapor Barang Temuan</h1>
            <p>Laporkan barang temuan agar pemilik dapat dihubungi dan proses pengambilan berjalan tertib.</p>
        </section>

        {{-- BAGIAN: Form laporan temuan --}}
        <section class="input-card">
            <form method="POST" action="{{ route('user.found-reports.store') }}" class="input-form" enctype="multipart/form-data" novalidate>
                @csrf
                <div class="form-col-6 form-group">
                    <label class="form-label" for="nama_barang">Nama Barang <span>*</span></label>
                    <input id="nama_barang" name="nama_barang" type="text" class="form-input" value="{{ old('nama_barang') }}" placeholder="Contoh: HP Android warna hitam" required>
                </div>

                <div class="form-col-6 form-group">
                    <label class="form-label" for="region_id">Wilayah Ditemukan <span>*</span></label>
                    <select id="region_id" name="region_id" class="form-input" required>
                        <option value="">Pilih kecamatan</option>
                        @foreach(($wilayahOptions ?? collect()) as $wilayah)
                            <option value="{{ $wilayah->id }}" @selected((string) old('region_id') === (string) $wilayah->id)>
                                {{ $wilayah->nama_wilayah }}
                            </option>
                        @endforeach
                    </select>
                    <small class="form-note">Barang temuan akan diteruskan ke pengelola barang wilayah ini.</small>
                </div>

                <div class="form-col-6 form-group">
                    <label class="form-label" for="kategori_id">Kategori</label>
                    <select id="kategori_id" name="kategori_id" class="form-input">
                        <option value="">Pilih kategori</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('kategori_id') === (string) $category->id)>
                                {{ $category->nama_kategori }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-col-6 form-group">
                    <label class="form-label" for="warna_barang">Warna Dominan</label>
                    <input id="warna_barang" name="warna_barang" type="text" class="form-input" value="{{ old('warna_barang') }}" placeholder="Contoh: Hitam">
                </div>

                <div class="form-col-6 form-group">
                    <label class="form-label" for="merek_barang">Merek / Brand</label>
                    <input id="merek_barang" name="merek_barang" type="text" class="form-input" value="{{ old('merek_barang') }}" placeholder="Contoh: Samsung, Casio">
                </div>

                <div class="form-col-6 form-group">
                    <label class="form-label" for="nomor_seri">Nomor Seri / Kode Unik</label>
                    <input id="nomor_seri" name="nomor_seri" type="text" class="form-input" value="{{ old('nomor_seri') }}" placeholder="Contoh: IMEI atau nomor seri">
                </div>

                <div class="form-col-6 form-group">
                    <label class="form-label" for="lokasi_ditemukan">Lokasi Ditemukan <span>*</span></label>
                    <input id="lokasi_ditemukan" name="lokasi_ditemukan" type="text" class="form-input" value="{{ old('lokasi_ditemukan') }}" placeholder="Contoh: Lobi Gedung A" required>
                </div>

                <div class="form-col-6 form-group">
                    <label class="form-label" for="tanggal_ditemukan">Tanggal Ditemukan <span>*</span></label>
                    <input id="tanggal_ditemukan" name="tanggal_ditemukan" type="date" class="form-input" value="{{ old('tanggal_ditemukan') }}" required>
                </div>

                <div class="form-col-6 form-group">
                    <label class="form-label" for="waktu_ditemukan">Perkiraan Jam Ditemukan</label>
                    <input id="waktu_ditemukan" name="waktu_ditemukan" type="time" class="form-input" value="{{ old('waktu_ditemukan') }}">
                </div>

                <div class="form-col-12 form-group">
                    <label class="form-label" for="detail_lokasi_ditemukan">Detail Lokasi Ditemukan</label>
                    <textarea id="detail_lokasi_ditemukan" name="detail_lokasi_ditemukan" class="form-input form-textarea" rows="3" placeholder="Contoh: Ditemukan di dekat pintu barat, sebelah mesin absensi.">{{ old('detail_lokasi_ditemukan') }}</textarea>
                </div>

                <div class="form-col-12 form-group">
                    <label class="form-label" for="deskripsi">Deskripsi <span>*</span></label>
                    <textarea id="deskripsi" name="deskripsi" class="form-input form-textarea" rows="4" placeholder="Jelaskan ciri barang dan kondisi saat ditemukan." required>{{ old('deskripsi') }}</textarea>
                </div>

                <div class="form-col-12 form-group">
                    <label class="form-label" for="ciri_khusus">Ciri Unik Barang</label>
                    <textarea id="ciri_khusus" name="ciri_khusus" class="form-input form-textarea" rows="3" placeholder="Contoh: Ada stiker, goresan tertentu, atau aksesori khusus.">{{ old('ciri_khusus') }}</textarea>
                </div>

                <div class="form-col-6 form-group">
                    <label class="form-label" for="nama_penemu">Nama Penemu</label>
                    <input id="nama_penemu" name="nama_penemu" type="text" class="form-input" value="{{ old('nama_penemu', $user?->nama ?? $user?->name ?? '') }}" placeholder="Nama Anda">
                </div>

                <div class="form-col-6 form-group">
                    <label class="form-label" for="kontak_penemu">No. WA Penemu <span>*</span></label>
                    <input id="kontak_penemu" name="kontak_penemu" type="text" class="form-input" value="{{ old('kontak_penemu') }}" placeholder="Contoh: 081234567890" required>
                </div>

                <div class="form-col-12 form-group">
                    <label class="form-label" for="foto_barang">Foto Barang (Opsional)</label>
                    <input id="foto_barang" name="foto_barang" type="file" class="form-input" accept="image/jpeg,image/png,image/webp">
                    <small class="form-note">Format: JPG, JPEG, PNG, WEBP. Maksimal 3MB.</small>
                </div>

                <div class="form-col-12 form-actions">
                    <button type="submit" class="btn-primary">Kirim Laporan</button>
                </div>
            </form>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.body.classList.add('input-page-mode');
        });
    </script>
@endsection
