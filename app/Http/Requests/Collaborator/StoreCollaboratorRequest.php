<?php

namespace App\Http\Requests\Collaborator;

use App\Enums\CollaboratorRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCollaboratorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $this->user()->can('inviteCollaborator', $event);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                'exists:users,email',
            ],
            'roles' => [
                'required',
                'array',
                'min:1',
            ],
            'roles.*' => [
                Rule::in(CollaboratorRole::values()),
            ],
            // Legacy single custom role
            'custom_role_id' => ['nullable', 'integer', 'exists:custom_roles,id'],
            // New multi custom roles
            'custom_role_ids' => ['nullable', 'array'],
            'custom_role_ids.*' => ['integer', 'exists:custom_roles,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $customRoleIds = $this->input('custom_role_ids');
        $legacy = $this->input('custom_role_id');

        if (is_null($customRoleIds) && !is_null($legacy)) {
            $this->merge(['custom_role_ids' => [$legacy]]);
        }
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'adresse email',
            'roles' => 'rôles',
            'roles.*' => 'rôle',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'L\'adresse email n\'est pas valide.',
            'email.exists' => 'Aucun utilisateur trouvé avec cette adresse email. L\'utilisateur doit d\'abord créer un compte.',
            'roles.required' => 'Au moins un rôle doit être sélectionné.',
            'roles.array' => 'Les rôles doivent être fournis sous forme de tableau.',
            'roles.min' => 'Au moins un rôle doit être sélectionné.',
            'roles.*.in' => 'Un des rôles sélectionnés n\'est pas valide.',
            'custom_role_id.exists' => 'Le rôle personnalisé sélectionné n\'existe pas.',
        ];
    }
}
