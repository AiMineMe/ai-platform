<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class IcoToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'symbol',
        'description',
        'price',
        'current_price',
        'price_updated_at',
        'total_supply',
        'tokens_sold',
        'sale_start_date',
        'sale_end_date',
        'status',
        'is_featured'
    ];

    protected $casts = [
        'price' => 'decimal:4',
        'current_price' => 'decimal:4',
        'total_supply' => 'integer',
        'tokens_sold' => 'integer',
        'sale_start_date' => 'date',
        'sale_end_date' => 'date',
        'is_featured' => 'boolean',
        'price_updated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $appends = [
        'tokens_remaining',
        'progress_percentage',
        'total_raised',
        'is_active',
        'days_remaining',
        'price_change_percentage'
    ];

    public function purchases(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(IcoPurchase::class);
    }

    public function getTokensRemainingAttribute()
    {
        return $this->total_supply - $this->tokens_sold;
    }

    public function getProgressPercentageAttribute(): float|int
    {
        if ($this->total_supply == 0) return 0;
        return round(($this->tokens_sold / $this->total_supply) * 100, 2);
    }

    public function getTotalRaisedAttribute(): float|int
    {
        return $this->tokens_sold * $this->price;
    }

    public function getIsActiveAttribute(): bool
    {
        $now = Carbon::now();
        return $this->status === 'active' &&
            $now->greaterThanOrEqualTo($this->sale_start_date) &&
            $now->lessThanOrEqualTo($this->sale_end_date);
    }

    public function getDaysRemainingAttribute(): float|int
    {
        $now = Carbon::now();
        $endDate = Carbon::parse($this->sale_end_date);

        if ($now->greaterThan($endDate)) {
            return 0;
        }

        return $now->diffInDays($endDate);
    }

    public function getPriceChangePercentageAttribute(): float|int
    {
        if (!$this->current_price || !$this->price || $this->price == 0) {
            return 0;
        }

        return round((($this->current_price - $this->price) / $this->price) * 100, 2);
    }

    public function getCurrentPriceAttribute($value)
    {
        return $value ?? $this->price;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('symbol', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    public function scopeByStatus($query, $status)
    {
        if ($status) {
            return $query->where('status', $status);
        }
        return $query;
    }

    public function updateCurrentPrice($newPrice): bool
    {
        $this->current_price = $newPrice;
        $this->price_updated_at = now();
        return $this->save();
    }
}
