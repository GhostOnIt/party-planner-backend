<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class InitiatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subscription_id' => [
                'required',
                'exists:subscriptions,id',
            ],
            'phone' => [
                'required',
                'string',
                'regex:/^[0-9]{9,12}$/',
            ],
            'payment_method' => [
                'nullable',
                'in:mtn_mobile_money,airtel_money',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'subscription_id' => 'abonnement',
            'phone' => 'numéro de téléphone',
            'payment_method' => 'méthode de paiement',
        ];
    }

    public function messages(): array
    {
        return [
            'subscription_id.required' => 'L\'abonnement est requis.',
            'subscription_id.exists' => 'L\'abonnement n\'existe pas.',
            'phone.required' => 'Le numéro de téléphone est requis.',
            'phone.regex' => 'Le numéro de téléphone doit contenir entre 9 et 12 chiffres.',
            'payment_method.in' => 'La méthode de paiement n\'est pas valide.',
        ];
    }
}
