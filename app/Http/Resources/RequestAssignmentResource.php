<?php

namespace App\Http\Resources;

use App\Models\RequestAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RequestAssignment
 */
class RequestAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assignee' => UserResource::make($this->whenLoaded('assignee')),
            'assigned_by' => UserResource::make($this->whenLoaded('assigner')),
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
