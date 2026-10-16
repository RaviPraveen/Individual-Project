<?php

namespace Tests\Feature;

use App\Services\AiService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CloudflareImageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.cloudflare.account_id' => str_repeat('a', 32),
            'services.cloudflare.token' => 'test-token',
            'services.cloudflare.image_model' => '@cf/black-forest-labs/flux-1-schnell',
            'services.huggingface.key' => '',
        ]);
        Http::preventStrayRequests();
    }

    public function test_image_generation_works_without_a_hugging_face_key(): void
    {
        $image = imagecreatetruecolor(20, 20);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        Http::fake(['api.cloudflare.com/*' => Http::response([
            'success' => true, 'result' => ['image' => base64_encode($bytes)],
        ])]);

        $this->assertSame($bytes, app(AiService::class)->generateImage('Blue background'));
        Http::assertSent(fn ($request) => $request->url() ===
            'https://api.cloudflare.com/client/v4/accounts/'.str_repeat('a', 32).'/ai/run/@cf/black-forest-labs/flux-1-schnell'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['prompt'] === 'Blue background' && $request['steps'] === 4);
        Http::assertSentCount(1);
    }

    public function test_missing_cloudflare_settings_skip_the_request(): void
    {
        foreach (['account_id', 'token'] as $name) {
            $value = config('services.cloudflare.'.$name);
            config(['services.cloudflare.'.$name => '']);
            $this->assertNull(app(AiService::class)->generateImage('Blue background'));
            config(['services.cloudflare.'.$name => $value]);
        }
        Http::assertNothingSent();
    }

    #[DataProvider('failedResponses')]
    public function test_failed_or_invalid_images_use_the_fallback(int $status, array $body): void
    {
        Http::fake(['api.cloudflare.com/*' => Http::response($body, $status)]);
        $this->assertNull(app(AiService::class)->generateImage('Blue background'));
    }

    public static function failedResponses(): array
    {
        return [
            'invalid token' => [403, ['success' => false]],
            'daily limit' => [429, ['success' => false]],
            'provider failure' => [200, ['success' => false]],
            'missing image' => [200, ['success' => true, 'result' => []]],
            'bad base64' => [200, ['success' => true, 'result' => ['image' => '%%%']]],
            'not an image' => [200, ['success' => true, 'result' => ['image' => base64_encode('invalid image')]]],
        ];
    }

    public function test_network_failure_uses_the_fallback(): void
    {
        Http::fake(['api.cloudflare.com/*' => Http::failedConnection()]);
        $this->assertNull(app(AiService::class)->generateImage('Blue background'));
    }
}
