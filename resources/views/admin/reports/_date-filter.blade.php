@php
    $exportParams = array_merge(request()->query(), ['export' => 'csv']);
@endphp

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ $routeName ? route($routeName) : url()->current() }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <x-input-label for="start_date" :value="__('Start Date')" />
                <x-text-input id="start_date" name="start_date" type="date" value="{{ $start }}" />
            </div>
            <div class="col-md-3">
                <x-input-label for="end_date" :value="__('End Date')" />
                <x-text-input id="end_date" name="end_date" type="date" value="{{ $end }}" />
            </div>

            @isset($groupBy)
                <div class="col-md-3">
                    <x-input-label for="group_by" :value="__('Group By')" />
                    <select id="group_by" name="group_by" class="form-select">
                        <option value="day" @selected($groupBy === 'day')>{{ __('Day') }}</option>
                        <option value="week" @selected($groupBy === 'week')>{{ __('Week') }}</option>
                        <option value="month" @selected($groupBy === 'month')>{{ __('Month') }}</option>
                    </select>
                </div>
            @endisset

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-secondary">{{ __('Filter') }}</button>
                <a href="{{ url()->current() }}?{{ http_build_query($exportParams) }}" class="btn btn-outline-success">{{ __('Export CSV') }}</a>
            </div>
        </form>
    </div>
</div>
