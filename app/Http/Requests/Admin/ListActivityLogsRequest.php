<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListActivityLogsRequest extends FormRequest
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
            'admin_id' => ['nullable', 'exists:users,id'],
            'action' => ['nullable', 'string', Rule::in([
                'create', 'update', 'delete',
                'create_user', 'update_user', 'delete_user', 'update_role',
                'create_event', 'update_event', 'delete_event',
                'create_template', 'update_template', 'delete_template',
                'refund_payment', 'cancel_subscription',
            ])],
            'model_type' => ['nullable', 'string', Rule::in([
                'User', 'Event', 'Payment', 'Subscription', 'EventTemplate',
            ])],
            'model_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sort_by' => ['nullable', 'string', Rule::in(['created_at', 'action', 'model_type'])],
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
            'admin_id.exists' => 'L\'administrateur selectionne n\'existe pas.',
            'action.in' => 'L\'action selectionnee est invalide.',
            'model_type.in' => 'Le type de modele selectionne est invalide.',
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
