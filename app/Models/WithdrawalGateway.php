<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WithdrawalGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'currency',
        'rate',
        'min_amount',
        'max_amount',
        'fixed_charge',
        'percent_charge',
        'description',
        'status',
        'parameters',
    ];

    protected $casts = [
        'min_amount' => 'decimal:8',
        'max_amount' => 'decimal:8',
        'fixed_charge' => 'decimal:8',
        'percent_charge' => 'decimal:2',
        'status' => 'boolean',
        'parameters' => 'array',
    ];

    public function withdrawals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeForCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }

    public function calculateCharge($amount)
    {
        $fixedCharge = $this->fixed_charge ?? 0;
        $percentCharge = ($amount * ($this->percent_charge ?? 0)) / 100;

        return $fixedCharge + $percentCharge;
    }

    public function getFormattedChargeAttribute(): string
    {
        if ($this->fixed_charge > 0 && $this->percent_charge > 0) {
            return number_format($this->fixed_charge, 2) . ' + ' . number_format($this->percent_charge, 2) . '%';
        } elseif ($this->fixed_charge > 0) {
            return number_format($this->fixed_charge, 2);
        } elseif ($this->percent_charge > 0) {
            return number_format($this->percent_charge, 2) . '%';
        }

        return 'Free';
    }
}
