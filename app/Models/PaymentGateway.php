<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'currency',
        'rate',
        'min_amount',
        'max_amount',
        'fixed_charge',
        'percent_charge',
        'description',
        'credentials',
        'parameters',
        'status',
        'sort_order',
        'file',
    ];

    protected $casts = [
        'credentials' => 'array',
        'parameters' => 'array',
        'status' => 'boolean',
        'min_amount' => 'decimal:8',
        'max_amount' => 'decimal:8',
        'fixed_charge' => 'decimal:8',
        'percent_charge' => 'decimal:2',
    ];


    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file) return null;
        return asset('assets/files/' . $this->file);
    }

    public function deposits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    public function calculateCharge($amount)
    {
        $fixedCharge = $this->fixed_charge;
        $percentCharge = ($amount * $this->percent_charge) / 100;

        return $fixedCharge + $percentCharge;
    }

    public function getTotalAmountAttribute(): \Closure
    {
        return function($amount) {
            return $amount + $this->calculateCharge($amount);
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status ? 'Active' : 'Inactive';
    }

    public function getTypeLabelAttribute(): string
    {
        return ucfirst($this->type);
    }
}
