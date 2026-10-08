@extends('manager::layouts.app')

@php
    /** BAGIAN: Meta Halaman */
    $pageTitle = 'Verifikasi Klaim - SiNemu';
    $activeMenu = 'claim-verifications';
    $searchAction = manager_route('claim-verifications');
    $searchPlaceholder = 'Cari laporan atau barang';
@endphp

@section('page-content')
    {{-- BAGIAN: Pembuka --}}
    <section class="intro">
        <h1>Verifikasi Klaim</h1>
        <p>Tinjau dan verifikasi permintaan klaim barang temuan dari pengguna.</p>
    </section>

    {{-- BAGIAN: Notifikasi --}}
{{-- BAGIAN: Toolbar + Tabel --}}
    <section class="report-card report-card-scrollable">
        <header>
            <form class="lost-toolbar" method="GET" action="{{ manager_route('claim-verifications') }}">
                <div class="lost-toolbar-left">
                    <select name="sort" class="filter-btn" data-custom-select>
                        <option value="terbaru" @selected($sort === 'terbaru')>Urutkan Berdasarkan...</option>
                        <option value="terlama" @selected($sort === 'terlama')>Urutkan: Terlama</option>
                    </select>
                    <select name="status" class="filter-btn" data-custom-select>
                        <option value="">Semua Status</option>
                        <option value="menunggu" @selected(in_array(request('status'), ['menunggu','pending'], true))>Menunggu Verifikasi</option>
                        <option value="disetujui" @selected(request('status') === 'disetujui')>Disetujui</option>
                        <option value="ditolak" @selected(request('status') === 'ditolak')>Ditolak</option>
                        <option value="selesai" @selected(request('status') === 'selesai')>Selesai</option>
                    </select>
                </div>
                <div class="lost-toolbar-right">
                    <input type="date" class="filter-btn" name="date" value="{{ request('date') }}">
                    @if(request()->filled('search'))
                        <input type="hidden" name="search" value="{{ request('search') }}">
                    @endif
                    @if(request()->filled('date') || request()->filled('status') || request('sort', 'terbaru') !== 'terbaru' || request()->filled('search'))
                        <a href="{{ manager_route('claim-verifications') }}" class="filter-btn">Hapus Filter</a>
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
                                    <div class="item-avatar avatar-mint">
                                        <span class="item-avatar-fallback">{{ strtoupper(substr($claim->barang_temuan, 0, 1)) }}</span>
                                        @php
                                            $fotoUrlDefault = asset('img/login-image.png');
                                            $fotoSrc = null;
                                            $rawFotoPath = str_replace('\\', '/', trim((string) ($claim->foto_barang ?? '')));
                                            $localFotoPath = null;

                                            if ($rawFotoPath !== '') {
                                                if (\Illuminate\Support\Str::startsWith($rawFotoPath, ['http://', 'https://'])) {
                                                    $fotoSrc = $rawFotoPath;
                                                } else {
                                                    $fotoPath = ltrim($rawFotoPath, '/');
                                                    if (\Illuminate\Support\Str::startsWith($fotoPath, 'storage/')) {
                                                        $fotoPath = substr($fotoPath, 8);
                                                    } elseif (\Illuminate\Support\Str::startsWith($fotoPath, 'public/')) {
                                                        $fotoPath = substr($fotoPath, 7);
                                                    }

                                                    $localFotoPath = $fotoPath;
                                                }
                                            }

                                            if (!empty($localFotoPath) && \Illuminate\Support\Facades\Storage::disk('public')->exists($localFotoPath)) {
                                                $absolutePath = \Illuminate\Support\Facades\Storage::disk('public')->path($localFotoPath);
                                                $mimeType = mime_content_type($absolutePath) ?: 'image/jpeg';
                                                $binary = @file_get_contents($absolutePath);
                                                if ($binary !== false) {
                                                    $fotoSrc = 'data:' . $mimeType . ';base64,' . base64_encode($binary);
                                                }
                                            }

                                            if (!$fotoSrc) {
                                                $fotoSrc = $fotoUrlDefault;
                                            }
                                        @endphp
                                        <img
                                            src="{{ $fotoSrc }}"
                                            alt="{{ $claim->barang_temuan }}"
                                            loading="lazy"
                                            decoding="async"
                                            width="30"
                                            height="30"
                                            onerror="this.onerror=null;this.src='{{ $fotoUrlDefault }}';"
                                        >
                                    </div>
                                    <div>
                                        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                            <strong>{{ $claim->barang_temuan }}</strong>
                                            <span style="display: inline-block; font-size: 0.72rem; font-family: monospace; font-weight: 700; color: #1e40af; background: #dbeafe; border: 1px solid #bfdbfe; border-radius: 4px; padding: 1px 5px; letter-spacing: 0.02em;">#KLM-{{ str_pad((string) $claim->id, 5, '0', STR_PAD_LEFT) }}</span>
                                        </div>
                                        <small>Klaim untuk: {{ $claim->barang_hilang }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $claim->pelapor_nama }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($claim->created_at)->format('d M Y') }}</td>
                            <td>{{ $claim->lokasi }}</td>
                            <td>
                                @php
                                    $statusKey = \App\Support\ClaimStatusPresenter::key(
                                        (string) $claim->status_klaim,
                                        (string) ($claim->status_verifikasi ?? ''),
                                        (string) ($claim->status_barang_temuan ?? '')
                                    );
                                    $statusLabel = \App\Support\ClaimStatusPresenter::label($statusKey);
                                    $statusClass = \App\Support\ClaimStatusPresenter::cssClass($statusKey);
                                @endphp
                                <span class="status-chip {{ $statusClass }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="menu-cell">
                                <button type="button" class="row-menu-trigger" data-menu-target="menu-claim-{{ $index }}" aria-label="Aksi">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5.5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3zm0 5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3zm0 5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3z" fill="currentColor"/></svg>
                                </button>
                                <div class="row-menu" id="menu-claim-{{ $index }}">
                                    <a href="{{ manager_route('claim-verifications.show', $claim->id) }}">
                                        {{ $statusKey === 'menunggu' ? 'Tinjau Klaim' : 'Lihat Detail' }}
                                    </a>
                                    @php
                                        $isPublished = (bool) ($claim->barang_tampil_di_home ?? false) || (bool) ($claim->laporan_hilang_tampil_di_home ?? false);
                                        $uploadType = !empty($claim->barang_id) ? 'temuan' : (!empty($claim->laporan_hilang_id) ? 'hilang' : null);
                                        $uploadId = !empty($claim->barang_id) ? $claim->barang_id : (!empty($claim->laporan_hilang_id) ? $claim->laporan_hilang_id : null);
                                    @endphp
                                    @if(!$isPublished && !is_null($uploadType) && !is_null($uploadId))
                                        <form method="POST" action="{{ manager_route('dashboard.reports.publish-home', ['type' => $uploadType, 'id' => $uploadId]) }}">
                                            @csrf
                                            <button type="submit" class="menu-submit">Upload</button>
                                        </form>
                                    @elseif($isPublished)
                                        <span class="row-menu-note">Sudah di-upload</span>
                                    @endif
                                    <form method="POST" action="{{ manager_route('claim-verifications.destroy', $claim->id) }}" data-confirm-delete data-confirm-message="Yakin ingin menghapus data ini?">
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
