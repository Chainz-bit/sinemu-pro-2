@extends('manager::layouts.app')

@php
    /** BAGIAN: Meta Halaman */
    $pageTitle = 'Daftar Barang Hilang - SiNemu';
    $activeMenu = 'lost-items';
    $searchAction = manager_route('lost-items');
    $searchPlaceholder = 'Cari laporan atau barang';
    $managerRoleLabel = \App\Support\RoleLabels::manager();
    $managerRoleLabelLower = \App\Support\RoleLabels::managerLower();
@endphp

@section('page-content')
    {{-- BAGIAN: Pembuka --}}
    <section class="intro">
        <h1>Daftar Barang Hilang</h1>
        <p>Kelola daftar barang yang hilang dan menunggu klaim pemiliknya.</p>
    </section>
{{-- BAGIAN: Toolbar + Tabel --}}
    <section class="report-card report-card-scrollable">
        <header>
            <form class="lost-toolbar" method="GET" action="{{ manager_route('lost-items') }}">
                <div class="lost-toolbar-left">
                    <select name="sort" class="filter-btn" data-custom-select>
                        <option value="terbaru" @selected($sort === 'terbaru')>Urutkan Berdasarkan...</option>
                        <option value="terlama" @selected($sort === 'terlama')>Urutkan: Terlama</option>
                        <option value="nama_asc" @selected($sort === 'nama_asc')>Nama A-Z</option>
                        <option value="nama_desc" @selected($sort === 'nama_desc')>Nama Z-A</option>
                    </select>
                    <select name="status" class="filter-btn" data-custom-select>
                        <option value="">Semua Status</option>
                        <option value="submitted" @selected(request('status') === 'submitted')>Menunggu Verifikasi</option>
                        <option value="approved" @selected(request('status') === 'approved')>Disetujui {{ $managerRoleLabel }}</option>
                        <option value="matched" @selected(request('status') === 'matched')>Sudah Dicocokkan</option>
                        <option value="claimed" @selected(request('status') === 'claimed')>Klaim Diproses</option>
                        <option value="completed" @selected(request('status') === 'completed')>Selesai</option>
                        <option value="rejected" @selected(request('status') === 'rejected')>Ditolak {{ $managerRoleLabel }}</option>
                    </select>
                </div>
                <div class="lost-toolbar-right">
                    <input type="date" class="filter-btn" name="date" value="{{ request('date') }}">
                    @if(request()->filled('search'))
                        <input type="hidden" name="search" value="{{ request('search') }}">
                    @endif
                    @if(request()->filled('date') || request()->filled('status') || request('sort', 'terbaru') !== 'terbaru' || request()->filled('search'))
                        <a href="{{ manager_route('lost-items') }}" class="filter-btn">Hapus Filter</a>
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
                        <th>Pelapor</th>
                        <th>Tanggal Hilang</th>
                        <th>Lokasi Hilang</th>
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
                                        <small>{{ $item->keterangan ?: 'Tanpa keterangan' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $item->user?->nama ?? $item->user?->name ?? 'Pengguna' }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($item->tanggal_hilang)->format('d M Y') }}</td>
                            <td>{{ $item->lokasi_hilang }}</td>
                            <td>
                                @php
                                    $reportStatus = \App\Support\ReportStatusPresenter::key($item->status_laporan ?? null);
                                    $statusLabel = \App\Support\ReportStatusPresenter::label($reportStatus);
                                    $statusClass = \App\Support\ReportStatusPresenter::cssClass($reportStatus);
                                @endphp
                                <span class="status-chip {{ $statusClass }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="menu-cell">
                                <button type="button" class="row-menu-trigger" data-menu-target="menu-{{ $index }}" aria-label="Aksi">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5.5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3zm0 5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3zm0 5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3z" fill="currentColor"/></svg>
                                </button>
                                <div class="row-menu" id="menu-{{ $index }}">
                                    <a href="{{ manager_route('lost-items.show', $item->id) }}">Lihat Detail</a>
                                    @if($item->canBeEditedByAdmin())
                                        <a href="{{ manager_route('lost-items.edit', $item->id) }}">Edit Data</a>
                                    @endif
                                    @if($reportStatus === \App\Support\WorkflowStatus::REPORT_APPROVED && !($item->tampil_di_home ?? false))
                                        <form method="POST" action="{{ manager_route('dashboard.reports.publish-home', ['type' => 'hilang', 'id' => $item->id]) }}">
                                            @csrf
                                            <button type="submit" class="menu-submit">Tampilkan di Home</button>
                                        </form>
                                    @endif
                                    @if($item->canBeDeletedByAdmin())
                                        <form method="POST" action="{{ manager_route('lost-items.destroy', $item->id) }}" data-confirm-delete data-confirm-message="Yakin ingin menghapus laporan ini?">
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
                            <td colspan="6" class="empty-row">Belum ada data barang hilang untuk {{ $managerRoleLabelLower }} ini.</td>
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
