<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'avatar' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,webp',
                'max:2048', // 2MB
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Le numéro de téléphone n\'est pas valide.',
            'avatar.image' => 'Le fichier doit être une image.',
            'avatar.mimes' => 'L\'avatar doit être au format JPEG, PNG, JPG ou WebP.',
            'avatar.max' => 'L\'avatar ne doit pas dépasser 2 Mo.',
        ];
    }
}
