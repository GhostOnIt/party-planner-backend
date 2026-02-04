<?php

namespace App\Http\Requests\Photo;

use App\Enums\PhotoType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePhotoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $this->user()->can('managePhotos', $event);
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
            'type' => ['required', Rule::enum(PhotoType::class)],
            'description' => ['nullable', 'string', 'max:255'],
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
            'type' => 'type',
            'description' => 'description',
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
            'photos.*.uploaded' => "Le fichier n'a pas pu être envoyé. Vérifiez que la taille ne dépasse pas {$maxSizeMb} Mo et réessayez. Si le problème persiste, le serveur peut avoir une limite d'upload plus basse.",
            'type.required' => 'Le type de photo est obligatoire.',
            'type.Illuminate\Validation\Rules\Enum' => 'Le type de photo sélectionné est invalide.',
            'description.max' => 'La description ne peut pas dépasser 255 caractères.',
        ];
    }
}
