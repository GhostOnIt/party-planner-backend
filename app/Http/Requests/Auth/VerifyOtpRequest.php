<?php

namespace App\Http\Requests\Auth;

use App\Models\Otp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyOtpRequest extends FormRequest
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
        return [
            'identifier' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'size:4', 'regex:/^[0-9]+$/'],
            'type' => ['required', 'string', Rule::in(Otp::getTypes())],
            'remember_me' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'identifier.required' => 'L\'email ou le numéro de téléphone est requis.',
            'code.required' => 'Le code de vérification est requis.',
            'code.size' => 'Le code doit contenir exactement 4 chiffres.',
            'code.regex' => 'Le code ne doit contenir que des chiffres.',
            'type.required' => 'Le type d\'OTP est requis.',
            'type.in' => 'Le type d\'OTP est invalide.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalize identifier
        $identifier = $this->identifier ?? '';

        // If it looks like a phone number, normalize it
        if (preg_match('/^[\d\s\-+()]+$/', $identifier)) {
            $phone = preg_replace('/[^0-9+]/', '', $identifier);

            if ($phone && !str_starts_with($phone, '+')) {
                if (str_starts_with($phone, '6') && strlen($phone) === 9) {
                    $phone = '+242' . $phone;
                } elseif (str_starts_with($phone, '237')) {
                    $phone = '+' . $phone;
                }
            }

            $this->merge(['identifier' => $phone]);
        } else {
            // Assume it's an email, normalize to lowercase
            $this->merge(['identifier' => strtolower($identifier)]);
        }

        // Remove any spaces from code
        if ($this->code) {
            $this->merge(['code' => preg_replace('/\s/', '', $this->code)]);
        }
    }
}
