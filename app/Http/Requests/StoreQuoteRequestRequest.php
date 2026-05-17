<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['nullable', 'exists:plans,id'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:30'],
            'company_name' => ['required', 'string', 'max:255'],
            'business_needs' => ['required', 'string', 'min:20', 'max:3000'],
            'budget_estimate' => ['nullable', 'integer', 'min:0'],
            'team_size' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'timeline' => ['nullable', 'string', 'max:255'],
            'event_types' => ['nullable', 'array', 'max:20'],
            'event_types.*' => ['string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_name.required' => 'Le nom du contact est obligatoire.',
            'contact_email.required' => 'L\'email du contact est obligatoire.',
            'contact_email.email' => 'L\'email du contact est invalide.',
            'contact_phone.required' => 'Le téléphone du contact est obligatoire.',
            'company_name.required' => 'Le nom de la société est obligatoire.',
            'business_needs.required' => 'La description des besoins est obligatoire.',
            'business_needs.min' => 'La description des besoins doit contenir au moins 20 caractères.',
            'business_needs.max' => 'La description des besoins ne peut dépasser 3000 caractères.',
            'team_size.min' => 'La taille de l\'équipe doit être au moins 1.',
            'event_types.max' => 'Vous ne pouvez pas dépasser 20 types d\'événements.',
        ];
    }
}
