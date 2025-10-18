<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IcoPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ico_token_id',
        'purchase_id',
        'amount_usd',
        'tokens_purchased',
        'token_price',
        'status',
        'purchased_at',
        'metadata'
    ];

    protected $casts = [
        'amount_usd' => 'decimal:2',
        'tokens_purchased' => 'integer',
        'token_price' => 'decimal:4',
        'purchased_at' => 'datetime',
        'metadata' => 'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function icoToken(): BelongsTo
    {
        return $this->belongsTo(IcoToken::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed';
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending';
    }

    public function getFormattedTokensAttribute(): string
    {
        return number_format($this->tokens_purchased);
    }

    public static function generatePurchaseId(): string
    {
        return 'ICO-' . strtoupper(uniqid()) . '-' . time();
    }

    public function markAsCompleted(): bool
    {
        $this->status = 'completed';
        $this->purchased_at = now();
        return $this->save();
    }

    public function markAsFailed(): bool
    {
        $this->status = 'failed';
        return $this->save();
    }
}
