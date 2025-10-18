<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TokenSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ico_token_id',
        'sale_id',
        'tokens_sold',
        'sale_price',
        'total_amount',
        'status',
        'sold_at'
    ];

    protected $casts = [
        'tokens_sold' => 'integer',
        'sale_price' => 'decimal:4',
        'total_amount' => 'decimal:2',
        'sold_at' => 'datetime'
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function icoToken(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(IcoToken::class);
    }

    public static function generateSaleId(): string
    {
        return 'SALE-' . strtoupper(uniqid()) . '-' . time();
    }
}
