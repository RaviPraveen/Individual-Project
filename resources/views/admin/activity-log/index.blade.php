<x-admin-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Activity Log') }}</h2>
    </x-slot>

    {{-- Filter bar --}}
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.activity-log.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <x-input-label for="filter_user" :value="__('User')" />
                    <select id="filter_user" name="user_id" class="form-select">
                        <option value="">{{ __('All Users') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <x-input-label for="filter_action" :value="__('Action')" />
                    <x-text-input id="filter_action" name="action" type="text" value="{{ request('action') }}" placeholder="{{ __('e.g. sale.created') }}" />
                </div>
                <div class="col-md-2">
                    <x-input-label for="filter_from" :value="__('From')" />
                    <x-text-input id="filter_from" name="date_from" type="date" value="{{ request('date_from') }}" />
                </div>
                <div class="col-md-2">
                    <x-input-label for="filter_to" :value="__('To')" />
                    <x-text-input id="filter_to" name="date_to" type="date" value="{{ request('date_to') }}" />
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary">{{ __('Filter') }}</button>
                    <a href="{{ route('admin.activity-log.index') }}" class="btn btn-outline-secondary">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-bordered mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('When') }}</th>
                        <th>{{ __('User') }}</th>
                        <th>{{ __('Action') }}</th>
                        <th>{{ __('Description') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="text-nowrap text-muted small">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                            <td>{{ $log->user->name ?? __('System') }}</td>
                            <td><span class="badge bg-secondary">{{ $log->action }}</span></td>
                            <td>{{ $log->description }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-0">
                                <x-empty-state icon="bi-clock-history" :title="__('No activity found')" :text="__('Try a different filter, or check back once actions have been recorded.')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $logs->links() }}
    </div>
</x-admin-layout>
