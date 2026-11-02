<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['body'])]
class RequestComment extends Model
{
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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
