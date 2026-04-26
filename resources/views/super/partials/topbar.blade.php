<header class="topbar">
    @if(!empty($topbarBackUrl))
        <a href="{{ $topbarBackUrl }}" class="topbar-back-link" aria-label="{{ $topbarBackLabel ?? 'Kembali' }}">
            <iconify-icon icon="mdi:arrow-left"></iconify-icon>
        </a>
    @endif

    @unless($hideSidebarToggle ?? false)
        <button type="button" class="sidebar-toggle" aria-label="Buka menu" aria-expanded="false" aria-controls="admin-sidebar">
            <span></span>
            <span></span>
            <span></span>
        </button>
    @endunless

    @unless($hideSearch ?? false)
        <form class="search-form" method="GET" action="{{ $searchAction ?? request()->url() }}" role="search">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 21l-4.3-4.3m1.3-5.2a6.5 6.5 0 1 1-13 0a6.5 6.5 0 0 1 13 0z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            <input type="text" name="search" placeholder="{{ $searchPlaceholder ?? 'Cari kecamatan atau instansi' }}" value="{{ request('search') }}">
            @if(request()->filled('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif
        </form>
    @else
        <div class="topbar-spacer" aria-hidden="true"></div>
    @endunless

    <div class="top-actions">
        <button
            type="button"
            class="notification-trigger {{ ($superUnreadNotificationsCount ?? 0) === 0 ? 'is-idle' : '' }}"
            aria-label="Notifikasi"
            aria-expanded="false"
            aria-controls="notification-modal"
        >
            <iconify-icon icon="mdi:bell-outline"></iconify-icon>
            @if(($superUnreadNotificationsCount ?? 0) > 0)
                <span class="notification-badge">{{ $superUnreadNotificationsCount > 9 ? '9+' : $superUnreadNotificationsCount }}</span>
            @endif
        </button>

        <a href="{{ route('super.settings') }}" class="top-action-link {{ ($activeMenu ?? '') === 'settings' ? 'active' : '' }}" aria-label="Pengaturan">
            <iconify-icon icon="mdi:cog-outline"></iconify-icon>
        </a>

        <div class="notification-modal" id="notification-modal">
            <div class="notification-head">
                <strong>Notifikasi Super Admin</strong>
                <div class="notification-head-actions">
                    <a href="{{ route('super.admin-verifications.index') }}" class="notification-link-btn">Buka Verifikasi</a>
                </div>
            </div>

            <div class="notification-tabs" aria-label="Ringkasan notifikasi">
                <span class="notification-tab-info">Butuh tindakan <strong>{{ $superUnreadNotificationsCount ?? 0 }}</strong></span>
            </div>

            <div class="notification-list">
                @forelse(($superNotifications ?? collect()) as $notification)
                    <div class="notification-item {{ ($notification['is_urgent'] ?? false) ? 'is-unread' : 'is-read' }}">
                        <a href="{{ $notification['action_url'] ?? route('super.dashboard') }}" class="notification-main">
                            <div class="notification-text">
                                <strong class="notification-title">{{ $notification['title'] ?? 'Notifikasi Super Admin' }}</strong>
                                <p class="notification-message">{{ $notification['message'] ?? '' }}</p>
                                <small class="notification-meta">
                                    <span class="notification-time">{{ ($notification['created_at'] ?? null)?->diffForHumans() ?? 'Baru saja' }}</span>
                                    <span class="notification-state {{ ($notification['is_urgent'] ?? false) ? 'unread' : 'read' }}">
                                        {{ $notification['tag'] ?? 'Info' }}
                                    </span>
                                </small>
                            </div>
                        </a>
                    </div>
                @empty
                    <div class="notification-empty">Belum ada notifikasi untuk super admin.</div>
                @endforelse
            </div>
        </div>
    </div>
</header>
