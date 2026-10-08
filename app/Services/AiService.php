<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Service to handle Hugging Face API requests for text generation and poster images.
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

    /**
     * Generate poster background image via text-to-image API.
     * Returns raw image bytes, or null if service is unreachable.
     */
    public function generateImage(string $prompt): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $model = config('services.huggingface.image_model');
            $token = config('services.huggingface.key');

            $response = Http::withToken($token)
                ->timeout(60)
                ->post("https://router.huggingface.co/hf-inference/models/{$model}", [
                    'inputs' => $prompt,
                ]);

            if (! $response->successful() || ! str_starts_with($response->header('Content-Type', ''), 'image/')) {
                Log::warning('Hugging Face image request failed', ['status' => $response->status()]);

                return null;
            }

            return $response->body();
        } catch (Throwable $e) {
            Log::warning('Hugging Face image request threw an exception', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
