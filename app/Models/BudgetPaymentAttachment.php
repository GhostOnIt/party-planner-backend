<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetPaymentAttachment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'budget_item_payment_id',
        'budget_item_id',
        'event_id',
        'uploaded_by',
        'original_name',
        'mime_type',
        'size',
        's3_path',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(BudgetItemPayment::class, 'budget_item_payment_id');
    }

    public function budgetItem(): BelongsTo
    {
        return $this->belongsTo(BudgetItem::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
