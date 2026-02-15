<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUuids;

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Find the token instance matching the given token.
     * Override to handle UUID primary key: old tokens with format "74|xxx"
     * (integer ID) would fail with uuid column. Fall back to hash lookup.
     */
    public static function findToken($token)
    {
        if (strpos($token, '|') === false) {
            return static::where('token', hash('sha256', $token))->first();
        }

        [$id, $tokenPart] = explode('|', $token, 2);

        // Only use find($id) when $id looks like a valid UUID
        if (Str::isUuid($id)) {
            $instance = static::find($id);
            if ($instance && hash_equals($instance->token, hash('sha256', $tokenPart))) {
                return $instance;
            }
            return null;
        }

        // Fallback for old tokens with integer ID format (e.g. "74|xxx")
        return static::where('token', hash('sha256', $tokenPart))->first();
    }
}
