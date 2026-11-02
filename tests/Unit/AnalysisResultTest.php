<?php

namespace Tests\Unit;

use App\Enums\RiskLevel;
use App\Services\AI\AnalysisResult;
use App\Services\AI\InvalidAnalysisOutputException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeRequestAnalyzer;
use Tests\TestCase;

class AnalysisResultTest extends TestCase
{
    public function test_accepts_valid_output(): void
    {
        $result = AnalysisResult::fromArray(FakeRequestAnalyzer::validOutput());

        $this->assertSame(RiskLevel::High, $result->riskLevel);
        $this->assertSame(FakeRequestAnalyzer::validOutput(), $result->toArray());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidOutputs(): array
    {
        $valid = FakeRequestAnalyzer::validOutput();

        return [
            'invented category' => [['category' => 'approve_immediately'] + $valid, 'category'],
            'invented team' => [['suggested_team' => 'ceo_office'] + $valid, 'suggested_team'],
            'priority outside enum' => [['priority' => 'urgent'] + $valid, 'priority'],
            'non-string risk' => [['risk_level' => 3] + $valid, 'risk_level'],
            'missing summary' => [array_diff_key($valid, ['summary' => true]), 'summary'],
            'overlong action' => [['suggested_action' => str_repeat('x', 501)] + $valid, 'suggested_action'],
            'markup-only summary' => [['summary' => '<script></script>'] + $valid, 'summary'],
        ];
    }

    /**
     * @param  array<string, mixed>  $output
     */
    #[DataProvider('invalidOutputs')]
    public function test_rejects_invalid_model_output(array $output, string $field): void
    {
        try {
            AnalysisResult::fromArray($output);
            $this->fail('Expected invalid output to be rejected.');
        } catch (InvalidAnalysisOutputException $e) {
            $this->assertStringContainsString($field, $e->getMessage());
            $this->assertSame('invalid_output', $e->errorCode);
        }
    }

    public function test_error_message_names_fields_but_never_echoes_values(): void
    {
        $this->expectException(InvalidAnalysisOutputException::class);
        $this->expectExceptionMessageMatches('/^((?!SECRET-CUSTOMER-DATA).)*$/');

        AnalysisResult::fromArray(['category' => 'SECRET-CUSTOMER-DATA'] + FakeRequestAnalyzer::validOutput());
    }

    public function test_strips_markup_and_control_characters(): void
    {
        $result = AnalysisResult::fromArray(['summary' => "<b>Latency</b> is up\x07 on checkout."] + FakeRequestAnalyzer::validOutput());

        $this->assertSame('Latency is up  on checkout.', $result->summary);
    }
}
