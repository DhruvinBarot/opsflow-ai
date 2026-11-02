<?php

namespace App\Http\Resources;

use App\Enums\AnalysisStatus;
use App\Models\AIAnalysis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Exposes the stored, validated analysis only. Raw provider payloads are never persisted, so they cannot leak here.
 *
 * @mixin AIAnalysis
 */
class AIAnalysisResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $completed = $this->status === AnalysisStatus::Completed;
        $failed = $this->status === AnalysisStatus::Failed;

        return [
            'status' => $this->status->value,
            'advisory' => true,
            'provider' => $this->when($completed, $this->provider),
            'fallback_reason' => $this->when($completed && $this->error_code !== null, $this->error_code),
            'recommendation' => $completed ? [
                'category' => $this->category?->value,
                'priority' => $this->priority?->value,
                'risk_level' => $this->risk_level?->value,
                'suggested_team' => $this->suggested_team?->value,
                'summary' => $this->summary,
                'suggested_action' => $this->suggested_action,
            ] : null,
            'error' => $this->when($failed, fn () => [
                'code' => $this->error_code,
                'message' => 'AI analysis is unavailable for this request. Reviewers can proceed without it.',
            ]),
            'attempts' => $this->attempts,
            'analyzed_at' => $this->analyzed_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
