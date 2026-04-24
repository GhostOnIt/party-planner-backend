<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuoteRequest extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tracking_code',
        'user_id',
        'plan_id',
        'current_stage_id',
        'assigned_admin_id',
        'status',
        'outcome',
        'contact_name',
        'contact_email',
        'contact_phone',
        'company_name',
        'business_needs',
        'budget_estimate',
        'team_size',
        'timeline',
        'event_types',
        'call_scheduled_at',
        'outcome_note',
        'last_stage_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'budget_estimate' => 'integer',
            'team_size' => 'integer',
            'event_types' => 'array',
            'call_scheduled_at' => 'datetime',
            'last_stage_changed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(QuoteRequestStage::class, 'current_stage_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(QuoteRequestActivity::class);
    }
}
