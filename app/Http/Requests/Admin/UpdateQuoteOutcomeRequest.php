<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuoteOutcomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'outcome' => ['required', Rule::in(['won', 'lost'])],
            'outcome_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'outcome.required' => 'L\'issue est obligatoire.',
            'outcome.in' => 'L\'issue doit être "won" ou "lost".',
            'outcome_note.max' => 'La note d\'issue ne peut dépasser 2000 caractères.',
        ];
    }
}
