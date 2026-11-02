<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by workflow actions that accept an optional or mandatory reason.
 * Authorization is checked in the controller, because each action maps to a different policy ability.
 */
class TransitionOperationalRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => [$this->routeIs('requests.reject') ? 'required' : 'nullable', 'string', 'min:3', 'max:1000'],
        ];
    }
}
