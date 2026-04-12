<header class="topbar {{ ($hideSearch ?? false) ? 'topbar-no-search' : '' }}">
    {{-- BAGIAN: Tombol Sidebar --}}
    <button type="button" class="sidebar-toggle" aria-label="Buka menu" aria-expanded="false" aria-controls="admin-sidebar">
        <span></span>
        <span></span>
        <span></span>
    </button>

    {{-- BAGIAN: Pencarian --}}
    @if(!($hideSearch ?? false))
        <form class="search-form" method="GET" action="{{ $searchAction ?? request()->url() }}" role="search">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 21l-4.3-4.3m1.3-5.2a6.5 6.5 0 1 1-13 0a6.5 6.5 0 0 1 13 0z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            <input type="text" name="search" placeholder="{{ $searchPlaceholder ?? 'Cari laporan atau barang' }}" value="{{ request('search') }}">
        </form>
    @else
        <div class="topbar-spacer" aria-hidden="true"></div>
    @endif

    {{-- BAGIAN: Aksi Utilitas --}}
    <div class="top-actions">
        <button type="button" class="notification-trigger" aria-label="Notifikasi" aria-expanded="false" aria-controls="notification-modal">
            <iconify-icon icon="mdi:bell-outline"></iconify-icon>
            @if(($adminUnreadNotificationsCount ?? 0) > 0)
                <span class="notification-badge">{{ $adminUnreadNotificationsCount > 9 ? '9+' : $adminUnreadNotificationsCount }}</span>
            @endif
        </button>
        <button type="button" aria-label="Pengaturan">
            <iconify-icon icon="mdi:cog-outline"></iconify-icon>
        </button>

        <div class="notification-modal" id="notification-modal">
            <div class="notification-head">
                <strong>Your notifications</strong>
                <form method="POST" action="{{ route('admin.notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="notification-link-btn">Tandai semua dibaca</button>
                </form>
            </div>

            <div class="notification-tabs">
                <button type="button" class="active">View all</button>
                <button type="button">Unread <span>{{ $adminUnreadNotificationsCount ?? 0 }}</span></button>
            </div>

            <div class="notification-list">
                @forelse(($adminNotifications ?? collect()) as $notification)
                    <div class="notification-item">
                        <a href="{{ $notification->action_url ?: '#' }}" class="notification-main">
                            <div class="notification-text">
                                <strong>{{ $notification->title }}: {{ $notification->message }}</strong>
                                <small>{{ $notification->created_at?->diffForHumans() }}</small>
                            </div>
                        </a>
                        @if(is_null($notification->read_at))
                            <form method="POST" action="{{ route('admin.notifications.read', $notification->id) }}">
                                @csrf
                                <button type="submit" class="notification-link-btn">Tandai sudah dibaca</button>
                            </form>
                        @else
                            <span class="notification-dot read"></span>
                        @endif
                    </div>
                @empty
                    <div class="notification-empty">Belum ada notifikasi.</div>
                @endforelse
            </div>

            <a href="{{ route('admin.claim-verifications') }}" class="notification-footer">View all notifications</a>
        </div>
    </div>
</header>
