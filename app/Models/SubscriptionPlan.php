<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'mining_fee_percentage',
        'mining_rate_multiplier',
        'features',
        'is_active'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'mining_fee_percentage' => 'decimal:2',
        'mining_rate_multiplier' => 'decimal:2',
        'features' => 'array',
        'is_active' => 'boolean'
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 2);
    }
}
