<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPaymentsRequest extends FormRequest
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
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'user_id' => ['nullable', 'exists:users,id'],
            'subscription_id' => ['nullable', 'exists:subscriptions,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0', 'gte:min_amount'],
            'sort_by' => ['nullable', 'string', Rule::in(['amount', 'status', 'payment_method', 'created_at'])],
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
            'status.enum' => 'Le statut selectionne est invalide.',
            'method.enum' => 'La methode de paiement selectionnee est invalide.',
            'user_id.exists' => 'L\'utilisateur selectionne n\'existe pas.',
            'subscription_id.exists' => 'L\'abonnement selectionne n\'existe pas.',
            'to.after_or_equal' => 'La date de fin doit etre apres ou egale a la date de debut.',
            'max_amount.gte' => 'Le montant maximum doit etre superieur ou egal au montant minimum.',
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
