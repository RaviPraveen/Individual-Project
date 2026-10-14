<x-admin-layout>
    @include('ai-chat._content', [
        'conversationGroups' => $conversationGroups,
        'activeConversation' => $activeConversation,
        'messages' => $messages,
        'aiConfigured' => $aiConfigured,
        'suggestions' => $suggestions,
    ])
</x-admin-layout>
