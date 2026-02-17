<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBatchLogsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'events' => ['required', 'array', 'min:1', 'max:100'],
            'events.*.type' => ['required', 'string', Rule::in([
                'navigation', 'ui_interaction', 'api_call',
            ])],
            'events.*.action' => ['required', 'string', 'max:100'],
            'events.*.page_url' => ['nullable', 'string', 'max:500'],
            'events.*.session_id' => ['nullable', 'string', 'max:100'],
            'events.*.timestamp' => ['nullable', 'date'],
            'events.*.description' => ['nullable', 'string', 'max:500'],
            'events.*.metadata' => ['nullable', 'array'],
            'events.*.metadata.element' => ['nullable', 'string', 'max:200'],
            'events.*.metadata.duration' => ['nullable', 'numeric', 'min:0'],
            'events.*.metadata.previous_page' => ['nullable', 'string', 'max:500'],
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
            'events.required' => 'Le tableau d\'événements est requis.',
            'events.array' => 'Les événements doivent être un tableau.',
            'events.min' => 'Au moins un événement est requis.',
            'events.max' => 'Maximum 100 événements par batch.',
            'events.*.type.required' => 'Le type d\'événement est requis.',
            'events.*.type.in' => 'Le type d\'événement est invalide.',
            'events.*.action.required' => 'L\'action est requise.',
            'events.*.action.max' => 'L\'action ne peut pas dépasser 100 caractères.',
        ];
    }
}
