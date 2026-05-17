<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleQuoteCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'call_scheduled_at' => ['required', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'call_scheduled_at.required' => 'La date du call est obligatoire.',
            'call_scheduled_at.date' => 'La date du call est invalide.',
            'call_scheduled_at.after' => 'La date du call doit être dans le futur.',
        ];
    }
}
