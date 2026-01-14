<?php

namespace App\Http\Requests\CustomRole;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled in the controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'in:purple,blue,green,yellow,red,gray'],
            'permissions' => ['sometimes', 'array', 'min:1'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.max' => 'Le nom du rôle ne peut pas dépasser 100 caractères.',
            'description.max' => 'La description ne peut pas dépasser 500 caractères.',
            'color.in' => 'La couleur sélectionnée n\'est pas valide.',
            'permissions.min' => 'Au moins une permission doit être sélectionnée.',
            'permissions.*.exists' => 'Une permission sélectionnée n\'existe pas.',
        ];
    }
}
