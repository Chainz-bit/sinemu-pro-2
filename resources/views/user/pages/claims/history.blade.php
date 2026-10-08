@extends('user.layouts.app')

@php
    $pageTitle = 'Riwayat Klaim Barang - User - SiNemu';
    $activeMenu = 'claim-history';
    $searchAction = route('user.claim-history');
    $searchPlaceholder = 'Cari riwayat klaim';
@endphp

@section('page-content')
    <div class="dashboard-page-content claim-history-page">
        <section class="intro">
            <h1>Riwayat Klaim Barang</h1>
            <p>Pantau status pengajuan klaim barang temuan Anda di sini.</p>
        </section>

        <section class="report-card claim-status-guide">
            <header>
                <div class="report-heading">
                    <h2>Arti Status Klaim</h2>
                    <p>Gunakan panduan ini untuk mengetahui langkah Anda berikutnya.</p>
                </div>
            </header>
            <div class="claim-status-guide-body d-flex flex-wrap gap-2">
                <span class="status-chip status-dalam_peninjauan">Menunggu Tinjauan</span>
                <span class="status-chip status-diproses">Terverifikasi (Siap Diambil)</span>
                <span class="status-chip status-selesai">Selesai</span>
                <span class="status-chip status-ditolak">Tidak Disetujui</span>
            </div>
        </section>

        <section class="report-card report-card-scrollable dashboard-report-card claim-history-card">
            <header>
                <div class="report-heading">
                    <h2>Daftar Riwayat Klaim</h2>
                    <p>Menampilkan klaim terbaru berdasarkan aktivitas Anda.</p>
                </div>
                <div class="report-actions claim-history-filter-grid row g-2">
                    <form method="GET" action="{{ route('user.claim-history') }}" class="dashboard-filter-form claim-history-filter-form col-12 col-md-auto mb-2 mb-md-0">
                        @if($search !== '')
                            <input type="hidden" name="search" value="{{ $search }}">
                        @endif
                        @if($typeFilter !== '' && $typeFilter !== 'semua')
                            <input type="hidden" name="type" value="{{ $typeFilter }}">
                        @endif
                        <select name="status" class="filter-btn dashboard-filter-select w-100" onchange="this.form.submit()" data-custom-select>
                            <option value="semua" @selected($statusFilter === 'semua')>Semua Status</option>
                            <option value="menunggu_tinjauan" @selected($statusFilter === 'menunggu_tinjauan')>Menunggu Tinjauan</option>
                            <option value="terverifikasi" @selected($statusFilter === 'terverifikasi')>Terverifikasi</option>
                            <option value="sedang_diproses" @selected($statusFilter === 'sedang_diproses')>Sedang Diproses</option>
                            <option value="selesai" @selected($statusFilter === 'selesai')>Selesai</option>
                            <option value="tidak_disetujui" @selected($statusFilter === 'tidak_disetujui')>Tidak Disetujui</option>
                        </select>
                    </form>
                    <form method="GET" action="{{ route('user.claim-history') }}" class="dashboard-filter-form claim-history-filter-form col-12 col-md-auto mb-2 mb-md-0">
                        @if($search !== '')
                            <input type="hidden" name="search" value="{{ $search }}">
                        @endif
                        @if($statusFilter !== '' && $statusFilter !== 'semua')
                            <input type="hidden" name="status" value="{{ $statusFilter }}">
                        @endif
                        <select name="type" class="filter-btn dashboard-filter-select w-100" onchange="this.form.submit()" data-custom-select>
                            <option value="semua" @selected($typeFilter === 'semua')>Semua Jenis</option>
                            <option value="temuan" @selected($typeFilter === 'temuan')>Barang Temuan</option>
                            <option value="hilang" @selected($typeFilter === 'hilang')>Laporan Hilang</option>
                        </select>
                    </form>
                </div>
            </header>

            <div class="report-table-wrap claim-history-desktop-wrap d-none d-md-block">
                <table class="report-table claim-history-table d-none d-md-table">
                    <colgroup>
                        <col class="col-item">
                        <col class="col-date">
                        <col class="col-status">
                        <col class="col-pickup">
                        <col class="col-action">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Detail Barang</th>
                            <th class="d-none d-md-table-cell">Tanggal Pengajuan</th>
                            <th class="d-none d-md-table-cell">Status</th>
                            <th class="d-none d-md-table-cell">Lokasi Pengambilan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($claims as $claim)
                            <tr class="mobile-stack-row">
                                <td class="mobile-stack-primary" data-label="Detail Barang">
                                    <div class="item-cell">
                                        <div class="item-avatar avatar-claim-image">
                                            <img src="{{ $claim->item_image }}" alt="{{ $claim->item_name }}">
                                        </div>
                                        <div>
                                            <strong>{{ $claim->item_name }}</strong>
                                            <small>{{ $claim->item_type }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="mobile-stack-meta" data-label="Tanggal Pengajuan">
                                    <div class="date-cell">
                                        <strong>{{ $claim->submitted_at?->format('d M Y') }}</strong>
                                        <small>{{ $claim->submitted_at?->format('H:i') }} WIB</small>
                                    </div>
                                </td>
                                <td class="mobile-stack-status" data-label="Status">
                                    <span class="status-chip {{ $claim->status_class }}">
                                        {{ $claim->status_text }}
                                    </span>
                                    <small class="claim-status-detail">{{ $claim->status_detail }}</small>
                                    @if($claim->status_key === 'terverifikasi')
                                        <a href="{{ $claim->detail_url }}" class="claim-instruction-badge-link d-inline-flex align-items-center gap-1 mt-2 text-decoration-none fw-semibold" style="font-size: 11px; color: #0284c7;">
                                            <iconify-icon icon="mdi:ticket-confirmation-outline"></iconify-icon>
                                            Instruksi Pengambilan
                                        </a>
                                    @endif
                                </td>
                                <td class="mobile-stack-meta" data-label="Lokasi Pengambilan">{{ $claim->pickup_location }}</td>
                                <td class="menu-cell mobile-stack-action" data-label="Aksi">
                                    <button type="button"
                                            class="row-menu-trigger"
                                            data-menu-target="claim-history-menu-{{ $claim->id }}-{{ $loop->index }}"
                                            aria-label="Aksi">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <circle cx="12" cy="5" r="1.8" fill="currentColor"/>
                                            <circle cx="12" cy="12" r="1.8" fill="currentColor"/>
                                            <circle cx="12" cy="19" r="1.8" fill="currentColor"/>
                                        </svg>
                                    </button>

                                    <div class="row-menu" id="claim-history-menu-{{ $claim->id }}-{{ $loop->index }}">
                                        <a href="{{ $claim->detail_url }}">{{ $claim->action_label ?? 'Lihat Detail' }}</a>
                                        <form method="POST"
                                              action="{{ route('user.claim-history.destroy', ['klaim' => $claim->id, 'search' => $search, 'status' => $statusFilter, 'type' => $typeFilter, 'page' => $claims->currentPage()]) }}"
                                              data-confirm-delete
                                              data-confirm-message="Hapus riwayat klaim ini?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="menu-submit danger">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="empty-row claim-history-empty-row">
                                    <strong>Belum ada riwayat klaim.</strong>
                                    <small>Coba ubah filter status atau kata kunci pencarian.</small>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="claim-history-mobile-list d-grid d-md-none">
                @forelse($claims as $claim)
                    <article class="claim-history-mobile-card">
                        <div class="claim-history-mobile-action">
                            <button type="button"
                                    class="row-menu-trigger"
                                    data-menu-target="claim-history-mobile-menu-{{ $claim->id }}-{{ $loop->index }}"
                                    aria-label="Aksi klaim">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <circle cx="12" cy="5" r="1.8" fill="currentColor"/>
                                    <circle cx="12" cy="12" r="1.8" fill="currentColor"/>
                                    <circle cx="12" cy="19" r="1.8" fill="currentColor"/>
                                </svg>
                            </button>

                            <div class="row-menu" id="claim-history-mobile-menu-{{ $claim->id }}-{{ $loop->index }}">
                                <a href="{{ $claim->detail_url }}">{{ $claim->action_label ?? 'Lihat Detail' }}</a>
                                <form method="POST"
                                      action="{{ route('user.claim-history.destroy', ['klaim' => $claim->id, 'search' => $search, 'status' => $statusFilter, 'type' => $typeFilter, 'page' => $claims->currentPage()]) }}"
                                      data-confirm-delete
                                      data-confirm-message="Hapus riwayat klaim ini?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="menu-submit danger">Hapus</button>
                                </form>
                            </div>
                        </div>

                        <div class="item-cell claim-history-mobile-item">
                            <div class="item-avatar avatar-claim-image">
                                <img src="{{ $claim->item_image }}" alt="{{ $claim->item_name }}">
                            </div>
                            <div>
                                <strong>{{ $claim->item_name }}</strong>
                                <small>{{ $claim->item_type }}</small>
                            </div>
                        </div>

                        <div class="claim-history-mobile-fields">
                            <div>
                                <span>Tanggal Pengajuan</span>
                                <strong>{{ $claim->submitted_at?->format('d M Y') }}</strong>
                                <small>{{ $claim->submitted_at?->format('H:i') }} WIB</small>
                            </div>
                            <div>
                                <span>Status</span>
                                <span class="status-chip {{ $claim->status_class }}">{{ $claim->status_text }}</span>
                                <small>{{ $claim->status_detail }}</small>
                                @if($claim->status_key === 'terverifikasi')
                                    <a href="{{ $claim->detail_url }}" class="claim-instruction-badge-link d-inline-flex align-items-center gap-1 mt-1 text-decoration-none fw-semibold" style="font-size: 11px; color: #0284c7;">
                                        <iconify-icon icon="mdi:ticket-confirmation-outline"></iconify-icon>
                                        Instruksi Pengambilan
                                    </a>
                                @endif
                            </div>
                            <div>
                                <span>Lokasi Pengambilan</span>
                                <strong>{{ $claim->pickup_location }}</strong>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="claim-history-mobile-empty">
                        <strong>Belum ada riwayat klaim.</strong>
                        <small>Coba ubah filter status atau kata kunci pencarian.</small>
                    </div>
                @endforelse
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
    </div>
@endsection
