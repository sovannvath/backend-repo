<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'price',
        'quantity',
        'low_stock_threshold',
        'reorder_quantity',
        'auto_reorder',
        'reorder_cost',
        'category_id',
        'brand_id',
        'image',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
        'reorder_cost' => 'decimal:2',
        'auto_reorder' => 'boolean',
        'is_active' => 'boolean',
        'low_stock_threshold' => 'integer',
        'reorder_quantity' => 'integer',
        'quantity' => 'integer',
    ];

    /**
     * Get the category that owns the product.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the brand that owns the product.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get the cart items for the product.
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Get the order items for the product.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the request orders for the product.
     */
    public function requestOrders(): HasMany
    {
        return $this->hasMany(RequestOrder::class);
    }

    /**
     * Get the reviews for the product.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get the wishlist items for the product.
     */
    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Get the images for the product.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    /**
     * Get the reorder requests for the product.
     */
    public function reorderRequests(): HasMany
    {
        return $this->hasMany(ReorderRequest::class);
    }

    /**
     * Get the inventory alerts for the product.
     */
    public function inventoryAlerts(): HasMany
    {
        return $this->hasMany(InventoryAlert::class);
    }

    /**
     * Check if the product is low on stock.
     *
     * @return bool
     */
    public function isLowStock(): bool
    {
        return $this->quantity <= $this->low_stock_threshold;
    }

    /**
     * Check if the product is out of stock.
     *
     * @return bool
     */
    public function isOutOfStock(): bool
    {
        return $this->quantity <= 0;
    }

    /**
     * Check if the product needs reordering.
     *
     * @return bool
     */
    public function needsReordering(): bool
    {
        return $this->isLowStock() && $this->auto_reorder;
    }

    /**
     * Create inventory alerts for this product if needed.
     *
     * @return void
     */
    public function checkAndCreateAlerts(): void
    {
        // Check if there are already unresolved alerts for this product
        $hasUnresolvedAlerts = $this->inventoryAlerts()
                                   ->where('is_resolved', false)
                                   ->exists();

        if ($hasUnresolvedAlerts) {
            return; // Don't create duplicate alerts
        }

        if ($this->isOutOfStock()) {
            InventoryAlert::createOutOfStockAlert($this);
        } elseif ($this->isLowStock()) {
            InventoryAlert::createLowStockAlert($this);
        }

        if ($this->needsReordering()) {
            InventoryAlert::createReorderNeededAlert($this);
        }
    }

    /**
     * Get the average rating for the product.
     *
     * @return float
     */
    public function getAverageRating(): float
    {
        return $this->reviews()->avg('rating') ?? 0;
    }

    /**
     * Get the total number of reviews for the product.
     *
     * @return int
     */
    public function getReviewCount(): int
    {
        return $this->reviews()->count();
    }

    /**
     * Scope a query to only include low stock products.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLowStock($query)
    {
        return $query->whereRaw('quantity <= low_stock_threshold');
    }

    /**
     * Scope a query to only include out of stock products.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOutOfStock($query)
    {
        return $query->where('quantity', '<=', 0);
    }

    /**
     * Scope a query to only include products that need reordering.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNeedsReordering($query)
    {
        return $query->whereRaw('quantity <= low_stock_threshold')
                     ->where('auto_reorder', true);
    }
}

