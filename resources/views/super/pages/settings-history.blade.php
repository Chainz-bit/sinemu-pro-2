@extends('super.layouts.app')

@php
    $pageTitle = 'Riwayat Super Admin - SiNemu';
    $activeMenu = 'settings';
    $hideSuperSidebar = true;
    $hideSuperSearch = true;
    $topbarBackUrl = route('super.settings');
    $topbarBackLabel = 'Kembali ke Pengaturan';
@endphp

@section('page-content')
    <section class="settings-log-page">
        <header class="settings-log-header">
            <h1>Log / Riwayat</h1>
            <p>Riwayat verifikasi dan perubahan status admin yang tercatat untuk area super admin.</p>
        </header>

        <section class="settings-log-summary">
            <article>
                <span>Total Riwayat</span>
                <strong>{{ $summary['total'] }}</strong>
                <small>Seluruh data admin yang masuk ke ruang kerja super admin.</small>
            </article>
            <article>
                <span>Menunggu</span>
                <strong>{{ $summary['pending'] }}</strong>
                <small>Pendaftar yang masih menunggu keputusan verifikasi.</small>
            </article>
            <article>
                <span>Sudah Diproses</span>
                <strong>{{ $summary['reviewed'] }}</strong>
                <small>Admin yang sudah aktif atau ditolak pada riwayat terbaru.</small>
            </article>
        </section>

        <section class="report-card settings-log-card">
            <header>
                <form class="settings-log-toolbar" method="GET" action="{{ route('super.settings.history') }}">
                    <div class="settings-log-toolbar-left">
                        <select name="status" class="filter-btn">
                            <option value="" @selected($statusFilter === '')>Semua Status</option>
                            <option value="pending" @selected($statusFilter === 'pending')>Menunggu Verifikasi</option>
                            <option value="active" @selected($statusFilter === 'active')>Aktif</option>
                            <option value="rejected" @selected($statusFilter === 'rejected')>Ditolak</option>
                        </select>
                        <input type="date" name="date" class="filter-btn" value="{{ $dateFilter }}">
                    </div>

                    <div class="settings-log-toolbar-right">
                        <input type="text" name="search" class="filter-btn settings-log-search" placeholder="Cari admin/instansi/email..." value="{{ $search }}">
                        <button type="submit" class="filter-btn">Filter</button>
                        @if($statusFilter !== '' || $dateFilter !== '' || $search !== '')
                            <a href="{{ route('super.settings.history') }}" class="filter-btn">Reset</a>
                        @endif
                    </div>
                </form>
            </header>

            <div class="settings-log-toolbar-meta">
                <p class="settings-log-toolbar-note">
                    {{ $summary['total'] > 0 ? 'Gunakan filter untuk menelusuri riwayat verifikasi admin dengan lebih cepat.' : 'Riwayat akan muncul setelah ada admin yang masuk dan diproses di area super admin.' }}
                </p>
            </div>

            <div class="settings-log-list">
                @forelse($histories as $history)
                    @php
                        $statusKey = $statusPresenter::key($history->status_verifikasi);
                        $statusLabel = $statusPresenter::label($statusKey);
                        $stamp = $history->verified_at ?? $history->updated_at ?? $history->created_at;
                    @endphp
                    <article class="settings-log-item {{ $statusKey === 'pending' ? 'is-unread' : 'is-read' }}">
                        <div class="settings-log-item-main">
                            <div class="settings-log-item-head">
                                <strong>{{ $history->nama }}</strong>
                                <span class="settings-log-type">{{ $statusLabel }}</span>
                            </div>
                            <p>
                                {{ $history->instansi ?: 'Instansi belum diisi' }} ·
                                {{ $history->email }}
                            </p>
                            <small>
                                {{ $stamp?->translatedFormat('d M Y, H:i') ?? '-' }} WIB
                                <span class="status-chip {{ $statusPresenter::badgeClass($statusKey) }}">{{ $statusLabel }}</span>
                            </small>
                        </div>

                        <div class="settings-log-item-actions">
                            <a href="{{ route('super.admins.index', ['search' => $history->nama]) }}" class="filter-btn">Buka</a>
                            <a href="{{ route('super.admin-verifications.index', ['search' => $history->nama]) }}" class="filter-btn">Verifikasi</a>
                        </div>
                    </article>
                @empty
                    <div class="settings-log-empty">
                        <iconify-icon icon="mdi:clipboard-text-clock-outline" aria-hidden="true"></iconify-icon>
                        <strong>Belum ada riwayat</strong>
                        <p>Riwayat verifikasi admin akan tampil di sini setelah ada aktivitas di area super admin.</p>
                    </div>
                @endforelse
            </div>

            @if($histories->hasPages())
                <footer class="pagination">
                    @if($histories->onFirstPage())
                        <button type="button" disabled>Sebelumnya</button>
                    @else
                        <button type="button" onclick="window.location.href='{{ $histories->previousPageUrl() }}'">Sebelumnya</button>
                    @endif

                    @for($page = 1; $page <= $histories->lastPage(); $page++)
                        <button type="button" class="{{ $histories->currentPage() === $page ? 'active' : '' }}" onclick="window.location.href='{{ $histories->url($page) }}'">{{ $page }}</button>
                    @endfor

                    @if($histories->hasMorePages())
                        <button type="button" onclick="window.location.href='{{ $histories->nextPageUrl() }}'">Selanjutnya</button>
                    @else
                        <button type="button" disabled>Selanjutnya</button>
                    @endif
                </footer>
            @endif
        </section>
    </section>
@endsection
