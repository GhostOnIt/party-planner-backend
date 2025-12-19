<?php

namespace App\Http\Requests\Admin;

use App\Enums\EventStatus;
use App\Enums\EventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListEventsRequest extends FormRequest
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
            'type' => ['nullable', Rule::enum(EventType::class)],
            'status' => ['nullable', Rule::enum(EventStatus::class)],
            'user_id' => ['nullable', 'exists:users,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sort_by' => ['nullable', 'string', Rule::in(['title', 'date', 'created_at', 'status', 'type'])],
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
            'type.enum' => 'Le type d\'evenement selectionne est invalide.',
            'status.enum' => 'Le statut selectionne est invalide.',
            'user_id.exists' => 'L\'utilisateur selectionne n\'existe pas.',
            'to.after_or_equal' => 'La date de fin doit etre apres ou egale a la date de debut.',
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
