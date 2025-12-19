<?php

namespace App\Http\Requests\Auth;

use App\Models\Otp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendOtpRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in(Otp::getTypes())],
            'channel' => ['required', 'string', Rule::in(Otp::getChannels())],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'identifier.required' => 'L\'email ou le numéro de téléphone est requis.',
            'type.required' => 'Le type d\'OTP est requis.',
            'type.in' => 'Le type d\'OTP est invalide.',
            'channel.required' => 'Le canal d\'envoi est requis.',
            'channel.in' => 'Le canal d\'envoi est invalide.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalize phone number if channel is SMS or WhatsApp
        if (in_array($this->channel, [Otp::CHANNEL_SMS, Otp::CHANNEL_WHATSAPP])) {
            $phone = preg_replace('/[^0-9+]/', '', $this->identifier ?? '');

            // Add Cameroon country code if missing
            if ($phone && !str_starts_with($phone, '+')) {
                if (str_starts_with($phone, '6') && strlen($phone) === 9) {
                    $phone = '+237' . $phone;
                } elseif (str_starts_with($phone, '237')) {
                    $phone = '+' . $phone;
                }
            }

            $this->merge(['identifier' => $phone]);
        }

        // Normalize email to lowercase
        if ($this->channel === Otp::CHANNEL_EMAIL) {
            $this->merge(['identifier' => strtolower($this->identifier ?? '')]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $channel = $this->channel;
            $identifier = $this->identifier;

            // Validate email format if channel is email
            if ($channel === Otp::CHANNEL_EMAIL) {
                if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                    $validator->errors()->add('identifier', 'L\'adresse email est invalide.');
                }
            }

            // Validate phone format if channel is SMS or WhatsApp
            if (in_array($channel, [Otp::CHANNEL_SMS, Otp::CHANNEL_WHATSAPP])) {
                if (!preg_match('/^\+?[1-9]\d{8,14}$/', $identifier ?? '')) {
                    $validator->errors()->add('identifier', 'Le numéro de téléphone est invalide.');
                }
            }
        });
    }
}
