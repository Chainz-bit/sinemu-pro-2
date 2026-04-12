@extends('admin.layouts.app')

@php
    /** BAGIAN: Meta Halaman */
    $pageTitle = 'Daftar Barang Temuan - SiNemu';
    $activeMenu = 'found-items';
    $searchAction = route('admin.found-items');
    $searchPlaceholder = 'Cari laporan atau barang';
@endphp

@section('page-content')
    {{-- BAGIAN: Pembuka --}}
    <section class="intro">
        <h1>Daftar Barang Temuan</h1>
        <p>Kelola daftar barang yang Temuan dan menunggu diklaim pemiliknya.</p>
    </section>

    @if(session('status'))
        <div class="report-card" style="margin-bottom:12px;">
            <header><h2 style="font-size:14px;">{{ session('status') }}</h2></header>
        </div>
    @endif

    {{-- BAGIAN: Toolbar + Tabel --}}
    <section class="report-card">
        <header>
            <form class="lost-toolbar" method="GET" action="{{ route('admin.found-items') }}">
                <div class="lost-toolbar-left">
                    <select name="sort" class="filter-btn">
                        <option value="terbaru" @selected($sort === 'terbaru')>Urutkan Berdasarkan...</option>
                        <option value="terlama" @selected($sort === 'terlama')>Urutkan: Terlama</option>
                        <option value="nama_asc" @selected($sort === 'nama_asc')>Nama A-Z</option>
                        <option value="nama_desc" @selected($sort === 'nama_desc')>Nama Z-A</option>
                    </select>
                </div>
                <div class="lost-toolbar-right">
                    <input type="date" class="filter-btn" name="date" value="{{ request('date') }}">
                    @if(request()->filled('search'))
                        <input type="hidden" name="search" value="{{ request('search') }}">
                    @endif
                    @if(request()->filled('status'))
                        <input type="hidden" name="status" value="{{ request('status') }}">
                    @endif
                    <button type="submit" name="export" value="1" class="filter-btn export-btn">Export Data</button>
                </div>
            </form>
        </header>

        <div class="report-table-wrap">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Detail Barang</th>
                        <th>Petugas</th>
                        <th>Tanggal Ditemukan</th>
                        <th>Lokasi Penyimpanan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $index => $item)
                        <tr>
                            <td>
                                <div class="item-cell">
                                    <div class="item-avatar avatar-sand">
                                        <span class="item-avatar-fallback">{{ strtoupper(substr($item->nama_barang, 0, 1)) }}</span>
                                        @if(!empty($item->foto_barang))
                                            @php
                                                $fotoPath = trim((string) $item->foto_barang, '/');
                                                [$folder, $subPath] = array_pad(explode('/', $fotoPath, 2), 2, '');
                                                $fotoUrl = in_array($folder, ['barang-hilang', 'barang-temuan', 'verifikasi-klaim'], true) && $subPath !== ''
                                                    ? route('media.image', ['folder' => $folder, 'path' => $subPath], false)
                                                    : asset('storage/' . $fotoPath);
                                            @endphp
                                            <img
                                                src="{{ $fotoUrl }}"
                                                alt="{{ $item->nama_barang }}"
                                                loading="lazy"
                                                decoding="async"
                                                width="30"
                                                height="30"
                                                onerror="this.remove()"
                                            >
                                        @else
                                            <img
                                                src="{{ route('media.image', ['folder' => 'barang-temuan', 'path' => 'hp.webp'], false) }}"
                                                alt="{{ $item->nama_barang }}"
                                                loading="lazy"
                                                decoding="async"
                                                width="30"
                                                height="30"
                                                onerror="this.remove()"
                                            >
                                        @endif
                                    </div>
                                    <div>
                                        <strong>{{ $item->nama_barang }}</strong>
                                        <small>{{ $item->kategori?->nama_kategori ?? 'Tanpa Kategori' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $item->admin?->nama ?? '-' }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($item->tanggal_ditemukan)->format('d M Y') }}</td>
                            <td>{{ $item->lokasi_ditemukan }}</td>
                            <td>
                                @php
                                    $statusMap = [
                                        'tersedia' => ['TERSEDIA', 'status-dalam_peninjauan'],
                                        'dalam_proses_klaim' => ['DALAM PROSES KLAIM', 'status-diproses'],
                                        'sudah_diklaim' => ['SUDAH DIKLAIM', 'status-selesai'],
                                        'sudah_dikembalikan' => ['SELESAI', 'status-selesai'],
                                    ];
                                    [$statusLabel, $statusClass] = $statusMap[$item->status_barang] ?? ['UNKNOWN', 'status-diproses'];
                                @endphp
                                <span class="status-chip {{ $statusClass }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="menu-cell">
                                <button type="button" class="row-menu-trigger" data-menu-target="menu-found-{{ $index }}" aria-label="Aksi">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5.5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3zm0 5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3zm0 5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3z" fill="currentColor"/></svg>
                                </button>
                                <div class="row-menu" id="menu-found-{{ $index }}">
                                    <a href="{{ route('admin.found-items.show', $item->id) }}">Lihat Detail</a>
                                    <a href="#">Edit Data</a>
                                    <form method="POST" action="{{ route('admin.found-items.destroy', $item->id) }}" data-confirm-delete data-confirm-message="Yakin ingin menghapus laporan ini?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="menu-submit danger">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-row">Belum ada data barang temuan untuk admin ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <footer class="pagination">
            @if($items->onFirstPage())
                <button type="button" disabled>Sebelumnya</button>
            @else
                <button type="button" onclick="window.location.href='{{ $items->previousPageUrl() }}'">Sebelumnya</button>
            @endif

            @for($page = 1; $page <= $items->lastPage(); $page++)
                <button type="button" class="{{ $items->currentPage() === $page ? 'active' : '' }}" onclick="window.location.href='{{ $items->url($page) }}'">{{ $page }}</button>
            @endfor

            @if($items->hasMorePages())
                <button type="button" onclick="window.location.href='{{ $items->nextPageUrl() }}'">Selanjutnya</button>
            @else
                <button type="button" disabled>Selanjutnya</button>
            @endif
        </footer>
    </section>
@endsection
