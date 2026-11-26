<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    use HasFactory;

    public const TEMP_HOT = 'hot';
    public const TEMP_ICED = 'iced';

    protected $fillable = [
        'product_id',
        'size_label',
        'temperature',
        'price_adjustment',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_adjustment' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function displayLabel(): string
    {
        return sprintf('%s · %s', $this->size_label, ucfirst($this->temperature));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
