<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class StrongPassword implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        // Minimum 8 caractères
        if (strlen($value) < 8) {
            return false;
        }

        // Au moins une majuscule
        if (!preg_match('/[A-Z]/', $value)) {
            return false;
        }

        // Au moins une minuscule
        if (!preg_match('/[a-z]/', $value)) {
            return false;
        }

        // Au moins un chiffre
        if (!preg_match('/[0-9]/', $value)) {
            return false;
        }

        // Au moins un caractère spécial
        if (!preg_match('/[^A-Za-z0-9]/', $value)) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return 'Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.';
    }
}

