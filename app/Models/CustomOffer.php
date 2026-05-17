<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomOffer extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'quote_request_id',
        'created_by',
        'title',
        'description',
        'price_amount',
        'price_currency',
        'features',
        'terms',
        'validity_days',
        'expires_at',
        'status',
        'client_token',
        'client_responded_at',
        'client_response_note',
    ];

    protected function casts(): array
    {
        return [
            'price_amount' => 'integer',
            'validity_days' => 'integer',
            'features' => 'array',
            'expires_at' => 'datetime',
            'client_responded_at' => 'datetime',
        ];
    }

    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at
            && $this->expires_at->isPast()
            && $this->status === 'sent';
    }
}
