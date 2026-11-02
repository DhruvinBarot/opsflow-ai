<?php

namespace App\Enums;

enum Role: string
{
    case Employee = 'employee';
    case Manager = 'manager';
    case Admin = 'admin';

    /**
     * Roles allowed to review, assign and progress requests they do not own.
     */
    public function isOperations(): bool
    {
        return $this === self::Manager || $this === self::Admin;
    }
}
