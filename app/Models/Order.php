<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'customer_id',
        'sold_by',
        'customer_name_snapshot',
        'staff_name_snapshot',
        'source',
        'order_type',
        'payment_method',
        'payment_reference',
        'cup_name',
        'order_note',
        'subtotal_amount',
        'discount_type',
        'discount_label',
        'discount_amount',
        'gift_card_id',
        'total_amount',
        'cash_received',
        'change_amount',
        'status',
        'voided_at',
        'voided_by',
        'void_reason',
        'checkout_token',
        'request_hash',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'cash_received' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'voided_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by')->withTrashed();
    }

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by')->withTrashed();
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class)->orderBy('id');
    }
}
