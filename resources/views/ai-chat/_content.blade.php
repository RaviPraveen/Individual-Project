{{--
    Redesigned Foodcity AI Copilot Interface (ChatGPT / Claude / Gemini Style)
    Preserves all backend routes, variables, and API contracts.
--}}
<div class="ai-copilot-shell" id="ai-chat-shell">
    <!-- Left Sidebar (280px) -->
    <aside class="ai-copilot-sidebar" id="ai-chat-sidebar">
        <!-- Logo & Header -->
        <div class="ai-sidebar-header">
            <div class="d-flex align-items-center gap-2.5">
                <span class="ai-copilot-mark">✨</span>
                <div class="d-flex flex-column">
                    <span class="fw-extrabold text-white lh-1 fs-6">Foodcity AI</span>
                    <span class="text-indigo-400 fw-bold uppercase mt-0.5" style="font-size:0.65rem; letter-spacing:0.08em; color: #A5B4FC;">Copilot v2026</span>
                </div>
            </div>
        </div>

        <!-- New Chat Action -->
        <button type="button" class="btn ai-btn-new-chat w-100" id="ai-new-chat-btn">
            <i class="bi bi-plus-lg fs-5"></i>
            <span>{{ __('New Chat') }}</span>
        </button>

        <!-- Search Conversations Input -->
        <div class="ai-search-box mt-3 mb-2">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-transparent border-0 text-muted pe-1"><i class="bi bi-search"></i></span>
                <input type="text" id="ai-search-chats" class="form-control bg-transparent border-0 text-white shadow-none ps-1" placeholder="{{ __('Search chats...') }}" style="font-size:0.85rem;">
            </div>
        </div>

        <!-- Conversation History List -->
        <div class="ai-chat-history flex-grow-1 overflow-auto" id="ai-chat-history">
            @forelse ($conversationGroups as $groupLabel => $conversations)
                <div class="ai-history-group-label">{{ __($groupLabel) }}</div>
                @foreach ($conversations as $conversation)
                    <div class="ai-history-item {{ $activeConversation && $activeConversation->id === $conversation->id ? 'active' : '' }}" data-id="{{ $conversation->id }}">
                        <i class="bi bi-chat-left-text ai-history-icon"></i>
                        <span class="ai-history-title truncate" data-id="{{ $conversation->id }}">{{ $conversation->title ?? __('New Chat') }}</span>
                        <div class="ai-history-actions">
                            <button type="button" class="ai-history-action-btn ai-rename-btn" title="{{ __('Rename') }}"><i class="bi bi-pencil"></i></button>
                            <button type="button" class="ai-history-action-btn ai-delete-btn" title="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                @endforeach
            @empty
                <div class="text-muted small text-center mt-4 px-2">{{ __('No recent chats. Start a new conversation!') }}</div>
            @endforelse
        </div>
    </aside>

    <!-- Main Chat Workspace -->
    <div class="ai-copilot-main d-flex flex-column">
        <!-- Top Workspace Bar -->
        <header class="ai-copilot-header bg-white">
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="btn btn-sm btn-outline-secondary ai-sidebar-toggle d-lg-none rounded-pill px-3" id="ai-sidebar-toggle">
                    <i class="bi bi-layout-sidebar me-1"></i> {{ __('Chats') }}
                </button>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary-subtle text-primary rounded-pill fw-bold"><i class="bi bi-lightning-charge-fill me-1"></i> {{ __('Foodcity AI Copilot') }}</span>
                    <span class="text-muted small d-none d-md-inline">&middot; {{ __('Context Aware Retail Intelligence') }}</span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" id="ai-new-chat-btn-2">
                    <i class="bi bi-plus-lg"></i> <span class="d-none d-md-inline">{{ __('New') }}</span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" id="ai-clear-chat-btn">
                    <i class="bi bi-trash3"></i> <span class="d-none d-md-inline">{{ __('Clear Chat') }}</span>
                </button>
            </div>
        </header>

        @if (! $geminiConfigured)
            <div class="alert alert-warning border-0 bg-warning-subtle text-warning-emphasis m-3 mb-0 py-2.5 px-3 rounded-3 small d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <div>{{ __('The AI API service is not configured in .env. Add GEMINI_API_KEY to enable live intelligence responses.') }}</div>
            </div>
        @endif

        <!-- Conversation Scrollable Viewport -->
        <div class="ai-copilot-messages-viewport" id="ai-chat-messages">
            <!-- Welcome Hero Screen (Shown when no messages) -->
            <div class="ai-welcome-hero my-auto text-center py-5 {{ $messages->isNotEmpty() ? 'd-none' : '' }}" id="ai-chat-empty">
                <div class="ai-welcome-badge mb-3">✨</div>
                <h1 class="h2 fw-extrabold text-dark mb-2">{{ __('Hello, I\'m Foodcity AI Copilot.') }}</h1>
                <p class="text-muted fs-6 mb-4 max-w-lg mx-auto">
                    {{ __('I can help you analyze products, sales performance, inventory stock, supplier orders, and generate instant business intelligence.') }}
                </p>

                <div class="ai-suggestion-grid max-w-2xl mx-auto">
                    <button type="button" class="ai-suggestion-card" data-text="Which products need reordering?">
                        <span class="icon">📦</span>
                        <span class="text">Which products need reordering?</span>
                    </button>
                    <button type="button" class="ai-suggestion-card" data-text="Show today's sales summary">
                        <span class="icon">📈</span>
                        <span class="text">Show today's sales summary</span>
                    </button>
                    <button type="button" class="ai-suggestion-card" data-text="Revenue this month by category">
                        <span class="icon">💰</span>
                        <span class="text">Revenue this month by category</span>
                    </button>
                    <button type="button" class="ai-suggestion-card" data-text="List slow-moving products">
                        <span class="icon">📉</span>
                        <span class="text">List slow-moving products</span>
                    </button>
                    <button type="button" class="ai-suggestion-card" data-text="Top spending loyalty customers">
                        <span class="icon">👥</span>
                        <span class="text">Top spending loyalty customers</span>
                    </button>
                    <button type="button" class="ai-suggestion-card" data-text="Pending purchase orders">
                        <span class="icon">🛒</span>
                        <span class="text">Pending purchase orders</span>
                    </button>
                </div>
            </div>

            <!-- Messages Stream List -->
            <div id="ai-messages-list" class="d-flex flex-column gap-4 max-w-4xl mx-auto w-100"></div>

            <!-- Jump to Latest Floating Button -->
            <button type="button" class="btn btn-primary rounded-circle shadow-lg ai-jump-bottom-btn d-none" id="ai-jump-bottom-btn" title="{{ __('Scroll to latest') }}">
                <i class="bi bi-arrow-down fs-5"></i>
            </button>
        </div>

        <!-- Sticky Bottom Input Deck -->
        <div class="ai-copilot-input-deck">
            <div class="max-w-4xl mx-auto w-100">
                <form id="ai-chat-form" autocomplete="off">
                    <div class="ai-input-pill">
                        <button type="button" class="ai-input-tool-btn" title="{{ __('Attach document (Coming soon)') }}" disabled>
                            <i class="bi bi-paperclip"></i>
                        </button>

                        <textarea id="ai-chat-textarea" rows="1" placeholder="{{ __('Ask Foodcity AI anything...') }}" maxlength="1000"></textarea>

                        <button type="button" class="ai-input-tool-btn" title="{{ __('Voice dictation (Coming soon)') }}" disabled>
                            <i class="bi bi-mic"></i>
                        </button>

                        <button type="submit" class="ai-submit-btn" id="ai-send-btn" disabled title="{{ __('Send prompt') }}">
                            <i class="bi bi-arrow-up-short"></i>
                        </button>
                    </div>
                    <div class="text-center text-muted ai-footer-disclaimer mt-2">
                        {{ __('Foodcity AI Copilot can analyze real-time inventory and sales database metrics. Press Enter to send · Shift+Enter for new line.') }}
                    </div>
                </form>
            </div>
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
