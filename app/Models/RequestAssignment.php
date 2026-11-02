<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assigned_to', 'assigned_by', 'note'])]
class RequestAssignment extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<OperationalRequest, $this>
     */
    public function operationalRequest(): BelongsTo
    {
        return $this->belongsTo(OperationalRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
