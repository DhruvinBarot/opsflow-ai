<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action->value,
            'actor' => $this->actor_id ? UserResource::make($this->whenLoaded('actor')) : ['type' => 'system'],
            'request' => $this->whenLoaded('operationalRequest', fn () => $this->operationalRequest ? [
                'id' => $this->operationalRequest->id,
                'title' => $this->operationalRequest->title,
            ] : null),
            'metadata' => $this->metadata ?? (object) [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
