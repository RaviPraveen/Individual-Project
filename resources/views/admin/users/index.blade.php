<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h2 class="h3 mb-0 fw-extrabold text-dark"><i class="bi bi-people-fill text-primary me-2"></i>{{ __('User Accounts & Access') }}</h2>
                <div class="text-muted small">{{ __('Manage Admin & Cashier staff credentials, permissions, and security resets.') }}</div>
            </div>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary rounded-pill px-4 shadow-sm">
                <i class="bi bi-person-plus me-1"></i> {{ __('Add New User') }}
            </a>
        </div>
    </x-slot>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3.5">
            <form method="GET" action="{{ route('admin.users.index') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <x-input-label for="filter_name" :value="__('Name')" />
                    <x-text-input id="filter_name" name="name" type="text" value="{{ request('name') }}" placeholder="{{ __('Search name...') }}" />
                </div>
                <div class="col-md-3">
                    <x-input-label for="filter_email" :value="__('Email Address')" />
                    <x-text-input id="filter_email" name="email" type="text" value="{{ request('email') }}" placeholder="{{ __('Search email...') }}" />
                </div>
                <div class="col-md-2">
                    <x-input-label for="filter_role" :value="__('Role')" />
                    <select id="filter_role" name="role" class="form-select">
                        <option value="">{{ __('All Roles') }}</option>
                        <option value="admin"   @selected(request('role') === 'admin')>{{ __('Admin') }}</option>
                        <option value="cashier" @selected(request('role') === 'cashier')>{{ __('Cashier') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <x-input-label for="filter_status" :value="__('Status')" />
                    <select id="filter_status" name="status" class="form-select">
                        <option value="">{{ __('All Statuses') }}</option>
                        <option value="active"   @selected(request('status') === 'active')>{{ __('Active') }}</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>{{ __('Inactive') }}</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary fw-bold flex-grow-1"><i class="bi bi-funnel"></i> {{ __('Filter') }}</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table Card -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">{{ __('User Name') }}</th>
                        <th>{{ __('Email Address') }}</th>
                        <th>{{ __('Role') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Last Login') }}</th>
                        <th class="pe-4 text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        @php
                            $isSelf         = $user->id === auth()->id();
                            $activeAdmins   = \App\Models\User::where('role', 'admin')->where('is_active', true)->count();
                            $isLastAdmin    = $user->role === 'admin' && $user->is_active && $activeAdmins === 1;
                            $canModify      = ! $isSelf && ! $isLastAdmin;
                        @endphp
                        <tr>
                            <td class="ps-4 fw-bold text-dark">
                                {{ $user->name }}
                                @if ($isSelf)
                                    <span class="badge bg-info-subtle text-info rounded-pill ms-1">{{ __('You') }}</span>
                                @endif
                                @if ($user->force_password_reset)
                                    <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill ms-1" title="{{ __('Pending password reset on next login') }}">
                                        <i class="bi bi-key-fill"></i> Reset Required
                                    </span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $user->email }}</td>
                            <td>
                                <span class="badge {{ $user->role === 'admin' ? 'bg-primary' : 'bg-secondary' }}">
                                    {{ ucfirst($user->role) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $user->is_active ? 'bg-success' : 'bg-danger' }}">
                                    {{ $user->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            <td class="text-muted small">
                                {{ $user->last_login_at ? $user->last_login_at->diffForHumans() : __('Never') }}
                            </td>
                            <td class="pe-4 text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-secondary" title="{{ __('Edit User') }}">
                                        <i class="bi bi-pencil"></i>
                                    </a>

                                    <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#resetPwd{{ $user->id }}" title="{{ __('Force Password Reset') }}">
                                        <i class="bi bi-key"></i>
                                    </button>

                                    @if ($canModify)
                                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('Delete this user? If they have related records they will be deactivated instead.') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="{{ __('Delete User') }}"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @else
                                        <button type="button" class="btn btn-outline-danger" disabled title="{{ $isSelf ? __('Cannot delete your own account') : __('Cannot delete the last active admin') }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        <!-- Reset Password Modal -->
                        <x-modal :name="'resetPwd'.$user->id">
                            <form method="POST" action="{{ route('admin.users.reset-password', $user) }}">
                                @csrf
                                <div class="modal-header border-bottom">
                                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-key text-warning me-2"></i>{{ __('Reset Password: ') }}{{ $user->name }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body p-4">
                                    <p class="text-muted small mb-3">
                                        {{ __('Set a temporary password. The user will be required to change it on their next login.') }}
                                    </p>
                                    <div class="mb-3">
                                        <x-input-label :for="'temp_password'.$user->id" :value="__('New Password')" />
                                        <x-text-input :id="'temp_password'.$user->id" name="temp_password" type="password" required />
                                    </div>
                                    <div class="mb-3">
                                        <x-input-label :for="'temp_password_confirmation'.$user->id" :value="__('Confirm Password')" />
                                        <x-text-input :id="'temp_password_confirmation'.$user->id" name="temp_password_confirmation" type="password" required />
                                    </div>
                                </div>
                                <div class="modal-footer border-top bg-light">
                                    <x-secondary-button data-bs-dismiss="modal">{{ __('Cancel') }}</x-secondary-button>
                                    <x-primary-button>{{ __('Set Password') }}</x-primary-button>
                                </div>
                            </form>
                        </x-modal>
                    @empty
                        <tr>
                            <td colspan="6" class="p-0">
                                <x-empty-state icon="bi-people" :title="__('No users found')" :text="__('Try adjusting your search criteria, or add a new user.')">
                                    <x-slot name="action">
                                        <a href="{{ route('admin.users.create') }}" class="btn btn-primary rounded-pill px-4">{{ __('Add New User') }}</a>
                                    </x-slot>
                                </x-empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
</x-admin-layout>
