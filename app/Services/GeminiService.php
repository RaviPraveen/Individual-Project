<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiService
{
    public function isConfigured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /**
     * Send a prompt to Gemini and return the generated text, or null if the
     * API is not configured, unreachable, or returns an error. Callers must
     * treat null as "AI unavailable" and fall back to non-AI content —
     * this method never throws.
     */
    public function generate(string $prompt): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $model = config('services.gemini.model');
            $key = config('services.gemini.key');

            $response = Http::timeout(15)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}", [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Gemini API request failed', ['status' => $response->status()]);

                return null;
            }

            $text = $response->json('candidates.0.content.parts.0.text');

            return is_string($text) ? trim($text) : null;
        } catch (Throwable $e) {
            Log::warning('Gemini API request threw an exception', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
