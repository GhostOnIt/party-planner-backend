<?php

namespace App\Http\Requests\Collaborator;

use App\Enums\CollaboratorRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCollaboratorRequest extends FormRequest
{
    private const MAX_ROLES_PER_COLLABORATOR = 3;

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
        $event = $this->route('event');
        $eventOwner = $event->user;
        
        // Get system assignable roles
        $assignableRoleValues = collect(CollaboratorRole::assignableRoles())->map(fn ($r) => $r->value)->toArray();
        
        // Get event owner's custom role slugs
        $customRoleSlugs = $eventOwner->collaboratorRoles()->pluck('slug')->toArray();
        
        // Combine system roles and custom roles
        $allowedRoleValues = array_merge($assignableRoleValues, $customRoleSlugs);

        return [
            'email' => [
                'required',
                'email',
            ],
            // At least one of: role, roles (non-empty), or custom_role_ids (non-empty)
            'role' => [
                'required_without_all:roles,custom_role_ids',
                'nullable',
                'string',
                Rule::in($allowedRoleValues),
            ],
            'roles' => [
                'required_without_all:role,custom_role_ids',
                'nullable',
                'array',
            ],
            'roles.*' => [
                'string',
                Rule::in($allowedRoleValues),
            ],
            // Legacy single custom role (must belong to this event)
            'custom_role_id' => [
                'nullable',
                'integer',
                Rule::exists('custom_roles', 'id')->where('user_id', $event->user_id),
            ],
            // Multi custom roles (must belong to event owner; custom roles are user-scoped). Can be empty when only system roles are selected.
            'custom_role_ids' => ['nullable', 'array'],
            'custom_role_ids.*' => [
                'integer',
                Rule::exists('custom_roles', 'id')->where('user_id', $event->user_id),
            ],
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $roles = $this->input('roles');
            if (! is_array($roles)) {
                $roles = [];
            }
            $role = $this->input('role');
            if (empty($roles) && $role) {
                $roles = [$role];
            }
            $roles = array_values(array_unique(array_filter($roles)));
            $customIds = $this->input('custom_role_ids');
            if (! is_array($customIds)) {
                $customIds = [];
            }
            $customIds = array_values(array_unique(array_map('intval', $customIds)));

            $systemCount = count($roles);
            if ($systemCount === 0 && count($customIds) > 0) {
                $systemCount = 1;
            }

            if ($systemCount + count($customIds) > self::MAX_ROLES_PER_COLLABORATOR) {
                $validator->errors()->add(
                    'roles',
                    'Un collaborateur ne peut avoir que 3 rôles au maximum (rôles système et personnalisés).'
                );
            }
        });
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
            'email.exists' => 'L\'adresse email n\'est pas valide.',
            'roles.required' => 'Au moins un rôle doit être sélectionné.',
            'roles.array' => 'Les rôles doivent être fournis sous forme de tableau.',
            'roles.min' => 'Au moins un rôle doit être sélectionné.',
            'roles.*.in' => 'Un des rôles sélectionnés n\'est pas valide.',
            'custom_role_id.exists' => 'Le rôle personnalisé sélectionné n\'existe pas ou ne vous appartient pas.',
            'custom_role_ids.*.exists' => 'Un des rôles personnalisés n\'existe pas ou ne vous appartient pas.',
        ];
    }
}
