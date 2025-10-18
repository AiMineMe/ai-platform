<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminRevenue extends Model
{
    use HasFactory;

    protected $table = 'admin_revenues';

    protected $fillable = [
        'revenue_type',
        'amount',
        'user_id',
        'description',
        'details'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'details' => 'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function recordRevenue(string $type, float $amount, int $userId, string $description, array $details = []): void
    {
        self::create([
            'revenue_type' => $type,
            'amount' => $amount,
            'user_id' => $userId,
            'description' => $description,
            'details' => $details
        ]);
    }
}
