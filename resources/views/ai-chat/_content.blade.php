{{--
    Shared AI Assistant chat interface for both admin and cashier (wrapped by
    admin/ai-chat/index.blade.php and cashier/ai-chat/index.blade.php with
    their own layout/sidebar). A real chat UI — bubbles, sidebar history,
    markdown, widgets — built on Bootstrap 5 + vanilla JS (no Tailwind/Alpine)
    to stay consistent with the rest of this app's stack.
--}}
<div class="ai-chat-shell" id="ai-chat-shell">
    <aside class="ai-chat-sidebar" id="ai-chat-sidebar">
        <button type="button" class="btn btn-primary w-100 ai-new-chat-btn" id="ai-new-chat-btn">
            <i class="bi bi-plus-lg"></i> {{ __('New Chat') }}
        </button>

        <div class="ai-chat-history" id="ai-chat-history">
            @forelse ($conversationGroups as $groupLabel => $conversations)
                <div class="ai-history-group-label">{{ __($groupLabel) }}</div>
                @foreach ($conversations as $conversation)
                    <div class="ai-history-item {{ $activeConversation && $activeConversation->id === $conversation->id ? 'active' : '' }}" data-id="{{ $conversation->id }}">
                        <i class="bi bi-chat-left-text ai-history-icon"></i>
                        <span class="ai-history-title" data-id="{{ $conversation->id }}">{{ $conversation->title ?? __('New Chat') }}</span>
                        <div class="ai-history-actions">
                            <button type="button" class="ai-history-action-btn ai-rename-btn" title="{{ __('Rename') }}"><i class="bi bi-pencil"></i></button>
                            <button type="button" class="ai-history-action-btn ai-delete-btn" title="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                @endforeach
            @empty
                <div class="text-muted small text-center mt-4 px-2">{{ __('No conversations yet — say hello!') }}</div>
            @endforelse
        </div>
    </aside>

    <div class="ai-chat-main">
        <button type="button" class="btn btn-sm btn-outline-secondary ai-sidebar-toggle d-lg-none" id="ai-sidebar-toggle">
            <i class="bi bi-list"></i> {{ __('Chats') }}
        </button>

        <header class="ai-chat-header">
            <div class="ai-chat-header-title">
                <div class="d-flex align-items-center gap-2">
                    <span class="ai-header-icon">🤖</span>
                    <span class="fw-bold h5 mb-0">{{ __('AI Assistant') }}</span>
                </div>
                <div class="text-muted small">{{ __('Ask anything about inventory, products, suppliers, sales, reports and business insights.') }}</div>
            </div>
            <div class="ai-chat-header-actions">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="ai-new-chat-btn-2">
                    <i class="bi bi-plus-lg"></i> <span class="d-none d-md-inline">{{ __('New Chat') }}</span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" id="ai-clear-chat-btn">
                    <i class="bi bi-trash3"></i> <span class="d-none d-md-inline">{{ __('Clear Chat') }}</span>
                </button>
            </div>
        </header>

        @if (! $geminiConfigured)
            <div class="alert alert-secondary ai-config-alert m-3 mb-0 py-2 small">
                {{ __('The AI service is not configured yet, so answers will show as unavailable until it is added to .env.') }}
            </div>
        @endif

        <div class="ai-chat-messages" id="ai-chat-messages">
            <div class="ai-chat-empty {{ $messages->isNotEmpty() ? 'd-none' : '' }}" id="ai-chat-empty">
                <div class="ai-empty-icon">🤖</div>
                <h3>{{ __('Welcome Foodcity AI Assistant') }}</h3>
                <p class="text-muted">{{ __('Ask me anything about your store — try one of these:') }}</p>
                <div class="ai-suggestion-chips">
                    @foreach ($suggestions as $s)
                        <button type="button" class="ai-chip" data-text="{{ $s['text'] }}">{{ $s['icon'] }} {{ $s['text'] }}</button>
                    @endforeach
                </div>
            </div>

            <div id="ai-messages-list"></div>
        </div>

        <div class="ai-chat-input-area">
            <form id="ai-chat-form" autocomplete="off">
                <div class="ai-input-wrap">
                    <button type="button" class="ai-input-icon-btn" title="{{ __('Attach (coming soon)') }}" disabled>
                        <i class="bi bi-paperclip"></i>
                    </button>
                    <textarea id="ai-chat-textarea" rows="1" placeholder="{{ __('Ask anything...') }}" maxlength="1000"></textarea>
                    <button type="button" class="ai-input-icon-btn" title="{{ __('Voice (coming soon)') }}" disabled>
                        <i class="bi bi-mic"></i>
                    </button>
                    <button type="submit" class="ai-send-btn" id="ai-send-btn" disabled>
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
                <div class="text-center text-muted ai-input-hint">{{ __('Enter to send · Shift+Enter for new line') }}</div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/marked@12.0.2/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3.1.5/dist/purify.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
@php
    $isAdmin = request()->user()->role === 'admin';
    $prefix = $isAdmin ? 'admin.' : 'cashier.';
    $initialMessagesArray = $messages->map(fn ($m) => [
        'id' => $m->id,
        'query' => $m->query,
        'response' => $m->response,
        'feedback' => $m->feedback,
        'widget' => $m->widget,
        'created_at' => $m->created_at->format('H:i'),
    ])->values()->all();
@endphp
<script>
    window.aiChatConfig = {
        askUrl: '{{ route($prefix.'ai-chat.ask') }}',
        switchUrlTemplate: '{{ route($prefix.'ai-chat.conversations.switch', ['conversation' => '__ID__']) }}',
        renameUrlTemplate: '{{ route($prefix.'ai-chat.conversations.rename', ['conversation' => '__ID__']) }}',
        deleteUrlTemplate: '{{ route($prefix.'ai-chat.conversations.delete', ['conversation' => '__ID__']) }}',
        clearUrlTemplate: '{{ route($prefix.'ai-chat.conversations.clear', ['conversation' => '__ID__']) }}',
        feedbackUrlTemplate: '{{ route($prefix.'ai-chat.messages.feedback', ['log' => '__ID__']) }}',
        indexUrl: '{{ route($prefix.'ai-chat.index') }}',
        activeConversationId: {{ $activeConversation?->id ?? 'null' }},
        initialMessages: @json($initialMessagesArray),
    };
</script>
<script src="{{ asset('js/ai-chat.js') }}"></script>
