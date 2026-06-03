@extends('manager::layouts.app')

@php
    /** BAGIAN: Meta Halaman */
    $pageTitle = 'Daftar Barang Temuan - SiNemu';
    $activeMenu = 'found-items';
    $searchAction = manager_route('found-items');
    $searchPlaceholder = 'Cari laporan atau barang';
    $managerRoleLabelLower = \App\Support\RoleLabels::managerLower();
@endphp

@section('page-content')
    {{-- BAGIAN: Pembuka --}}
    <section class="intro">
        <h1>Daftar Barang Temuan</h1>
        <p>Kelola daftar barang yang Temuan dan menunggu diklaim pemiliknya.</p>
    </section>
{{-- BAGIAN: Toolbar + Tabel --}}
    <section class="report-card report-card-scrollable">
        <header>
            <form class="lost-toolbar" method="GET" action="{{ manager_route('found-items') }}">
                <div class="lost-toolbar-left">
                    <select name="sort" class="filter-btn" data-custom-select>
                        <option value="terbaru" @selected($sort === 'terbaru')>Urutkan Berdasarkan...</option>
                        <option value="terlama" @selected($sort === 'terlama')>Urutkan: Terlama</option>
                        <option value="nama_asc" @selected($sort === 'nama_asc')>Nama A-Z</option>
                        <option value="nama_desc" @selected($sort === 'nama_desc')>Nama Z-A</option>
                    </select>
                    <select name="status" class="filter-btn" data-custom-select>
                        <option value="">Semua Status</option>
                        <option value="tersedia" @selected(request('status') === 'tersedia')>Tersedia</option>
                        <option value="dalam_proses_klaim" @selected(request('status') === 'dalam_proses_klaim')>Dalam Proses Klaim</option>
                        <option value="sudah_diklaim" @selected(request('status') === 'sudah_diklaim')>Sudah Diklaim</option>
                        <option value="sudah_dikembalikan" @selected(request('status') === 'sudah_dikembalikan')>Selesai</option>
                    </select>
                </div>
                <div class="lost-toolbar-right">
                    <input type="date" class="filter-btn" name="date" value="{{ request('date') }}">
                    @if(request()->filled('search'))
                        <input type="hidden" name="search" value="{{ request('search') }}">
                    @endif
                    @if(request()->filled('date') || request()->filled('status') || request('sort', 'terbaru') !== 'terbaru' || request()->filled('search'))
                        <a href="{{ manager_route('found-items') }}" class="filter-btn">Hapus Filter</a>
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
                                        @php
                                            $fotoUrlDefault = asset('img/login-image.png');
                                            $fotoUrl = null;
                                            $fotoSrc = null;
                                            $localFotoPath = null;
                                            $rawFotoPath = str_replace('\\', '/', trim((string) ($item->foto_barang ?? '')));

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
                                                }
                                            }

                                            if (!$fotoSrc) {
                                                $fotoSrc = $fotoUrl ?: $fotoUrlDefault;
                                            }
                                        @endphp
                                        @if($fotoSrc)
                                            <img
                                                src="{{ $fotoSrc }}"
                                                alt="{{ $item->nama_barang }}"
                                                loading="lazy"
                                                decoding="async"
                                                width="30"
                                                height="30"
                                                onerror="this.onerror=null;this.src='{{ $fotoUrlDefault }}';"
                                            >
                                        @else
                                            <img
                                                src="{{ $fotoUrlDefault }}"
                                                alt="{{ $item->nama_barang }}"
                                                loading="lazy"
                                                decoding="async"
                                                width="30"
                                                height="30"
                                                onerror="this.onerror=null;"
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
                                    <a href="{{ manager_route('found-items.show', $item->id) }}">Lihat Detail</a>
                                    @if($item->canBeEditedByAdmin())
                                        <a href="{{ manager_route('found-items.edit', $item->id) }}">Edit Data</a>
                                    @endif
                                    @if($item->canBePublishedToHomeByAdmin())
                                        <form method="POST" action="{{ manager_route('dashboard.reports.publish-home', ['type' => 'temuan', 'id' => $item->id]) }}">
                                            @csrf
                                            <button type="submit" class="menu-submit">Tampilkan di Home</button>
                                        </form>
                                    @elseif($item->tampil_di_home ?? false)
                                        <span class="row-menu-note">Sudah tampil di Home</span>
                                    @endif
                                    @if($item->canBeDeletedByAdmin())
                                        <form method="POST" action="{{ manager_route('found-items.destroy', $item->id) }}" data-confirm-delete data-confirm-message="Yakin ingin menghapus laporan ini?">
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
                            <td colspan="6" class="empty-row">Belum ada data barang temuan untuk {{ $managerRoleLabelLower }} ini.</td>
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

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const filterForm = document.querySelector('.lost-toolbar');
            if (!filterForm) return;

            const autoSubmitFields = filterForm.querySelectorAll('select[name="sort"], select[name="status"], input[name="date"]');
            autoSubmitFields.forEach(function (field) {
                field.addEventListener('change', function () {
                    filterForm.requestSubmit();
                });
            });
        });
    </script>
@endsection
