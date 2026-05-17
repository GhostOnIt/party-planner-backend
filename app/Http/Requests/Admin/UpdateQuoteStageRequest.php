<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuoteStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'stage_id' => ['required', 'exists:quote_request_stages,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'stage_id.required' => 'L\'étape est obligatoire.',
            'stage_id.exists' => 'L\'étape sélectionnée n\'existe pas.',
        ];
    }
}
