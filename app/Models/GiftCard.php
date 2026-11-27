<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'initial_balance',
        'remaining_balance',
        'is_active',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'initial_balance' => 'decimal:2',
            'remaining_balance' => 'decimal:2',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isUsable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (Money::toCentavos((string) $this->remaining_balance) <= 0) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
