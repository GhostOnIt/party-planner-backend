<?php

namespace App\Http\Requests\Event;

use App\Enums\EventStatus;
use App\Enums\EventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('event'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();

        // Get user's event type slugs
        $userEventTypeSlugs = $user->eventTypes()->pluck('slug')->toArray();
        // Also include default enum values for backward compatibility
        $defaultTypeSlugs = array_column(\App\Enums\EventType::cases(), 'value');
        $allowedTypeSlugs = array_merge($userEventTypeSlugs, $defaultTypeSlugs);

        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in($allowedTypeSlugs)],
            'description' => ['nullable', 'string', 'max:5000'],
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
            'location' => ['required', 'string', 'max:255'],
            'estimated_budget' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'theme' => ['nullable', 'string', 'max:255'],
            'expected_guests_count' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'status' => ['required', Rule::enum(EventStatus::class)],
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
            'title.required' => 'Le titre de l\'événement est requis.',
            'type.required' => 'Le type d\'événement est requis.',
            'type.in' => 'Le type d\'événement sélectionné est invalide.',
            'date.required' => 'La date de l\'événement est requise.',
            'time.required' => 'L\'heure de l\'événement est requise.',
            'time.date_format' => 'Le format de l\'heure est invalide (HH:MM).',
            'location.required' => 'Le lieu de l\'événement est requis.',
            'location.max' => 'Le lieu ne peut pas dépasser 255 caractères.',
            'status.required' => 'Le statut est requis.',
            'status.enum' => 'Le statut sélectionné est invalide.',
        ];
    }
}
