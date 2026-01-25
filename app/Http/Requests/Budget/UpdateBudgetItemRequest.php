<?php

namespace App\Http\Requests\Budget;

use App\Enums\BudgetCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBudgetItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $this->user()->can('manageBudget', $event);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        
        // Get user's budget categories slugs
        $userCategorySlugs = $user->budgetCategories()->pluck('slug')->toArray();
        
        // Also allow default enum categories for backward compatibility
        $defaultCategorySlugs = array_column(BudgetCategory::cases(), 'value');
        
        // Combine both
        $allowedCategories = array_merge($userCategorySlugs, $defaultCategorySlugs);
        
        return [
            'category' => ['required', 'string', Rule::in($allowedCategories)],
            'name' => ['required', 'string', 'max:255'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'actual_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'paid' => ['boolean'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Prepare data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'paid' => $this->boolean('paid'),
        ]);
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category' => 'catégorie',
            'name' => 'nom',
            'estimated_cost' => 'coût estimé',
            'actual_cost' => 'coût réel',
            'paid' => 'payé',
            'payment_date' => 'date de paiement',
            'notes' => 'notes',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.required' => 'La catégorie est obligatoire.',
            'category.Illuminate\Validation\Rules\Enum' => 'La catégorie sélectionnée est invalide.',
            'name.required' => 'Le nom est obligatoire.',
            'name.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'estimated_cost.numeric' => 'Le coût estimé doit être un nombre.',
            'estimated_cost.min' => 'Le coût estimé doit être positif.',
            'actual_cost.numeric' => 'Le coût réel doit être un nombre.',
            'actual_cost.min' => 'Le coût réel doit être positif.',
            'payment_date.date' => 'La date de paiement doit être une date valide.',
            'notes.max' => 'Les notes ne peuvent pas dépasser 1000 caractères.',
        ];
    }
}
