<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h2 class="h3 mb-0 fw-extrabold text-dark"><i class="bi bi-tags text-primary me-2"></i>{{ __('Product Categories') }}</h2>
                <div class="text-muted small">{{ __('Organize products into categories for POS navigation and revenue reporting.') }}</div>
            </div>
            <a href="{{ route('admin.categories.create') }}" class="btn btn-primary rounded-pill px-4 shadow-sm">
                <i class="bi bi-plus-lg"></i> {{ __('Add New Category') }}
            </a>
        </div>
    </x-slot>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">{{ __('Category Name') }}</th>
                        <th>{{ __('Description') }}</th>
                        <th class="text-center">{{ __('Assigned Products') }}</th>
                        <th class="pe-4 text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td class="ps-4 fw-bold text-dark">{{ $category->name }}</td>
                            <td class="text-muted small">{{ $category->description ?: '-' }}</td>
                            <td class="text-center">
                                <span class="badge bg-primary-subtle text-primary rounded-pill px-3">{{ $category->products_count }} {{ __('products') }}</span>
                            </td>
                            <td class="pe-4 text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-outline-secondary" title="{{ __('Edit Category') }}">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('Delete this category?') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="{{ __('Delete Category') }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-0">
                                <x-empty-state icon="bi-tags" :title="__('No categories created yet')" :text="__('Create your first category to group your products.')">
                                    <x-slot name="action">
                                        <a href="{{ route('admin.categories.create') }}" class="btn btn-primary rounded-pill px-4">{{ __('Add New Category') }}</a>
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
        {{ $categories->links() }}
    </div>
</x-admin-layout>
