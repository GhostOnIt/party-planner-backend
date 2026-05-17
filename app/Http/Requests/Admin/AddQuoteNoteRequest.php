<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AddQuoteNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.required' => 'La note est obligatoire.',
            'note.min' => 'La note doit contenir au moins 3 caractères.',
            'note.max' => 'La note ne peut dépasser 2000 caractères.',
        ];
    }
}
