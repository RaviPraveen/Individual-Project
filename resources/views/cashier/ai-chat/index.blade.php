<x-cashier-layout>
    @include('ai-chat._content', [
        'conversationGroups' => $conversationGroups,
        'activeConversation' => $activeConversation,
        'messages' => $messages,
        'aiConfigured' => $aiConfigured,
        'suggestions' => $suggestions,
    ])
</x-cashier-layout>
