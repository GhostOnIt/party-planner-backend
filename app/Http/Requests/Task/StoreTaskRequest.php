<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $assigned = $this->input('assigned_to_user_id');
        if ($assigned === 0 || $assigned === '0' || $assigned === '') {
            $this->merge(['assigned_to_user_id' => null]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageTasks', $this->route('event'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'due_date' => ['nullable', 'date'],
            'assigned_to_user_id' => ['nullable', 'exists:users,id'],
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
            'title.required' => 'Le titre de la tâche est requis.',
            'title.max' => 'Le titre ne peut pas dépasser 255 caractères.',
            'priority.required' => 'La priorité est requise.',
            'priority.enum' => 'La priorité sélectionnée est invalide.',
            'due_date.date' => 'La date d\'échéance n\'est pas valide.',
            'assigned_to_user_id.exists' => 'L\'utilisateur assigné n\'existe pas.',
        ];
    }
}
