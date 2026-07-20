<x-cashier-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('AI Assistant') }}</h2>
    </x-slot>

    <p class="text-muted small">{{ __('Ask about product availability, pricing, or stock levels.') }}</p>

    <div class="card mb-3">
        <div class="card-body">
            <form method="POST" action="{{ route('cashier.ai-chat.ask') }}">
                @csrf
                <div class="mb-3">
                    <textarea name="message" class="form-control" rows="2" placeholder="{{ __('e.g. How many bags of rice are in stock?') }}" required>{{ old('message') }}</textarea>
                    <x-input-error :messages="$errors->get('message')" />
                </div>
                <x-primary-button>{{ __('Ask') }}</x-primary-button>
            </form>
        </div>
    </div>

    @if (! $geminiConfigured)
        <div class="alert alert-secondary">
            {{ __('The Gemini API key is not configured yet, so answers below will show as unavailable until it is added to .env.') }}
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <h3 class="h6">{{ __('Recent Conversation') }}</h3>
            @forelse ($logs as $log)
                <div class="mb-3 pb-3 border-bottom">
                    <div class="fw-semibold">{{ __('You: ') }}{{ $log->query }}</div>
                    <div class="text-muted">{{ $log->response }}</div>
                    <div class="text-muted small">{{ $log->created_at->format('Y-m-d H:i') }}</div>
                </div>
            @empty
                <p class="text-muted small mb-0">{{ __('No questions asked yet.') }}</p>
            @endforelse
        </div>
    </div>
</x-cashier-layout>
