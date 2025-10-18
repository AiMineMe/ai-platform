<?php

namespace App\Models;

use App\Enums\Wallet\Status;
use App\Enums\Wallet\Type;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'currency',
        'address',
        'balance',
        'status',
        'last_activity',
    ];

    protected $casts = [
        'balance' => 'decimal:8',
        'status' => 'integer',
        'last_activity' => 'datetime',
    ];

    protected $hidden = [
        'private_key',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', Status::ACTIVE->value);
    }

    public function scopeMain($query)
    {
        return $query->where('type', Type::MAIN->value);
    }

    public function scopeTrade($query)
    {
        return $query->where('type', Type::TRADE->value);
    }

    public function isActive(): bool
    {
        return $this->status === Status::ACTIVE->value;
    }
}
