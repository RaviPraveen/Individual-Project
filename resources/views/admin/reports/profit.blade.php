<x-admin-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Profit / Margin Report') }}</h2>
    </x-slot>

    @include('admin.reports._date-filter', ['routeName' => 'admin.reports.profit'])

    @if ($rows->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-stars me-1 text-gold"></i> {{ __('AI Profit Analysis') }}</div>
            <div class="card-body">
                @if ($narrative)
                    <p class="mb-0">{{ $narrative }}</p>
                @else
                    <div class="alert alert-secondary mb-0">
                        {{ __('AI narrative is currently unavailable (AI service not configured or unreachable). Showing figures below instead.') }}
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('Quantity Sold') }}</th>
                        <th>{{ __('Revenue') }}</th>
                        <th>{{ __('Cost') }}</th>
                        <th>{{ __('Profit') }}</th>
                        <th>{{ __('Margin %') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row->name }}</td>
                            <td>{{ $row->qty_sold }}</td>
                            <td>{{ number_format($row->revenue, 2) }}</td>
                            <td>{{ number_format($row->total_cost, 2) }}</td>
                            <td>{{ number_format($row->profit, 2) }}</td>
                            <td>{{ $row->margin_percent }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-0"><x-empty-state icon="bi-cash-coin" :title="__('No sales in this date range')" /></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
