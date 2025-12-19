<?php

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;

class ImportGuestsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageGuests', $this->route('event'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'delimiter' => ['nullable', 'string', 'in:,,;,\t'],
            'skip_duplicates' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'skip_duplicates' => $this->boolean('skip_duplicates', true),
        ]);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'csv_file.required' => 'Le fichier CSV est requis.',
            'csv_file.file' => 'Le fichier uploadé n\'est pas valide.',
            'csv_file.mimes' => 'Le fichier doit être au format CSV.',
            'csv_file.max' => 'Le fichier ne peut pas dépasser 2 Mo.',
            'delimiter.in' => 'Le délimiteur doit être une virgule, un point-virgule ou une tabulation.',
        ];
    }
}
