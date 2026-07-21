<x-admin-layout>
    @include('ai-chat._content', [
        'conversationGroups' => $conversationGroups,
        'activeConversation' => $activeConversation,
        'messages' => $messages,
        'geminiConfigured' => $geminiConfigured,
        'suggestions' => $suggestions,
    ])
</x-admin-layout>
