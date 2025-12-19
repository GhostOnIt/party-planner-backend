<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUsersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'sort_by' => ['nullable', 'string', Rule::in(['name', 'email', 'created_at', 'role'])],
            'sort_dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.enum' => 'Le role selectionne est invalide.',
            'sort_by.in' => 'Le champ de tri est invalide.',
            'sort_dir.in' => 'La direction de tri doit etre asc ou desc.',
            'per_page.min' => 'Le nombre par page doit etre au moins 1.',
            'per_page.max' => 'Le nombre par page ne peut depasser 100.',
        ];
    }

    /**
     * Get default values for optional parameters.
     */
    public function defaults(): array
    {
        return [
            'sort_by' => 'created_at',
            'sort_dir' => 'desc',
            'per_page' => 15,
        ];
    }
}
