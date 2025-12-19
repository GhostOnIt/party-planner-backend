<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentStatus;
use App\Enums\PlanType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSubscriptionsRequest extends FormRequest
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
            'plan' => ['nullable', Rule::enum(PlanType::class)],
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'user_id' => ['nullable', 'exists:users,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'expired' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sort_by' => ['nullable', 'string', Rule::in(['plan_type', 'total_price', 'payment_status', 'created_at', 'expires_at'])],
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
            'plan.enum' => 'Le plan selectionne est invalide.',
            'status.enum' => 'Le statut selectionne est invalide.',
            'user_id.exists' => 'L\'utilisateur selectionne n\'existe pas.',
            'event_id.exists' => 'L\'evenement selectionne n\'existe pas.',
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
