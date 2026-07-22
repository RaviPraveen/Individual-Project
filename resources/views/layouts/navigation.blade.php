@php
    $showSidebarToggle = $showSidebarToggle ?? false;
    $user = Auth::user();
    $initials = collect(explode(' ', $user?->name ?? 'User'))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
@endphp

<header class="pos-topbar">
    <div class="d-flex align-items-center gap-3">
        @if ($showSidebarToggle)
            <button type="button" id="pos-sidebar-toggle" class="pos-icon-btn d-lg-none" aria-label="{{ __('Toggle menu') }}">
                <i class="bi bi-list fs-5"></i>
            </button>
        @endif
        
        {{-- Live Browser Local Date & Time Widget --}}
        <div class="d-none d-md-flex align-items-center gap-2.5">
            <div class="pos-topbar-pill" title="{{ __('Local Date') }}">
                <i class="bi bi-calendar3 me-2"></i>
                <span id="pos-live-date">Tuesday, July 21, 2026</span>
            </div>
            <div class="pos-topbar-pill" title="{{ __('Local Time') }}">
                <i class="bi bi-clock me-2"></i>
                <span id="pos-live-clock">10:42 AM</span>
            </div>
        </div>
    </div>

    <div class="d-flex align-items-center gap-3">
        {{-- Notifications Bell (admin only) --}}
        @if ($user?->isAdmin())
            @php $unreadNotifications = $unreadNotifications ?? collect(); @endphp
            <div class="dropdown">
                <button class="pos-icon-btn position-relative" data-bs-toggle="dropdown" aria-label="{{ __('Notifications') }}">
                    <i class="bi bi-bell fs-5"></i>
                    @if (($unreadNotificationsCount ?? 0) > 0)
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                            {{ $unreadNotificationsCount > 9 ? '9+' : $unreadNotificationsCount }}
                        </span>
                    @endif
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-md border-0 rounded-4 p-2 mt-2" style="min-width: 340px; max-height: 420px; overflow-y: auto;">
                    <div class="d-flex align-items-center justify-content-between px-2 py-1 mb-1">
                        <span class="fw-bold text-dark">{{ __('Notifications') }}</span>
                        @if ($unreadNotifications->isNotEmpty())
                            <form method="POST" action="{{ route('admin.notifications.read-all') }}">
                                @csrf
                                <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none">{{ __('Mark all read') }}</button>
                            </form>
                        @endif
                    </div>
                    @forelse ($unreadNotifications as $notification)
                        @php
                            $icon = match ($notification->type) {
                                'low_stock' => 'bi-box-seam text-warning',
                                'pending_po' => 'bi-clipboard-check text-info',
                                'near_expiry' => 'bi-hourglass-split text-danger',
                                default => 'bi-bell text-secondary',
                            };
                        @endphp
                        <div class="dropdown-item rounded-3 py-2 px-2 d-flex align-items-start gap-2" style="white-space: normal;">
                            <i class="bi {{ $icon }} mt-1"></i>
                            <div class="flex-grow-1">
                                <a href="{{ $notification->link ?? '#' }}" class="text-dark text-decoration-none small">{{ $notification->message }}</a>
                                <div class="text-muted" style="font-size: 0.7rem;">{{ $notification->created_at->diffForHumans() }}</div>
                            </div>
                            <form method="POST" action="{{ route('admin.notifications.read', $notification) }}">
                                @csrf
                                <button type="submit" class="btn btn-link btn-sm p-0 text-muted" title="{{ __('Mark read') }}">
                                    <i class="bi bi-check2"></i>
                                </button>
                            </form>
                        </div>
                    @empty
                        <div class="text-center text-muted small py-4">{{ __('No new notifications') }}</div>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- Role-specific Quick Link --}}
        @if ($user?->isAdmin())
            <a href="{{ route('cashier.billing.index') }}" class="btn btn-outline-primary rounded-pill px-3.5 d-none d-sm-inline-flex" style="height:40px; align-items:center;">
                <i class="bi bi-cart-check me-1.5"></i> {{ __('POS Billing') }}
            </a>
            <a href="{{ route('admin.ai-chat.index') }}" class="btn btn-outline-primary rounded-pill px-3.5 d-none d-sm-inline-flex" style="height:40px; align-items:center;">
                <i class="bi bi-robot me-1.5"></i> {{ __('AI Insights') }}
            </a>
        @else
            <a href="{{ route('cashier.billing.index') }}" class="btn btn-primary rounded-pill px-3.5 d-none d-sm-inline-flex" style="height:40px; align-items:center;">
                <i class="bi bi-cart-check me-1.5"></i> {{ __('New Billing') }}
            </a>
        @endif

        {{-- Profile Dropdown --}}
        <div class="dropdown">
            <button class="btn d-flex align-items-center gap-2.5 p-1 border-0 rounded-pill pe-3 hover-bg-light" data-bs-toggle="dropdown" style="background: transparent; height: 42px;">
                <span class="avatar">{{ $initials }}</span>
                <div class="d-none d-md-flex flex-column text-start">
                    <span class="small fw-bold text-dark lh-1">{{ $user?->name }}</span>
                    <span class="text-muted text-capitalize lh-1 mt-1" style="font-size: 0.72rem;">{{ $user?->role }}</span>
                </div>
                <i class="bi bi-chevron-down text-muted small ms-1 d-none d-md-inline"></i>
            </button>

            <ul class="dropdown-menu dropdown-menu-end shadow-md border-0 rounded-4 p-2 mt-2" style="min-width: 200px;">
                <li class="px-3 py-2 border-bottom mb-1">
                    <div class="fw-bold text-dark">{{ $user?->name }}</div>
                    <div class="small text-muted">{{ $user?->email }}</div>
                    <span class="badge bg-primary-subtle text-primary text-capitalize mt-1">{{ $user?->role }}</span>
                </li>
                <li>
                    <a class="dropdown-item rounded-3 py-2 px-3 fw-medium" href="{{ route('profile.edit') }}">
                        <i class="bi bi-person-circle me-2 text-primary"></i>{{ __('My Profile') }}
                    </a>
                </li>
                @if ($user?->isAdmin())
                    <li>
                        <a class="dropdown-item rounded-3 py-2 px-3 fw-medium" href="{{ route('admin.users.index') }}">
                            <i class="bi bi-people me-2 text-primary"></i>{{ __('Manage Users') }}
                        </a>
                    </li>
                @endif
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item rounded-3 py-2 px-3 fw-medium text-danger">
                            <i class="bi bi-box-arrow-right me-2"></i>{{ __('Log Out') }}
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
