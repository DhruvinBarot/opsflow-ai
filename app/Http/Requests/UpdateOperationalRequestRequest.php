<?php

namespace App\Http\Requests;

use App\Enums\RequestCategory;
use App\Enums\RequestPriority;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateOperationalRequestRequest extends FormRequest
{
    /**
     * Returning the policy Response (not a bool) keeps its denial message in the 403.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('operationalRequest'));
    }

    /**
     * Status, ownership and assignment are deliberately absent: they change only through workflow endpoints.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'min:5', 'max:160'],
            'description' => ['sometimes', 'string', 'min:10', 'max:5000'],
            'category' => ['sometimes', Rule::enum(RequestCategory::class)],
            'priority' => ['sometimes', Rule::enum(RequestPriority::class)],
            'due_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ];
    }
}
