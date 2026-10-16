<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

// Cloudflare text and image requests.
class AiService
{
    public function isConfigured(): bool
    {
        return filled(config('services.cloudflare.account_id')) && filled(config('services.cloudflare.token'));
    }

    // Return null when the service is unavailable so the caller can use a fallback.
    public function generate(string $prompt): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $account = config('services.cloudflare.account_id');
            $model = config('services.cloudflare.text_model');
            $token = config('services.cloudflare.token');

            $response = Http::withToken($token)
                ->timeout(30)
                ->post("https://api.cloudflare.com/client/v4/accounts/{$account}/ai/v1/chat/completions", [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Follow the requested task and output format exactly. Use only supplied facts. Do not invent reasons, buying habits or trends. If the data is insufficient, say so.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 1024,
                    'temperature' => 0.2,
                ]);

            if (! $response->successful()) {
                Log::warning('Cloudflare text request failed', ['status' => $response->status()]);

                return null;
            }

            $text = $response->json('choices.0.message.content');

            return is_string($text) && trim($text) !== '' ? trim($text) : null;
        } catch (Throwable $e) {
            Log::warning('Cloudflare text request threw an exception', ['type' => get_class($e)]);

            return null;
        }
    }

    // Return image bytes, or null if generation fails.
    public function generateImage(string $prompt): ?string
    {
        $account = config('services.cloudflare.account_id');
        $token = config('services.cloudflare.token');

        if (blank($account) || blank($token)) {
            return null;
        }

        try {
            $model = config('services.cloudflare.image_model');

            $response = Http::withToken($token)
                ->timeout(60)
                ->post("https://api.cloudflare.com/client/v4/accounts/{$account}/ai/run/{$model}", [
                    'prompt' => $prompt,
                    'steps' => 4,
                ]);

            if (! $response->successful() || $response->json('success') !== true) {
                Log::warning('Cloudflare image request failed', ['status' => $response->status()]);

                return null;
            }

            $encoded = $response->json('result.image');
            $image = is_string($encoded) ? base64_decode($encoded, true) : false;

            if (! $image || @getimagesizefromstring($image) === false) {
                Log::warning('Cloudflare returned an invalid image');

                return null;
            }

            return $image;
        } catch (Throwable $e) {
            Log::warning('Cloudflare image request threw an exception', ['type' => get_class($e)]);

            return null;
        }
    }
}
