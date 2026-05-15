@extends('user.layouts.app')

@php
    /** BAGIAN: Meta halaman lapor barang hilang */
    $pageTitle = 'Lapor Barang Hilang - SiNemu';
    $activeMenu = 'lost-report';
    $searchAction = route('user.dashboard');
    $searchPlaceholder = 'Cari di dashboard';
@endphp

@section('page-content')
    <div class="input-page-content report-form-page lost-report-page">
{{-- BAGIAN: Header halaman --}}
        <section class="intro">
            <h1>{{ $editingReport ? 'Edit Laporan Barang Hilang' : 'Lapor Barang Hilang' }}</h1>
            <p>{{ $editingReport ? 'Perbarui data laporan agar pengelola barang dapat meninjau ulang dengan cepat.' : 'Isi data secara lengkap agar tim SiNemu lebih cepat membantu proses pencarian.' }}</p>
        </section>

        {{-- BAGIAN: Form laporan hilang --}}
        <section class="input-card">
            <form method="POST" action="{{ route('user.lost-reports.store') }}" class="input-form" enctype="multipart/form-data" novalidate>
                @csrf
                @if($editingReport)
                    <input type="hidden" name="report_id" value="{{ $editingReport->id }}">
                @endif
                <div class="form-col-6 form-group">
                    <label class="form-label" for="nama_barang">Nama Barang <span>*</span></label>
                    <input id="nama_barang" name="nama_barang" type="text" class="form-input" value="{{ old('nama_barang', $editingReport?->nama_barang) }}" placeholder="Contoh: Dompet Coklat" required>
                </div>

                <div class="form-col-6 form-group">
                    <label class="form-label" for="region_id">Wilayah Kejadian <span>*</span></label>
                    <select id="region_id" name="region_id" class="form-input" required>
                        <option value="">Pilih kecamatan</option>
                        @foreach(($wilayahOptions ?? collect()) as $wilayah)
                            <option value="{{ $wilayah->id }}" @selected((string) old('region_id', $editingReport?->region_id) === (string) $wilayah->id)>
                                {{ $wilayah->nama_wilayah }}
                            </option>
                        @endforeach
                    </select>
                    <small class="form-note">Laporan akan masuk ke pengelola barang wilayah yang dipilih.</small>
                </div>

                <div class="form-col-6 form-group">
                    <label class="form-label" for="kategori_barang">Kategori Barang</label>
                    @php
                        $selectedLostCategory = (string) old('kategori_barang', $editingReport?->kategori_barang);
                        $lostCategoryNames = collect($lostCategoryOptions ?? [])->map(fn ($name) => trim((string) $name))->filter()->values();
                        $hasSelectedLostCategory = $selectedLostCategory !== '' && $lostCategoryNames->contains($selectedLostCategory);
                    @endphp
                    <select id="kategori_barang" name="kategori_barang" class="form-input">
                        <option value="">Pilih kategori</option>
                        @foreach($lostCategoryNames as $categoryName)
                            <option value="{{ $categoryName }}" @selected($selectedLostCategory === $categoryName)>{{ $categoryName }}</option>
                        @endforeach
                        @if($selectedLostCategory !== '' && !$hasSelectedLostCategory)
                            <option value="{{ $selectedLostCategory }}" selected>{{ $selectedLostCategory }}</option>
                        @endif
                    </select>
                </div>

                <div class="form-col-6 form-group">
                    <label class="form-label" for="warna_barang">Warna Dominan</label>
                    <input id="warna_barang" name="warna_barang" type="text" class="form-input" value="{{ old('warna_barang', $editingReport?->warna_barang) }}" placeholder="Contoh: Hitam">
                </div>

                <div class="form-col-6 form-group">
                    <label class="form-label" for="merek_barang">Merek / Brand</label>
                    <input id="merek_barang" name="merek_barang" type="text" class="form-input" value="{{ old('merek_barang', $editingReport?->merek_barang) }}" placeholder="Contoh: Samsung, Eiger, Casio">
                </div>

                <div class="form-col-6 form-group">
                    <label class="form-label" for="nomor_seri">Nomor Seri / Kode Unik</label>
                    <input id="nomor_seri" name="nomor_seri" type="text" class="form-input" value="{{ old('nomor_seri', $editingReport?->nomor_seri) }}" placeholder="Contoh: IMEI, nomor seri, kode produksi">
                </div>

                <div class="form-col-6 form-group">
                    <label class="form-label" for="lokasi_hilang">Lokasi Hilang <span>*</span></label>
                    <input id="lokasi_hilang" name="lokasi_hilang" type="text" class="form-input" value="{{ old('lokasi_hilang', $editingReport?->lokasi_hilang) }}" placeholder="Contoh: Area parkir timur" required>
                </div>

                <div class="form-col-6 form-group">
                    <label class="form-label" for="tanggal_hilang">Tanggal Hilang <span>*</span></label>
                    <input id="tanggal_hilang" name="tanggal_hilang" type="date" class="form-input" value="{{ old('tanggal_hilang', !empty($editingReport?->tanggal_hilang) ? \Illuminate\Support\Carbon::parse($editingReport->tanggal_hilang)->format('Y-m-d') : null) }}" required>
                </div>

                <div class="form-col-6 form-group">
                    <label class="form-label" for="waktu_hilang">Perkiraan Jam Hilang</label>
                    <input id="waktu_hilang" name="waktu_hilang" type="time" class="form-input" value="{{ old('waktu_hilang', !empty($editingReport?->waktu_hilang) ? date('H:i', strtotime($editingReport->waktu_hilang)) : null) }}">
                </div>

                <div class="form-col-12 form-group">
                    <label class="form-label" for="detail_lokasi_hilang">Detail Lokasi Hilang</label>
                    <textarea id="detail_lokasi_hilang" name="detail_lokasi_hilang" class="form-input form-textarea" rows="3" placeholder="Contoh: Dekat ATM sisi kiri, sekitar 10 meter dari pintu utama.">{{ old('detail_lokasi_hilang', $editingReport?->detail_lokasi_hilang) }}</textarea>
                </div>

                <div class="form-col-12 form-group">
                    <label class="form-label" for="keterangan">Deskripsi Barang dan Kronologi Singkat <span>*</span></label>
                    <textarea id="keterangan" name="keterangan" class="form-input form-textarea" rows="4" placeholder="Jelaskan barang, kapan terakhir terlihat, dan kronologi singkat kejadian." required>{{ old('keterangan', $editingReport?->keterangan) }}</textarea>
                    <small class="form-note">Data yang lebih rinci akan memudahkan proses validasi.</small>
                </div>

                <div class="form-col-12 form-group">
                    <label class="form-label" for="ciri_khusus">Ciri Unik Barang</label>
                    <textarea id="ciri_khusus" name="ciri_khusus" class="form-input form-textarea" rows="3" placeholder="Contoh: Ada stiker kampus di sisi belakang, resleting kiri agak seret.">{{ old('ciri_khusus', $editingReport?->ciri_khusus) }}</textarea>
                </div>

                <div class="form-col-6 form-group">
                    <label class="form-label" for="kontak_pelapor">No. WA yang Bisa Dihubungi <span>*</span></label>
                    <input id="kontak_pelapor" name="kontak_pelapor" type="text" class="form-input" value="{{ old('kontak_pelapor', $editingReport?->kontak_pelapor) }}" placeholder="Contoh: 081234567890" required>
                </div>

                <div class="form-col-12 form-group">
                    <label class="form-label" for="bukti_kepemilikan">Bukti Kepemilikan (Opsional)</label>
                    <textarea id="bukti_kepemilikan" name="bukti_kepemilikan" class="form-input form-textarea" rows="3" placeholder="Contoh: Ada foto saat barang dipakai, nomor seri, atau detail isi barang yang hanya pemilik tahu.">{{ old('bukti_kepemilikan', $editingReport?->bukti_kepemilikan) }}</textarea>
                </div>

                <div class="form-col-12 form-group">
                    <label class="form-label" for="foto_barang">Foto Barang (Opsional)</label>
                    <input id="foto_barang" name="foto_barang" type="file" class="form-input" accept="image/jpeg,image/png,image/webp">
                    <small class="form-note">Format: JPG, JPEG, PNG, WEBP. Maksimal 3MB.</small>
                </div>

                <div class="form-col-12 form-actions">
                    <button type="submit" class="btn-primary">{{ $editingReport ? 'Perbarui Laporan' : 'Kirim Laporan' }}</button>
                </div>
            </form>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.documentElement.classList.add('lost-report-page-mode');
            document.body.classList.add('lost-report-page-mode');
        });
    </script>
@endsection
