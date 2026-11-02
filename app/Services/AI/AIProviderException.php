<?php

namespace App\Services\AI;

use RuntimeException;

/**
 * The provider could not produce an answer. Transient failures are worth retrying; others are not.
 */
class AIProviderException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, public readonly bool $transient)
    {
        parent::__construct("AI provider error: {$errorCode}");
    }

    public static function timeout(): self
    {
        return new self('provider_timeout', true);
    }

    public static function fromStatus(int $status): self
    {
        return match (true) {
            $status === 429 => new self('provider_rate_limited', true),
            $status >= 500 => new self('provider_unavailable', true),
            in_array($status, [401, 403], true) => new self('provider_auth_failed', false),
            default => new self('provider_rejected', false),
        };
    }
}
