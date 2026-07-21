<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">{{ __('Bulk Import Products') }}</h2>
            <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>{{ __('Back to Products') }}
            </a>
        </div>
    </x-slot>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-upload me-1"></i> {{ __('Upload CSV') }}</div>
                <div class="card-body">
                    <p class="text-muted small">
                        {{ __('Columns required (any order): name, sku, category, cost price, selling price, stock, reorder level.') }}
                        {{ __('An existing SKU updates that product; a new SKU creates it. Unknown categories are created automatically.') }}
                    </p>
                    <form method="POST" action="{{ route('admin.products.import') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <x-input-label for="csv_file" :value="__('CSV file')" />
                            <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv,text/csv" required>
                            <x-input-error :messages="$errors->get('csv_file')" />
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i>{{ __('Import') }}</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            @if (isset($headerError))
                <div class="alert alert-danger">{{ $headerError }}</div>
            @endif

            @if (isset($summary))
                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-clipboard-data me-1"></i> {{ __('Import Summary') }}</div>
                    <div class="card-body">
                        <div class="d-flex gap-4">
                            <div>
                                <div class="fs-4 fw-bold text-success">{{ $summary['created'] }}</div>
                                <div class="text-muted small">{{ __('Created') }}</div>
                            </div>
                            <div>
                                <div class="fs-4 fw-bold text-primary">{{ $summary['updated'] }}</div>
                                <div class="text-muted small">{{ __('Updated') }}</div>
                            </div>
                            <div>
                                <div class="fs-4 fw-bold text-danger">{{ count($summary['skipped']) }}</div>
                                <div class="text-muted small">{{ __('Skipped') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                @if (! empty($summary['skipped']))
                    <div class="card">
                        <div class="card-header bg-white fw-semibold">{{ __('Skipped Rows') }}</div>
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('Row') }}</th>
                                        <th>{{ __('SKU') }}</th>
                                        <th>{{ __('Reason') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($summary['skipped'] as $row)
                                        <tr>
                                            <td>{{ $row['row'] }}</td>
                                            <td>{{ $row['sku'] }}</td>
                                            <td class="small">{{ $row['reason'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-admin-layout>
