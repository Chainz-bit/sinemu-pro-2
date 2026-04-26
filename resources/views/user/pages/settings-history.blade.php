@extends('user.layouts.app')

@php
    $pageTitle = 'Riwayat Akun - User - SiNemu';
    $activeMenu = 'settings';
    $hideSearch = true;
    $hideSidebar = true;
    $topbarBackUrl = route('user.settings');
    $topbarBackLabel = 'Kembali ke Pengaturan';
@endphp

@section('page-content')
    <section class="settings-log-page">
        <header class="settings-log-header">
            <h1>Log / Riwayat</h1>
            <p>Riwayat notifikasi dan pembaruan status yang pernah Anda terima di akun SiNemu.</p>
        </header>

        <section class="settings-log-summary">
            <article>
                <span>Total</span>
                <strong>{{ $summary['total'] }}</strong>
                <small>Semua notifikasi yang pernah tercatat untuk akun Anda.</small>
            </article>
            <article>
                <span>Belum Dibaca</span>
                <strong>{{ $summary['unread'] }}</strong>
                <small>Notifikasi terbaru yang belum Anda buka.</small>
            </article>
            <article>
                <span>Sudah Dibaca</span>
                <strong>{{ $summary['read'] }}</strong>
                <small>Riwayat notifikasi yang sudah Anda tinjau.</small>
            </article>
        </section>

        <section class="report-card settings-log-card">
            <header>
                <form class="settings-log-toolbar" method="GET" action="{{ route('user.settings.history') }}">
                    <div class="settings-log-toolbar-left">
                        <select name="status" class="filter-btn">
                            <option value="" @selected($statusFilter === '')>Semua Status</option>
                            <option value="unread" @selected($statusFilter === 'unread')>Belum Dibaca</option>
                            <option value="read" @selected($statusFilter === 'read')>Sudah Dibaca</option>
                        </select>
                        <select name="type" class="filter-btn">
                            <option value="" @selected($typeFilter === '')>Semua Tipe</option>
                            @foreach($typeOptions as $typeValue => $typeLabel)
                                <option value="{{ $typeValue }}" @selected($typeFilter === $typeValue)>{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                        <input type="date" name="date" class="filter-btn" value="{{ $dateFilter }}">
                    </div>

                    <div class="settings-log-toolbar-right">
                        <input type="text" name="search" class="filter-btn settings-log-search" placeholder="Cari judul/pesan..." value="{{ $search }}">
                        <button type="submit" class="filter-btn">Filter</button>
                        @if($statusFilter !== '' || $typeFilter !== '' || $dateFilter !== '' || $search !== '')
                            <a href="{{ route('user.settings.history') }}" class="filter-btn">Reset</a>
                        @endif
                    </div>
                </form>
            </header>

            <div class="settings-log-toolbar-meta">
                <p class="settings-log-toolbar-note">
                    {{ $summary['total'] > 0 ? 'Gunakan filter untuk mencari notifikasi tertentu lebih cepat.' : 'Riwayat akan muncul otomatis setelah sistem mengirim notifikasi ke akun Anda.' }}
                </p>
            </div>

            <div class="settings-log-list">
                @forelse($histories as $history)
                    @php
                        $typeLabel = str_replace('_', ' ', ucwords((string) $history->type, '_'));
                        $isUnread = is_null($history->read_at);
                        $badgeClass = $isUnread ? 'is-unread' : 'is-read';
                    @endphp
                    <article class="settings-log-item {{ $badgeClass }}">
                        <div class="settings-log-item-main">
                            <div class="settings-log-item-head">
                                <strong>{{ $history->title }}</strong>
                                <span class="settings-log-type">{{ $typeLabel }}</span>
                            </div>
                            <p>{{ $history->message }}</p>
                            <small>
                                {{ $history->created_at?->translatedFormat('d M Y, H:i') }} WIB
                                @if($isUnread)
                                    <span class="status-chip status-dalam_peninjauan">Belum Dibaca</span>
                                @else
                                    <span class="status-chip status-selesai">Sudah Dibaca</span>
                                @endif
                            </small>
                        </div>

                        <div class="settings-log-item-actions">
                            @if(!empty($history->action_url))
                                <a href="{{ $history->action_url }}" class="filter-btn">Buka</a>
                            @endif

                            @if($isUnread)
                                <form method="POST" action="{{ route('user.notifications.read', $history->id) }}">
                                    @csrf
                                    <button type="submit" class="filter-btn">Tandai Dibaca</button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('user.notifications.destroy', $history->id) }}" data-confirm-delete data-confirm-message="Hapus riwayat ini?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="filter-btn danger">Hapus</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="settings-log-empty">
                        <iconify-icon icon="mdi:clipboard-text-clock-outline" aria-hidden="true"></iconify-icon>
                        <strong>Belum ada riwayat</strong>
                        <p>Riwayat notifikasi akan tampil di sini setelah ada pembaruan laporan atau klaim untuk akun Anda.</p>
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
