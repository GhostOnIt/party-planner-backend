<?php

namespace App\Http\Requests\Invitation;

use App\Enums\RsvpStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RsvpRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public route
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'response' => ['required', Rule::in(['accepted', 'declined', 'maybe'])],
            'message' => ['nullable', 'string', 'max:500'],
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
            'response.required' => 'Votre réponse est requise.',
            'response.in' => 'La réponse doit être "accepted", "declined" ou "maybe".',
            'message.max' => 'Le message ne peut pas dépasser 500 caractères.',
        ];
    }
}
