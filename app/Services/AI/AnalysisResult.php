<?php

namespace App\Services\AI;

use App\Enums\RequestCategory;
use App\Enums\RequestPriority;
use App\Enums\RiskLevel;
use App\Enums\Team;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * A validated analysis. The only way to build one from untrusted (model) output is fromArray().
 */
final readonly class AnalysisResult
{
    public function __construct(
        public RequestCategory $category,
        public RequestPriority $priority,
        public RiskLevel $riskLevel,
        public Team $suggestedTeam,
        public string $summary,
        public string $suggestedAction,
    ) {}

    /**
     * @param  array<mixed>  $data
     *
     * @throws InvalidAnalysisOutputException
     */
    public static function fromArray(array $data): self
    {
        $data = array_map(fn ($value) => is_string($value) ? self::clean($value) : $value, $data);

        $validator = Validator::make($data, [
            'category' => ['required', 'string', Rule::enum(RequestCategory::class)],
            'priority' => ['required', 'string', Rule::enum(RequestPriority::class)],
            'risk_level' => ['required', 'string', Rule::enum(RiskLevel::class)],
            'suggested_team' => ['required', 'string', Rule::enum(Team::class)],
            'summary' => ['required', 'string', 'min:5', 'max:500'],
            'suggested_action' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if ($validator->fails()) {
            // Field names only: the rejected values are model output and may echo request content.
            throw InvalidAnalysisOutputException::forFields($validator->errors()->keys());
        }

        return new self(
            RequestCategory::from($data['category']),
            RequestPriority::from($data['priority']),
            RiskLevel::from($data['risk_level']),
            Team::from($data['suggested_team']),
            $data['summary'],
            $data['suggested_action'],
        );
    }

    /**
     * @return array{category: string, priority: string, risk_level: string, suggested_team: string, summary: string, suggested_action: string}
     */
    public function toArray(): array
    {
        return [
            'category' => $this->category->value,
            'priority' => $this->priority->value,
            'risk_level' => $this->riskLevel->value,
            'suggested_team' => $this->suggestedTeam->value,
            'summary' => $this->summary,
            'suggested_action' => $this->suggestedAction,
        ];
    }

    /**
     * Strip control characters and markup so model text is stored as plain text.
     */
    private static function clean(string $value): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', strip_tags($value)));
    }
}
