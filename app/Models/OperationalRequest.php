<?php

namespace App\Models;

use App\Enums\RequestCategory;
use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Observers\OperationalRequestObserver;
use Database\Factories\OperationalRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Only user-editable content is fillable. Ownership, status, assignment and
 * workflow timestamps are written by RequestWorkflowService / RequestAssignmentService.
 */
#[Fillable(['title', 'description', 'category', 'priority', 'due_at'])]
#[ObservedBy(OperationalRequestObserver::class)]
class OperationalRequest extends Model
{
    /** @use HasFactory<OperationalRequestFactory> */
    use HasFactory;

    /**
     * Stable entity name stored in audit_logs.entity_type (decoupled from the PHP class name).
     */
    public const AUDIT_ENTITY = 'operational_request';

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'priority' => 'medium',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => RequestCategory::class,
            'priority' => RequestPriority::class,
            'status' => RequestStatus::class,
            'due_at' => 'datetime',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<RequestStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(RequestStatusHistory::class)->oldest('created_at')->oldest('id');
    }

    /**
     * @return HasMany<RequestAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(RequestAssignment::class)->latest('created_at')->latest('id');
    }

    /**
     * @return HasOne<AIAnalysis, $this>
     */
    public function aiAnalysis(): HasOne
    {
        return $this->hasOne(AIAnalysis::class);
    }

    /**
     * @return HasMany<RequestComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(RequestComment::class)->oldest('created_at')->oldest('id');
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'entity_id')
            ->where('entity_type', self::AUDIT_ENTITY)
            ->latest('created_at')
            ->latest('id');
    }

    /**
     * Restrict a query to requests the given user may see.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->isOperations()) {
            return;
        }

        $query->where(function (Builder $query) use ($user) {
            $query->where('created_by', $user->id)->orWhere('assigned_to', $user->id);
        });
    }
}
