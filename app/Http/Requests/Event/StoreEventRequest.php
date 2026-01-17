<?php

namespace App\Http\Requests\Event;

use App\Enums\EventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxSize = config('partyplanner.uploads.photos.max_size', 5120);
        $allowedTypes = config('partyplanner.uploads.photos.allowed_types', ['jpeg', 'jpg', 'png', 'gif', 'webp']);

        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(EventType::class)],
            'description' => ['nullable', 'string', 'max:5000'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'estimated_budget' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'theme' => ['nullable', 'string', 'max:255'],
            'expected_guests_count' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'template_id' => ['nullable', 'exists:event_templates,id'],
            'cover_photo' => ['nullable', 'image', 'mimes:' . implode(',', $allowedTypes), "max:{$maxSize}"],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxSize = config('partyplanner.uploads.photos.max_size', 5120);
        $maxSizeMb = $maxSize / 1024;
        $allowedTypes = config('partyplanner.uploads.photos.allowed_types', ['jpeg', 'jpg', 'png', 'gif', 'webp']);

        return [
            'title.required' => 'Le titre de l\'événement est requis.',
            'title.max' => 'Le titre ne peut pas dépasser 255 caractères.',
            'type.required' => 'Le type d\'événement est requis.',
            'type.enum' => 'Le type d\'événement sélectionné est invalide.',
            'date.required' => 'La date de l\'événement est requise.',
            'date.after_or_equal' => 'La date doit être aujourd\'hui ou dans le futur.',
            'time.date_format' => 'Le format de l\'heure est invalide (HH:MM).',
            'estimated_budget.numeric' => 'Le budget doit être un nombre.',
            'estimated_budget.min' => 'Le budget ne peut pas être négatif.',
            'expected_guests_count.integer' => 'Le nombre d\'invités doit être un entier.',
            'expected_guests_count.min' => 'Le nombre d\'invités doit être au moins 1.',
            'cover_photo.image' => 'Le fichier doit être une image.',
            'cover_photo.mimes' => 'Le format de l\'image n\'est pas supporté. Formats acceptés : ' . implode(', ', $allowedTypes) . '.',
            'cover_photo.max' => "La taille de l'image ne peut pas dépasser {$maxSizeMb} Mo.",
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'titre',
            'type' => 'type',
            'description' => 'description',
            'date' => 'date',
            'time' => 'heure',
            'location' => 'lieu',
            'estimated_budget' => 'budget estimé',
            'theme' => 'thème',
            'expected_guests_count' => 'nombre d\'invités',
            'cover_photo' => 'photo de couverture',
        ];
    }
}
