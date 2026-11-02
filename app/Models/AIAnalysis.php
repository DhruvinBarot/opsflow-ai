<?php

namespace App\Models;

use App\Enums\AnalysisStatus;
use App\Enums\RequestCategory;
use App\Enums\RequestPriority;
use App\Enums\RiskLevel;
use App\Enums\Team;
use App\Observers\AIAnalysisObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Advisory output only. Nothing here is ever copied onto the request automatically.
 */
#[Table('ai_analyses')]
#[ObservedBy(AIAnalysisObserver::class)]
#[Fillable(['status', 'provider', 'category', 'priority', 'risk_level', 'suggested_team', 'summary', 'suggested_action', 'attempts', 'error_code', 'analyzed_at'])]
class AIAnalysis extends Model
{
    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'attempts' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AnalysisStatus::class,
            'category' => RequestCategory::class,
            'priority' => RequestPriority::class,
            'risk_level' => RiskLevel::class,
            'suggested_team' => Team::class,
            'attempts' => 'integer',
            'analyzed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OperationalRequest, $this>
     */
    public function operationalRequest(): BelongsTo
    {
        return $this->belongsTo(OperationalRequest::class);
    }
}
