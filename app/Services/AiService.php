<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

// Hugging Face text and image requests.
class AiService
{
    public function isConfigured(): bool
    {
        return filled(config('services.huggingface.key'));
    }

    // Return null when the service is unavailable so the caller can use a fallback.
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

    // Return image bytes, or null if generation fails.
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
