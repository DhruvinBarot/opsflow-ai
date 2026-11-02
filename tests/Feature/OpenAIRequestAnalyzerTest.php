<?php

namespace Tests\Feature;

use App\Models\OperationalRequest;
use App\Services\AI\AIProviderException;
use App\Services\AI\AIRequestAnalyzerInterface;
use App\Services\AI\InvalidAnalysisOutputException;
use App\Services\AI\LocalRequestAnalyzer;
use App\Services\AI\OpenAIRequestAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeRequestAnalyzer;
use Tests\TestCase;

class OpenAIRequestAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function analyzer(): OpenAIRequestAnalyzer
    {
        return new OpenAIRequestAnalyzer('sk-test-key', 'gpt-test', 'https://api.openai.test/v1', 5);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function fakeCompletion(array $message, int $status = 200): void
    {
        Http::fake(['api.openai.test/*' => Http::response(['choices' => [['message' => $message]]], $status)]);
    }

    public function test_parses_a_structured_response_and_sends_a_strict_schema(): void
    {
        $this->fakeCompletion(['content' => json_encode(FakeRequestAnalyzer::validOutput())]);
        $request = OperationalRequest::factory()->create(['description' => 'p95 latency is 2.4s.']);

        $result = $this->analyzer()->analyze($request);

        $this->assertSame('platform', $result->suggestedTeam->value);
        Http::assertSent(function (Request $sent) {
            return $sent->url() === 'https://api.openai.test/v1/chat/completions'
                && $sent->hasHeader('Authorization', 'Bearer sk-test-key')
                && $sent['model'] === 'gpt-test'
                && $sent['response_format']['json_schema']['strict'] === true
                && $sent['response_format']['json_schema']['schema']['additionalProperties'] === false
                && str_contains($sent['messages'][1]['content'], 'p95 latency is 2.4s.');
        });
    }

    public function test_rejects_output_that_escapes_the_allowed_values(): void
    {
        // e.g. a prompt-injected description persuading the model to invent an action.
        $this->fakeCompletion(['content' => json_encode(['category' => 'auto_approve'] + FakeRequestAnalyzer::validOutput())]);

        $this->expectException(InvalidAnalysisOutputException::class);
        $this->analyzer()->analyze(OperationalRequest::factory()->create());
    }

    public function test_non_json_content_and_refusals_are_invalid_output(): void
    {
        $request = OperationalRequest::factory()->create();
        Http::fake(['api.openai.test/*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => 'Sure! Here is my analysis...']]]])
            ->push(['choices' => [['message' => ['content' => null, 'refusal' => 'I cannot help with that.']]]]),
        ]);

        $this->assertThrows(fn () => $this->analyzer()->analyze($request), InvalidAnalysisOutputException::class, 'not a JSON object');
        $this->assertThrows(fn () => $this->analyzer()->analyze($request), InvalidAnalysisOutputException::class, 'no usable message');
    }

    public function test_server_errors_and_rate_limits_are_transient(): void
    {
        $request = OperationalRequest::factory()->create();
        $cases = [500 => 'provider_unavailable', 503 => 'provider_unavailable', 429 => 'provider_rate_limited'];
        $sequence = Http::sequence();
        foreach (array_keys($cases) as $status) {
            $sequence->push([], $status);
        }
        Http::fake(['api.openai.test/*' => $sequence]);

        foreach ($cases as $status => $code) {
            try {
                $this->analyzer()->analyze($request);
                $this->fail("Expected an exception for HTTP {$status}.");
            } catch (AIProviderException $e) {
                $this->assertTrue($e->transient);
                $this->assertSame($code, $e->errorCode);
            }
        }
    }

    public function test_auth_errors_are_not_retried(): void
    {
        $this->fakeCompletion([], 401);

        try {
            $this->analyzer()->analyze(OperationalRequest::factory()->create());
            $this->fail('Expected an exception.');
        } catch (AIProviderException $e) {
            $this->assertFalse($e->transient);
            $this->assertSame('provider_auth_failed', $e->errorCode);
            $this->assertStringNotContainsString('sk-test-key', $e->getMessage());
        }
    }

    public function test_connection_timeouts_become_a_transient_provider_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        try {
            $this->analyzer()->analyze(OperationalRequest::factory()->create());
            $this->fail('Expected an exception.');
        } catch (AIProviderException $e) {
            $this->assertSame('provider_timeout', $e->errorCode);
            $this->assertTrue($e->transient);
        }
    }

    public function test_container_uses_openai_only_when_a_key_is_configured(): void
    {
        config(['services.openai.key' => null]);
        $this->assertInstanceOf(LocalRequestAnalyzer::class, app(AIRequestAnalyzerInterface::class));

        config(['services.openai.key' => 'sk-configured']);
        $this->assertInstanceOf(OpenAIRequestAnalyzer::class, app(AIRequestAnalyzerInterface::class));
    }
}
