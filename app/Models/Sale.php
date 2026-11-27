<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'customer_id',
        'sold_by',
        'product_name_snapshot',
        'variant_label_snapshot',
        'base_price_snapshot',
        'variant_adjustment_snapshot',
        'addons_total_snapshot',
        'unit_price',
        'quantity',
        'total_price',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'base_price_snapshot' => 'decimal:2',
            'variant_adjustment_snapshot' => 'decimal:2',
            'addons_total_snapshot' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'quantity' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by')->withTrashed();
    }

    public function saleAddons(): HasMany
    {
        return $this->hasMany(SaleAddon::class)->orderBy('id');
    }
}
