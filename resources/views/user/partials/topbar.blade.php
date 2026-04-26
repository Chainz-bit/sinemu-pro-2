<header class="topbar {{ ($hideSearch ?? false) ? 'topbar-no-search' : '' }}">
    @if(!empty($topbarBackUrl))
        <a href="{{ $topbarBackUrl }}" class="topbar-back-link" aria-label="{{ $topbarBackLabel ?? 'Kembali' }}">
            <svg class="topbar-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M15 18l-6-6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </a>
    @endif

    @if(!($hideSidebar ?? false))
        <button type="button" class="sidebar-toggle" aria-label="Buka menu" aria-expanded="false" aria-controls="admin-sidebar">
            <span></span>
            <span></span>
            <span></span>
        </button>
    @endif

    @if(!($hideSearch ?? false))
        <form class="search-form" method="GET" action="{{ $searchAction ?? request()->url() }}" role="search">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 21l-4.3-4.3m1.3-5.2a6.5 6.5 0 1 1-13 0a6.5 6.5 0 0 1 13 0z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            <input type="text" name="search" placeholder="{{ $searchPlaceholder ?? 'Cari laporan Anda' }}" value="{{ request('search') }}">
        </form>
    @else
        <div class="topbar-spacer" aria-hidden="true"></div>
    @endif

    @if(!($hideTopActions ?? false))
        <div class="top-actions">
            <button type="button" class="notification-trigger" aria-label="Notifikasi" aria-expanded="false" aria-controls="user-notification-modal">
                <iconify-icon icon="mdi:bell-outline" class="topbar-icon" aria-hidden="true"></iconify-icon>
                @if(($userUnreadNotificationsCount ?? 0) > 0)
                    <span class="notification-badge">{{ $userUnreadNotificationsCount > 9 ? '9+' : $userUnreadNotificationsCount }}</span>
                @endif
            </button>

            <a href="{{ route('user.settings') }}" class="top-action-link {{ ($activeMenu ?? '') === 'settings' ? 'active' : '' }}" aria-label="Pengaturan">
                <iconify-icon icon="mdi:cog-outline" class="topbar-icon" aria-hidden="true"></iconify-icon>
            </a>

            <div class="notification-modal" id="user-notification-modal">
                <div class="notification-head">
                    <strong>Notifikasi Anda</strong>
                    <div class="notification-head-actions">
                        <form method="POST" action="{{ route('user.notifications.read-all') }}">
                            @csrf
                            <button type="submit" class="notification-link-btn">Tandai semua dibaca</button>
                        </form>
                        <form method="POST" action="{{ route('user.notifications.destroy-all') }}" data-confirm-delete data-confirm-message="Hapus semua notifikasi?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="notification-link-btn danger">Hapus semua</button>
                        </form>
                    </div>
                </div>

                <div class="notification-tabs" aria-label="Ringkasan notifikasi">
                    <span class="notification-tab-info">Belum dibaca <strong>{{ $userUnreadNotificationsCount ?? 0 }}</strong></span>
                </div>

                <div class="notification-list">
                    @forelse(($userNotifications ?? collect()) as $notification)
                        <div class="notification-item {{ is_null($notification->read_at) ? 'is-unread' : 'is-read' }}">
                            <a href="{{ $notification->action_url ?: route('user.dashboard') }}" class="notification-main">
                                <div class="notification-text">
                                    <strong class="notification-title">{{ $notification->title }}</strong>
                                    <p class="notification-message">{{ $notification->message }}</p>
                                    <small class="notification-meta">
                                        <span class="notification-time">{{ $notification->created_at?->diffForHumans() }}</span>
                                        @if(is_null($notification->read_at))
                                            <span class="notification-state unread">Belum dibaca</span>
                                        @else
                                            <span class="notification-state read">Sudah dibaca</span>
                                        @endif
                                    </small>
                                </div>
                            </a>
                            <details class="notification-menu-wrap">
                                <summary class="notification-menu-trigger" aria-label="Aksi notifikasi">
                                    <svg class="topbar-icon" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle cx="12" cy="5" r="1.8" fill="currentColor"/>
                                        <circle cx="12" cy="12" r="1.8" fill="currentColor"/>
                                        <circle cx="12" cy="19" r="1.8" fill="currentColor"/>
                                    </svg>
                                </summary>

                                <div class="notification-menu">
                                    @if(is_null($notification->read_at))
                                        <form method="POST" action="{{ route('user.notifications.read', $notification->id) }}">
                                            @csrf
                                            <button type="submit" class="notification-menu-item">Tandai telah dibaca</button>
                                        </form>
                                    @else
                                        <span class="notification-menu-item disabled">Sudah dibaca</span>
                                    @endif

                                    <form method="POST" action="{{ route('user.notifications.destroy', $notification->id) }}" data-confirm-delete data-confirm-message="Hapus notifikasi ini?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="notification-menu-item danger">Hapus pesan</button>
                                    </form>
                                </div>
                            </details>
                        </div>
                    @empty
                        <div class="notification-empty">Belum ada notifikasi dari admin.</div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</header>
