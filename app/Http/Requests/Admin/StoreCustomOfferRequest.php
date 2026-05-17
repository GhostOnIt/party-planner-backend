<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'price_amount' => ['required', 'integer', 'min:0'],
            'price_currency' => ['nullable', 'string', 'max:10'],
            'features' => ['nullable', 'array', 'max:50'],
            'features.*' => ['string', 'max:500'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Le titre de l\'offre est obligatoire.',
            'title.max' => 'Le titre ne peut dépasser 255 caractères.',
            'price_amount.required' => 'Le montant est obligatoire.',
            'price_amount.min' => 'Le montant doit être positif.',
            'features.max' => 'Vous ne pouvez pas dépasser 50 fonctionnalités.',
            'terms.max' => 'Les conditions ne peuvent dépasser 5000 caractères.',
            'validity_days.min' => 'La durée de validité doit être au moins 1 jour.',
            'validity_days.max' => 'La durée de validité ne peut dépasser 365 jours.',
        ];
    }
}
