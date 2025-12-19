<?php

namespace App\Http\Requests\Admin;

use App\Enums\EventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTemplateRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['sometimes', 'required', Rule::enum(EventType::class)],
            'theme' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'preview_image' => ['nullable', 'image', 'max:2048'],

            // Tasks template
            'tasks' => ['nullable', 'array'],
            'tasks.*.title' => ['required_with:tasks', 'string', 'max:255'],
            'tasks.*.description' => ['nullable', 'string', 'max:1000'],
            'tasks.*.priority' => ['nullable', 'string', Rule::in(['low', 'medium', 'high'])],
            'tasks.*.days_before_event' => ['nullable', 'integer', 'min:0', 'max:365'],

            // Budget items template
            'budget_items' => ['nullable', 'array'],
            'budget_items.*.category' => ['required_with:budget_items', 'string', 'max:100'],
            'budget_items.*.name' => ['required_with:budget_items', 'string', 'max:255'],
            'budget_items.*.estimated_cost' => ['nullable', 'numeric', 'min:0'],

            // Suggested guests categories
            'guest_categories' => ['nullable', 'array'],
            'guest_categories.*' => ['string', 'max:100'],

            // Color scheme
            'colors' => ['nullable', 'array'],
            'colors.primary' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'colors.secondary' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'colors.accent' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],

            // Metadata
            'metadata' => ['nullable', 'array'],
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
            'name.required' => 'Le nom du template est requis.',
            'name.max' => 'Le nom ne peut pas depasser 255 caracteres.',
            'type.required' => 'Le type d\'evenement est requis.',
            'type.enum' => 'Le type d\'evenement selectionne est invalide.',
            'preview_image.image' => 'Le fichier doit etre une image.',
            'preview_image.max' => 'L\'image ne peut pas depasser 2 Mo.',
            'tasks.*.title.required_with' => 'Le titre de la tache est requis.',
            'budget_items.*.category.required_with' => 'La categorie du poste budgetaire est requise.',
            'budget_items.*.name.required_with' => 'Le nom du poste budgetaire est requis.',
            'colors.primary.regex' => 'La couleur primaire doit etre au format hexadecimal (#RRGGBB).',
            'colors.secondary.regex' => 'La couleur secondaire doit etre au format hexadecimal (#RRGGBB).',
            'colors.accent.regex' => 'La couleur d\'accentuation doit etre au format hexadecimal (#RRGGBB).',
        ];
    }
}
