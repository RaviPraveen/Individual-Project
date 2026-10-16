<?php

namespace Tests\Feature;

use App\Services\AiService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CloudflareTextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.cloudflare.account_id' => str_repeat('a', 32),
            'services.cloudflare.token' => 'test-token',
            'services.cloudflare.text_model' => '@cf/meta/llama-3.1-8b-instruct-fp8-fast',
        ]);
        Http::preventStrayRequests();
    }

    public function test_text_requests_use_cloudflare_and_return_the_answer(): void
    {
        Http::fake(['api.cloudflare.com/*' => Http::response([
            'choices' => [['message' => ['content' => ' Sales are steady. ']]],
        ])]);
        $this->assertSame('Sales are steady.', app(AiService::class)->generate('Sales summary'));
        Http::assertSent(fn ($request) => $request->url() ===
            'https://api.cloudflare.com/client/v4/accounts/'.str_repeat('a', 32).'/ai/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['model'] === '@cf/meta/llama-3.1-8b-instruct-fp8-fast'
            && $request['messages'][0]['role'] === 'system'
            && $request['messages'][1]['content'] === 'Sales summary'
            && $request['max_tokens'] === 1024);
        Http::assertSentCount(1);
    }

    public function test_both_account_and_token_are_required(): void
    {
        foreach (['account_id', 'token'] as $name) {
            $value = config('services.cloudflare.'.$name);
            config(['services.cloudflare.'.$name => '']);
            $this->assertFalse(app(AiService::class)->isConfigured());
            $this->assertNull(app(AiService::class)->generate('Sales summary'));
            config(['services.cloudflare.'.$name => $value]);
        }
        Http::assertNothingSent();
    }

    #[DataProvider('failedResponses')]
    public function test_bad_responses_return_null(int $status, array $body): void
    {
        Http::fake(['api.cloudflare.com/*' => Http::response($body, $status)]);
        $this->assertNull(app(AiService::class)->generate('Sales summary'));
    }

    public static function failedResponses(): array
    {
        return [
            'invalid token' => [403, []],
            'daily limit' => [429, []],
            'missing answer' => [200, ['choices' => []]],
            'empty answer' => [200, ['choices' => [['message' => ['content' => ' ']]]]],
            'wrong answer type' => [200, ['choices' => [['message' => ['content' => ['text' => 'Hi']]]]]],
        ];
    }

    public function test_network_failure_returns_null(): void
    {
        Http::fake(['api.cloudflare.com/*' => Http::failedConnection()]);
        $this->assertNull(app(AiService::class)->generate('Sales summary'));
    }
}
