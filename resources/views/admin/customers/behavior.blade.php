<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">{{ __('Purchasing Behavior: ').$customer->name }}</h2>
            <a href="{{ route('admin.customers.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Back') }}</a>
        </div>
    </x-slot>

    @if ($summary['total_orders'] === 0)
        <div class="alert alert-secondary">{{ __('This customer has no purchase history yet.') }}</div>
    @else
        <div class="card mb-3">
            <div class="card-body">
                <h3 class="h6">{{ __('AI Summary') }}</h3>
                @if ($narrative)
                    <p class="mb-0">{{ $narrative }}</p>
                @else
                    <div class="alert alert-secondary mb-0">
                        {{ __('AI narrative is currently unavailable (Gemini API not configured or unreachable). Showing computed data below.') }}
                    </div>
                @endif
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="text-muted small">{{ __('Total Orders') }}</div>
                        <div class="fs-5">{{ $summary['total_orders'] }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">{{ __('Total Spend') }}</div>
                        <div class="fs-5">{{ number_format($summary['total_spend'], 2) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">{{ __('Average Basket') }}</div>
                        <div class="fs-5">{{ number_format($summary['avg_basket'], 2) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">{{ __('Last Purchase') }}</div>
                        <div class="fs-5">{{ \Illuminate\Support\Carbon::parse($summary['last_purchase'])->format('Y-m-d') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Top Categories') }}</th>
                            <th>{{ __('Quantity Purchased') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary['top_categories'] as $cat)
                            <tr>
                                <td>{{ $cat->name }}</td>
                                <td>{{ $cat->qty }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-admin-layout>
