<?php

namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $this->user()->can('update', $event);
    }

    public function rules(): array
    {
        return [
            'plan_type' => [
                'required',
                Rule::in(['starter', 'pro']),
            ],
            'guest_count' => [
                'nullable',
                'integer',
                'min:0',
                'max:10000',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'plan_type' => 'type de plan',
            'guest_count' => 'nombre d\'invités',
        ];
    }

    public function messages(): array
    {
        return [
            'plan_type.required' => 'Veuillez sélectionner un plan.',
            'plan_type.in' => 'Le plan sélectionné n\'est pas valide.',
            'guest_count.integer' => 'Le nombre d\'invités doit être un nombre entier.',
            'guest_count.min' => 'Le nombre d\'invités ne peut pas être négatif.',
            'guest_count.max' => 'Le nombre d\'invités est trop élevé.',
        ];
    }
}
