@extends('manager::layouts.app')

@php
    $pageTitle = 'Log Aktivitas - Pengaturan - SiNemu';
    $activeMenu = 'settings';
    $hideSidebar = true;
    $hideSearch = true;
    $topbarBackUrl = manager_route('settings');
    $topbarBackLabel = 'Kembali ke Pengaturan';
    $managerRoleLabelLower = \App\Support\RoleLabels::managerLower();
@endphp

@section('page-content')
    <section class="settings-log-page">
<header class="settings-log-header">
            <h1>Log Aktivitas</h1>
            <p>Riwayat notifikasi dan perubahan yang tercatat untuk akun {{ $managerRoleLabelLower }}.</p>
        </header>

        <section class="settings-log-summary">
            <article>
                <span>Total</span>
                <strong>{{ $summary['total'] }}</strong>
                <small>Semua log yang tercatat untuk akun ini.</small>
            </article>
            <article>
                <span>Belum Dibaca</span>
                <strong>{{ $summary['unread'] }}</strong>
                <small>Perlu ditinjau agar aktivitas terbaru tidak terlewat.</small>
            </article>
            <article>
                <span>Sudah Dibaca</span>
                <strong>{{ $summary['read'] }}</strong>
                <small>Aktivitas yang sudah dikonfirmasi oleh {{ $managerRoleLabelLower }}.</small>
            </article>
        </section>

        <section class="report-card settings-log-card">
            <header>
                <form class="settings-log-toolbar" method="GET" action="{{ manager_route('settings.logs') }}">
                    <div class="settings-log-toolbar-left">
                        <select name="status" class="filter-btn" data-custom-select>
                            <option value="" @selected($statusFilter === '')>Semua Status</option>
                            <option value="unread" @selected($statusFilter === 'unread')>Belum Dibaca</option>
                            <option value="read" @selected($statusFilter === 'read')>Sudah Dibaca</option>
                        </select>
                        <select name="type" class="filter-btn" data-custom-select>
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
                            <a href="{{ manager_route('settings.logs') }}" class="filter-btn">Reset</a>
                        @endif
                    </div>
                </form>
            </header>

            <div class="settings-log-toolbar-meta">
                <p class="settings-log-toolbar-note">
                    {{ $summary['total'] > 0 ? 'Gunakan filter untuk mempercepat pencarian log aktivitas.' : 'Log akan muncul otomatis setelah ada notifikasi atau aksi ' . $managerRoleLabelLower . '.' }}
                </p>
            </div>

            <div class="settings-log-list">
                @forelse($logs as $log)
                    @php
                        $typeLabel = str_replace('_', ' ', ucwords((string) $log->type, '_'));
                        $isUnread = is_null($log->read_at);
                        $badgeClass = $isUnread ? 'is-unread' : 'is-read';
                    @endphp
                    <article class="settings-log-item {{ $badgeClass }}">
                        <div class="settings-log-item-main">
                            <div class="settings-log-item-head">
                                <strong>{{ $log->title }}</strong>
                                <span class="settings-log-type">{{ $typeLabel }}</span>
                            </div>
                            <p>{{ $log->message }}</p>
                            <small>
                                {{ $log->created_at?->translatedFormat('d M Y, H:i') }} WIB
                                <x-status-badge :status="$isUnread ? 'unread' : 'read'" type="log" size="sm" />
                            </small>
                        </div>

                        <div class="settings-log-item-actions">
                            <button type="button"
                                    class="row-menu-trigger settings-log-menu-trigger"
                                    data-menu-target="admin-settings-log-menu-{{ $log->id }}-{{ $loop->index }}"
                                    aria-label="Aksi log">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 5.5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3zm0 5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3zm0 5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3z" fill="currentColor"/>
                                </svg>
                            </button>

                            <div class="row-menu settings-log-menu" id="admin-settings-log-menu-{{ $log->id }}-{{ $loop->index }}">
                                @if(!empty($log->action_url))
                                    <a href="{{ $log->action_url }}">
                                        <iconify-icon icon="mdi:eye-outline" aria-hidden="true"></iconify-icon>
                                        <span>Buka Detail</span>
                                    </a>
                                @endif

                                @if($isUnread)
                                    <form method="POST" action="{{ manager_route('notifications.read', $log->id) }}">
                                        @csrf
                                        <button type="submit" class="menu-submit">
                                            <iconify-icon icon="mdi:check" aria-hidden="true"></iconify-icon>
                                            <span>Tandai Dibaca</span>
                                        </button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ manager_route('notifications.destroy', $log->id) }}" data-confirm-delete data-confirm-message="Hapus log ini?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="menu-submit danger">
                                        <iconify-icon icon="mdi:trash-can-outline" aria-hidden="true"></iconify-icon>
                                        <span>Hapus Log</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="settings-log-empty">
                        <iconify-icon icon="mdi:clipboard-text-clock-outline" aria-hidden="true"></iconify-icon>
                        <strong>Belum ada log aktivitas</strong>
                        <p>Riwayat akan tampil di sini setelah sistem mencatat notifikasi atau perubahan data.</p>
                    </div>
                @endforelse
            </div>

            @if($logs->hasPages())
                <footer class="pagination">
                    @if($logs->onFirstPage())
                        <button type="button" disabled>Sebelumnya</button>
                    @else
                        <button type="button" onclick="window.location.href='{{ $logs->previousPageUrl() }}'">Sebelumnya</button>
                    @endif

                    @for($page = 1; $page <= $logs->lastPage(); $page++)
                        <button type="button" class="{{ $logs->currentPage() === $page ? 'active' : '' }}" onclick="window.location.href='{{ $logs->url($page) }}'">{{ $page }}</button>
                    @endfor

                    @if($logs->hasMorePages())
                        <button type="button" onclick="window.location.href='{{ $logs->nextPageUrl() }}'">Selanjutnya</button>
                    @else
                        <button type="button" disabled>Selanjutnya</button>
                    @endif
                </footer>
            @endif
        </section>
    </section>
@endsection
