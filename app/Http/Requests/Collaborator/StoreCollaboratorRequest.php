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
                'exists:users,email',
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
                Rule::exists('custom_roles', 'id')->where('event_id', $event->id),
            ],
            // New multi custom roles (must belong to this event)
            'custom_role_ids' => ['nullable', 'array', 'min:1'],
            'custom_role_ids.*' => [
                'integer',
                Rule::exists('custom_roles', 'id')->where('event_id', $event->id),
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
            'custom_role_id.exists' => 'Le rôle personnalisé sélectionné n\'existe pas ou n\'appartient pas à cet événement.',
            'custom_role_ids.*.exists' => 'Un des rôles personnalisés n\'existe pas ou n\'appartient pas à cet événement.',
        ];
    }
}
