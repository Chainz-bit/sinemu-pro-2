<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Barang Temuan</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2d3d; font-size: 12px; }
        .title { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
        .subtitle { color: #5f7087; margin-bottom: 14px; }
        .card { border: 1px solid #d9e2ef; border-radius: 8px; padding: 12px; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { padding: 8px 6px; border-bottom: 1px solid #ecf1f7; vertical-align: top; }
        .label { color: #6b7d96; width: 34%; font-weight: 700; }
        .value { color: #1f2d3d; }
        .photo-wrap { text-align: center; margin-bottom: 14px; }
        .photo { max-width: 260px; max-height: 260px; border-radius: 8px; }
        .footer { margin-top: 14px; font-size: 10px; color: #7b8ba1; }
    </style>
</head>
<body>
    <div class="title">Laporan Barang Temuan</div>
    <div class="subtitle">Diekspor pada {{ now()->format('d M Y, H:i') }} WIB</div>

    <div class="card">
        @if(!empty($photoDataUri))
            <div class="photo-wrap">
                <img src="{{ $photoDataUri }}" alt="{{ $barang->nama_barang }}" class="photo">
            </div>
        @endif

        <table class="grid">
            <tr><td class="label">Nama Barang</td><td class="value">{{ $barang->nama_barang }}</td></tr>
            <tr><td class="label">Kategori</td><td class="value">{{ $barang->kategori?->nama_kategori ?? 'Tanpa Kategori' }}</td></tr>
            <tr><td class="label">Deskripsi</td><td class="value">{{ $barang->deskripsi ?: '-' }}</td></tr>
            <tr><td class="label">Tanggal Ditemukan</td><td class="value">{{ !empty($barang->tanggal_ditemukan) ? \Illuminate\Support\Carbon::parse($barang->tanggal_ditemukan)->format('d M Y, H:i') . ' WIB' : '-' }}</td></tr>
            <tr><td class="label">Lokasi Ditemukan</td><td class="value">{{ $barang->lokasi_ditemukan ?: '-' }}</td></tr>
            <tr><td class="label">Status</td><td class="value">{{ $statusLabel }}</td></tr>
            <tr><td class="label">Lokasi Pengambilan</td><td class="value">{{ $barang->lokasi_pengambilan ?: '-' }}</td></tr>
            <tr><td class="label">Alamat Pengambilan</td><td class="value">{{ $barang->alamat_pengambilan ?: '-' }}</td></tr>
            <tr><td class="label">Penanggung Jawab</td><td class="value">{{ $barang->penanggung_jawab_pengambilan ?: ($barang->admin?->nama ?? '-') }}</td></tr>
            <tr><td class="label">Kontak</td><td class="value">{{ $barang->kontak_pengambilan ?: '-' }}</td></tr>
            <tr><td class="label">Jam Layanan</td><td class="value">{{ $barang->jam_layanan_pengambilan ?: '-' }}</td></tr>
            <tr><td class="label">Catatan</td><td class="value">{{ $barang->catatan_pengambilan ?: '-' }}</td></tr>
        </table>
    </div>

    <div class="footer">SiNemu - Dokumen ini dihasilkan otomatis oleh sistem.</div>
</body>
</html>
