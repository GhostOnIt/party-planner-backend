<?php

namespace App\Http\Requests\Photo;

use Illuminate\Foundation\Http\FormRequest;

class PublicStorePhotoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Public requests don't require authentication, but token validation happens in controller.
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
        $maxSize = max(10240, (int) config('partyplanner.uploads.photos.max_size', 10240));
        $maxPerUpload = config('partyplanner.uploads.photos.max_per_upload', 10);
        $allowedTypes = config('partyplanner.uploads.photos.allowed_types', ['jpeg', 'jpg', 'png', 'gif', 'webp']);

        return [
            'photos' => ['required', 'array', 'min:1', "max:{$maxPerUpload}"],
            'photos.*' => [
                'required',
                'image',
                'mimes:' . implode(',', $allowedTypes),
                "max:{$maxSize}",
            ],
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
            'photos' => 'photos',
            'photos.*' => 'photo',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxSize = max(10240, (int) config('partyplanner.uploads.photos.max_size', 10240));
        $maxSizeMb = (int) round($maxSize / 1024);
        $maxPerUpload = config('partyplanner.uploads.photos.max_per_upload', 10);

        return [
            'photos.required' => 'Veuillez sélectionner au moins une photo.',
            'photos.array' => 'Format de données invalide.',
            'photos.min' => 'Veuillez sélectionner au moins une photo.',
            'photos.max' => "Vous ne pouvez pas uploader plus de {$maxPerUpload} photos à la fois.",
            'photos.*.required' => 'Le fichier photo est requis.',
            'photos.*.image' => 'Le fichier doit être une image.',
            'photos.*.mimes' => 'Le format de l\'image n\'est pas supporté. Formats acceptés : JPEG, PNG, GIF, WebP.',
            'photos.*.max' => "La taille de l'image ne peut pas dépasser {$maxSizeMb} Mo.",
        ];
    }
}

