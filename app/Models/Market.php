<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Market extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol',
        'pair',
        'base',
        'quote',
        'price',
        'change_24h',
        'volume_24h',
        'market_cap',
        'high_24h',
        'low_24h',
        'liquidity',
        'status',
        'min_trade_amount',
        'max_trade_amount',
        'maker_fee',
        'taker_fee',
        'price_precision',
        'amount_precision',
        'icon',
        'last_updated',
    ];

    protected $casts = [
        'price' => 'decimal:8',
        'change_24h' => 'decimal:4',
        'volume_24h' => 'decimal:2',
        'market_cap' => 'decimal:2',
        'high_24h' => 'decimal:8',
        'low_24h' => 'decimal:8',
        'min_trade_amount' => 'decimal:8',
        'max_trade_amount' => 'decimal:8',
        'maker_fee' => 'decimal:4',
        'taker_fee' => 'decimal:4',
        'last_updated' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeTopGainers($query, $limit = 5)
    {
        return $query->where('change_24h', '>', 0)
            ->orderBy('change_24h', 'desc')
            ->limit($limit);
    }

    public function scopeTopLosers($query, $limit = 5)
    {
        return $query->where('change_24h', '<', 0)
            ->orderBy('change_24h', 'asc')
            ->limit($limit);
    }
}
