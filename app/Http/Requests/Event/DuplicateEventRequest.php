<?php

namespace App\Http\Requests\Event;

use App\Enums\EventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DuplicateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('duplicate', $this->route('event'));
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $userEventTypeSlugs = $user->eventTypes()->pluck('slug')->toArray();
        $defaultTypeSlugs = array_column(EventType::cases(), 'value');
        $allowedTypeSlugs = array_merge($userEventTypeSlugs, $defaultTypeSlugs);

        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', Rule::in($allowedTypeSlugs)],
            'date' => ['nullable', 'date', 'after_or_equal:today'],
            'time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'theme' => ['nullable', 'string', 'max:255'],
            'expected_guests_count' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'include_guests' => ['nullable', 'boolean'],
            'include_tasks' => ['nullable', 'boolean'],
            'include_budget' => ['nullable', 'boolean'],
            'include_collaborators' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Ensure booleans
        if ($this->has('include_guests')) {
            $this->merge(['include_guests' => $this->boolean('include_guests')]);
        }
        if ($this->has('include_tasks')) {
            $this->merge(['include_tasks' => $this->boolean('include_tasks')]);
        }
        if ($this->has('include_budget')) {
            $this->merge(['include_budget' => $this->boolean('include_budget')]);
        }
        if ($this->has('include_collaborators')) {
            $this->merge(['include_collaborators' => $this->boolean('include_collaborators')]);
        }
    }
}
