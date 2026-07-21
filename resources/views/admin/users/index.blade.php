<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">{{ __('User Management') }}</h2>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-person-plus me-1"></i>{{ __('Add User') }}
            </a>
        </div>
    </x-slot>

    {{-- Filter bar --}}
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.users.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <x-input-label for="filter_name" :value="__('Name')" />
                    <x-text-input id="filter_name" name="name" type="text" value="{{ request('name') }}" />
                </div>
                <div class="col-md-3">
                    <x-input-label for="filter_email" :value="__('Email')" />
                    <x-text-input id="filter_email" name="email" type="text" value="{{ request('email') }}" />
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
                    <button type="submit" class="btn btn-secondary">{{ __('Filter') }}</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>
    </div>

    {{-- User table --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table table-bordered mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Email') }}</th>
                        <th>{{ __('Role') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Last Login') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
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
                            <td>
                                {{ $user->name }}
                                @if ($isSelf)
                                    <span class="badge bg-info text-dark ms-1">{{ __('You') }}</span>
                                @endif
                                @if ($user->force_password_reset)
                                    <span class="badge bg-warning text-dark ms-1" title="{{ __('Pending password reset') }}">
                                        <i class="bi bi-key"></i>
                                    </span>
                                @endif
                            </td>
                            <td>{{ $user->email }}</td>
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
                            <td class="text-end">
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-secondary btn-sm">
                                    {{ __('Edit') }}
                                </a>

                                {{-- Reset Password modal trigger --}}
                                <button type="button"
                                    class="btn btn-outline-warning btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#resetPwd{{ $user->id }}">
                                    <i class="bi bi-key"></i>
                                </button>

                                {{-- Delete --}}
                                @if ($canModify)
                                    <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="d-inline"
                                        onsubmit="return confirm('{{ __('Delete this user? If they have related records they will be deactivated instead.') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('Delete') }}</button>
                                    </form>
                                @else
                                    <button type="button" class="btn btn-outline-danger btn-sm"
                                        disabled
                                        title="{{ $isSelf ? __('Cannot delete your own account') : __('Cannot delete the last active admin') }}">
                                        {{ __('Delete') }}
                                    </button>
                                @endif
                            </td>
                        </tr>

                        {{-- Reset Password modal --}}
                        <x-modal :name="'resetPwd'.$user->id">
                            <form method="POST" action="{{ route('admin.users.reset-password', $user) }}">
                                @csrf
                                <div class="modal-header">
                                    <h2 class="h5 mb-0">{{ __('Reset Password: ') }}{{ $user->name }}</h2>
                                </div>
                                <div class="modal-body">
                                    <p class="text-muted small">
                                        {{ __('Set a temporary password. The user will be prompted to change it on their next login.') }}
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
                                <div class="modal-footer">
                                    <x-secondary-button data-bs-dismiss="modal">{{ __('Cancel') }}</x-secondary-button>
                                    <x-primary-button>{{ __('Set Password') }}</x-primary-button>
                                </div>
                            </form>
                        </x-modal>

                    @empty
                        <tr>
                            <td colspan="6" class="p-0">
                                <x-empty-state
                                    icon="bi-people"
                                    :title="__('No users found')"
                                    :text="__('Try a different filter, or add your first user.')">
                                    <x-slot name="action">
                                        <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm">{{ __('Add User') }}</a>
                                    </x-slot>
                                </x-empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $users->links() }}
    </div>
</x-admin-layout>
