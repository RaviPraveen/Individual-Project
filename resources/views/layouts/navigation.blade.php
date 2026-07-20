@php
    $showSidebarToggle = $showSidebarToggle ?? false;
    $initials = collect(explode(' ', Auth::user()->name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
@endphp

<header class="pos-topbar">
    <div class="d-flex align-items-center gap-3">
        @if ($showSidebarToggle)
            <button type="button" id="pos-sidebar-toggle" class="pos-icon-btn d-lg-none" aria-label="{{ __('Toggle menu') }}">
                <i class="bi bi-list fs-5"></i>
            </button>
        @endif
        <div class="d-none d-md-block text-muted small">
            {{ now()->format('l, F j, Y') }}
        </div>
    </div>

    <div class="d-flex align-items-center gap-2">
        <div class="dropdown">
            <button class="btn d-flex align-items-center gap-2 border-0" data-bs-toggle="dropdown" style="background:transparent;">
                <span class="avatar">{{ $initials }}</span>
                <span class="d-none d-md-inline small fw-semibold">{{ Auth::user()->name }}</span>
                <i class="bi bi-chevron-down small d-none d-md-inline"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><h6 class="dropdown-header text-capitalize">{{ Auth::user()->role }}</h6></li>
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>{{ __('Profile') }}</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>{{ __('Log Out') }}</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
