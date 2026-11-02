<?php

namespace App\Services\AI;

use RuntimeException;

class InvalidAnalysisOutputException extends RuntimeException
{
    public string $errorCode = 'invalid_output';

    /**
     * @param  list<string>  $fields
     */
    public static function forFields(array $fields): self
    {
        return new self('AI output failed validation for: '.implode(', ', $fields));
    }

    public static function malformed(string $reason): self
    {
        return new self("AI output could not be parsed: {$reason}");
    }
}
