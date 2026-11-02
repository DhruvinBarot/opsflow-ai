<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\OperationalRequest;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Writes append-only audit records. Call it inside the caller's transaction so
 * the audit row commits (or rolls back) together with the change it describes.
 */
class AuditService
{
    /**
     * Metadata keys that must never be persisted, matched case-insensitively as substrings.
     */
    private const REDACTED_KEYS = ['password', 'token', 'secret', 'api_key', 'apikey', 'authorization', 'credential'];

    private const MAX_STRING_LENGTH = 500;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(AuditAction $action, OperationalRequest $request, ?User $actor, array $metadata = []): AuditLog
    {
        return AuditLog::create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'entity_type' => OperationalRequest::AUDIT_ENTITY,
            'entity_id' => $request->getKey(),
            'metadata' => $this->sanitize($metadata) ?: null,
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $metadata
     * @return array<array-key, mixed>
     */
    private function sanitize(array $metadata): array
    {
        $clean = [];

        foreach ($metadata as $key => $value) {
            if (is_string($key) && Str::contains(strtolower($key), self::REDACTED_KEYS)) {
                continue;
            }

            $clean[$key] = match (true) {
                is_array($value) => $this->sanitize($value),
                $value instanceof \BackedEnum => $value->value,
                is_string($value) => Str::limit($value, self::MAX_STRING_LENGTH),
                is_scalar($value), $value === null => $value,
                default => null,
            };
        }

        return $clean;
    }
}
