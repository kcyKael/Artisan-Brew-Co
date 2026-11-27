<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $attributes = [
        'stock_quantity' => 0,
        'low_stock_threshold' => 5,
        'stock_version' => 1,
        'is_active' => true,
    ];

    protected $fillable = [
        'name',
        'category_id',
        'sku',
        'description',
        'price',
        'stock_quantity',
        'low_stock_threshold',
        'image',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'stock_version' => 'integer',
            'is_active' => 'boolean',
            'archived_at' => 'datetime',
            'last_stock_alert_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeAvailableForPos(Builder $query): Builder
    {
        return $query->whereNull('archived_at')->where('is_active', true);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereNull('archived_at')
            ->where('stock_quantity', '>', 0)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
    }

    public function scopeSoldOut(Builder $query): Builder
    {
        return $query->whereNull('archived_at')
            ->where('stock_quantity', '<=', 0);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isSoldOut(): bool
    {
        return $this->stock_quantity <= 0;
    }

    public function isLowStock(): bool
    {
        return $this->stock_quantity > 0 && $this->stock_quantity <= $this->low_stock_threshold;
    }

    public function imageUrl(): ?string
    {
        if (! $this->image) {
            return null;
        }

        return route('media.show', [
            'type' => 'products',
            'filename' => basename($this->image),
        ]);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activeVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function addons(): BelongsToMany
    {
        return $this->belongsToMany(Addon::class, 'addon_product')->orderBy('sort_order')->orderBy('id');
    }

    public function activeAddons(): BelongsToMany
    {
        return $this->belongsToMany(Addon::class, 'addon_product')
            ->where('addons.is_active', true)
            ->orderBy('addons.sort_order')
            ->orderBy('addons.id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
