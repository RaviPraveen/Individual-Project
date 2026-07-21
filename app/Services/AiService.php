<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin wrapper around Hugging Face's Inference Providers router (an
 * OpenAI-compatible chat-completions endpoint). Originally built against
 * Google Gemini; switched providers when the available Gemini API key's
 * Google Cloud project turned out to have zero free-tier quota. Every
 * caller only ever depended on isConfigured()/generate(), so this class was
 * the only thing that needed a new implementation — the prompts elsewhere
 * in the app are provider-agnostic plain text.
 */
class AiService
{
    public function isConfigured(): bool
    {
        return filled(config('services.huggingface.key'));
    }

    /**
     * Send a prompt to the configured model and return the generated text,
     * or null if the API is not configured, unreachable, or returns an
     * error. Callers must treat null as "AI unavailable" and fall back to
     * non-AI content — this method never throws.
     */
    public function generate(string $prompt): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $model = config('services.huggingface.model');
            $token = config('services.huggingface.key');

            $response = Http::withToken($token)
                ->timeout(20)
                ->post('https://router.huggingface.co/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Hugging Face API request failed', ['status' => $response->status()]);

                return null;
            }

            $text = $response->json('choices.0.message.content');

            return is_string($text) ? trim($text) : null;
        } catch (Throwable $e) {
            Log::warning('Hugging Face API request threw an exception', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
