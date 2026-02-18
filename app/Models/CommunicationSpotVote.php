<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationSpotVote extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'spot_id',
        'user_id',
        'option_id',
    ];

    /**
     * Get the spot that was voted on.
     */
    public function spot(): BelongsTo
    {
        return $this->belongsTo(CommunicationSpot::class, 'spot_id');
    }

    /**
     * Get the user who voted.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
