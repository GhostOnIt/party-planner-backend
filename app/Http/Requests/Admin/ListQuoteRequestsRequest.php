<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListQuoteRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'stage_id' => ['nullable', 'exists:quote_request_stages,id'],
            'status' => ['nullable', Rule::in(['open', 'closed'])],
            'outcome' => ['nullable', Rule::in(['won', 'lost'])],
            'assigned_admin_id' => ['nullable', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'budget_min' => ['nullable', 'integer', 'min:0'],
            'budget_max' => ['nullable', 'integer', 'min:0'],
            'sort_by' => ['nullable', 'string', Rule::in(['created_at', 'company_name', 'budget_estimate', 'last_stage_changed_at'])],
            'sort_dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'stage_id.exists' => 'L\'étape sélectionnée n\'existe pas.',
            'status.in' => 'Le statut doit être "open" ou "closed".',
            'outcome.in' => 'L\'issue doit être "won" ou "lost".',
            'assigned_admin_id.exists' => 'L\'administrateur sélectionné n\'existe pas.',
            'date_to.after_or_equal' => 'La date de fin doit être après ou égale à la date de début.',
            'sort_by.in' => 'Le champ de tri est invalide.',
            'sort_dir.in' => 'La direction de tri doit être asc ou desc.',
            'per_page.max' => 'Le nombre par page ne peut dépasser 100.',
        ];
    }

    public function defaults(): array
    {
        return [
            'sort_by' => 'created_at',
            'sort_dir' => 'desc',
            'per_page' => 20,
        ];
    }
}
