@extends('admin.layouts.app')

@php
    /** BAGIAN: Meta Halaman */
    $pageTitle = 'Verifikasi Klaim - SiNemu';
    $activeMenu = 'claim-verifications';
    $searchAction = route('admin.claim-verifications');
    $searchPlaceholder = 'Cari laporan atau barang';
@endphp

@section('page-content')
    {{-- BAGIAN: Pembuka --}}
    <section class="intro">
        <h1>Verifikasi Klaim</h1>
        <p>Tinjau dan verifikasi permintaan klaim barang temuan dari pengguna.</p>
    </section>

    {{-- BAGIAN: Notifikasi --}}
    @if(session('status'))
        <div class="report-card" style="margin-bottom:12px;">
            <header><h2 style="font-size:14px;">{{ session('status') }}</h2></header>
        </div>
    @endif
    @if(session('error'))
        <div class="report-card" style="margin-bottom:12px;">
            <header><h2 style="font-size:14px;color:#b91c1c;">{{ session('error') }}</h2></header>
        </div>
    @endif

    {{-- BAGIAN: Toolbar + Tabel --}}
    <section class="report-card">
        <header>
            <form class="lost-toolbar" method="GET" action="{{ route('admin.claim-verifications') }}">
                <div class="lost-toolbar-left">
                    <select name="sort" class="filter-btn">
                        <option value="terbaru" @selected($sort === 'terbaru')>Urutkan Berdasarkan...</option>
                        <option value="terlama" @selected($sort === 'terlama')>Urutkan: Terlama</option>
                    </select>
                    <select name="status" class="filter-btn">
                        <option value="">Semua Status</option>
                        <option value="pending" @selected(request('status') === 'pending')>Sedang Ditinjau</option>
                        <option value="disetujui" @selected(request('status') === 'disetujui')>Disetujui</option>
                        <option value="ditolak" @selected(request('status') === 'ditolak')>Ditolak</option>
                    </select>
                </div>
                <div class="lost-toolbar-right">
                    <input type="date" class="filter-btn" name="date" value="{{ request('date') }}">
                    @if(request()->filled('search'))
                        <input type="hidden" name="search" value="{{ request('search') }}">
                    @endif
                    @if(request()->filled('date') || request()->filled('status') || request('sort', 'terbaru') !== 'terbaru' || request()->filled('search'))
                        <a href="{{ route('admin.claim-verifications') }}" class="filter-btn">Hapus Filter</a>
                    @endif
                    <button type="submit" name="export" value="1" class="filter-btn export-btn">
                        <iconify-icon icon="mdi:download-outline"></iconify-icon>
                        Export Data
                    </button>
                </div>
            </form>
        </header>

        <div class="report-table-wrap">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Detail Klaim</th>
                        <th>Pelapor</th>
                        <th>Tanggal Klaim</th>
                        <th>Lokasi Penyimpanan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($claims as $index => $claim)
                        <tr>
                            <td>
                                <div class="item-cell">
                                    <div class="item-avatar avatar-mint">{{ strtoupper(substr($claim->barang_temuan, 0, 1)) }}</div>
                                    <div>
                                        <strong>{{ $claim->barang_temuan }}</strong>
                                        <small>Klaim untuk: {{ $claim->barang_hilang }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $claim->pelapor_nama }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($claim->created_at)->format('d M Y') }}</td>
                            <td>{{ $claim->lokasi }}</td>
                            <td>
                                @php
                                    $statusMap = [
                                        'pending' => ['SEDANG DITINJAU', 'status-diproses'],
                                        'disetujui' => ['DISETUJUI', 'status-selesai'],
                                        'ditolak' => ['DITOLAK', 'status-ditolak'],
                                    ];
                                    [$statusLabel, $statusClass] = $statusMap[$claim->status_klaim] ?? ['UNKNOWN', 'status-diproses'];
                                    if ($claim->status_klaim === 'disetujui' && $claim->status_barang_temuan === 'sudah_dikembalikan') {
                                        $statusLabel = 'SELESAI';
                                    }
                                @endphp
                                <span class="status-chip {{ $statusClass }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="menu-cell">
                                <button type="button" class="row-menu-trigger" data-menu-target="menu-claim-{{ $index }}" aria-label="Aksi">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5.5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3zm0 5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3zm0 5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3z" fill="currentColor"/></svg>
                                </button>
                                <div class="row-menu" id="menu-claim-{{ $index }}">
                                    <a href="{{ route('admin.claim-verifications.show', $claim->id) }}">Lihat Detail</a>
                                    <a href="{{ route('admin.claim-verifications.show', $claim->id) }}">Tinjau Klaim</a>
                                    @php
                                        $isPublished = (bool) ($claim->barang_tampil_di_home ?? false) || (bool) ($claim->laporan_hilang_tampil_di_home ?? false);
                                        $uploadType = !empty($claim->barang_id) ? 'temuan' : (!empty($claim->laporan_hilang_id) ? 'hilang' : null);
                                        $uploadId = !empty($claim->barang_id) ? $claim->barang_id : (!empty($claim->laporan_hilang_id) ? $claim->laporan_hilang_id : null);
                                    @endphp
                                    @if(!$isPublished && !is_null($uploadType) && !is_null($uploadId))
                                        <form method="POST" action="{{ route('admin.dashboard.reports.publish-home', ['type' => $uploadType, 'id' => $uploadId]) }}">
                                            @csrf
                                            <button type="submit" class="menu-submit">Upload</button>
                                        </form>
                                    @elseif($isPublished)
                                        <span class="row-menu-note">Sudah di-upload</span>
                                    @endif
                                    <form method="POST" action="{{ route('admin.claim-verifications.destroy', $claim->id) }}" data-confirm-delete data-confirm-message="Yakin ingin menghapus data ini?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="menu-submit danger">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-row">Belum ada data klaim untuk diverifikasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <footer class="pagination">
            @if($claims->onFirstPage())
                <button type="button" disabled>Sebelumnya</button>
            @else
                <button type="button" onclick="window.location.href='{{ $claims->previousPageUrl() }}'">Sebelumnya</button>
            @endif

            @for($page = 1; $page <= $claims->lastPage(); $page++)
                <button type="button" class="{{ $claims->currentPage() === $page ? 'active' : '' }}" onclick="window.location.href='{{ $claims->url($page) }}'">{{ $page }}</button>
            @endfor

            @if($claims->hasMorePages())
                <button type="button" onclick="window.location.href='{{ $claims->nextPageUrl() }}'">Selanjutnya</button>
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
