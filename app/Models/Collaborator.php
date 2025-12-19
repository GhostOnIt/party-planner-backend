<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Collaborator extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'user_id',
        'role',
        'invited_at',
        'accepted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * Get the event that the collaborator belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the user that is the collaborator.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the collaborator is the owner.
     */
    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /**
     * Check if the collaborator can edit.
     */
    public function canEdit(): bool
    {
        return in_array($this->role, ['owner', 'editor']);
    }

    /**
     * Check if the invitation has been accepted.
     */
    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }
}
