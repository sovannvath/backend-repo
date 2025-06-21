<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAlert extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'alert_type',
        'message',
        'is_resolved',
        'resolved_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the product associated with this alert.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Resolve the alert.
     *
     * @return bool
     */
    public function resolve()
    {
        return $this->update([
            'is_resolved' => true,
            'resolved_at' => now(),
        ]);
    }

    /**
     * Check if the alert is resolved.
     *
     * @return bool
     */
    public function isResolved()
    {
        return $this->is_resolved;
    }

    /**
     * Create a low stock alert for a product.
     *
     * @param Product $product
     * @return static
     */
    public static function createLowStockAlert(Product $product)
    {
        return static::create([
            'product_id' => $product->id,
            'alert_type' => 'low_stock',
            'message' => "Product '{$product->name}' is running low on stock. Current quantity: {$product->quantity}",
        ]);
    }

    /**
     * Create an out of stock alert for a product.
     *
     * @param Product $product
     * @return static
     */
    public static function createOutOfStockAlert(Product $product)
    {
        return static::create([
            'product_id' => $product->id,
            'alert_type' => 'out_of_stock',
            'message' => "Product '{$product->name}' is out of stock!",
        ]);
    }

    /**
     * Create a reorder needed alert for a product.
     *
     * @param Product $product
     * @return static
     */
    public static function createReorderNeededAlert(Product $product)
    {
        return static::create([
            'product_id' => $product->id,
            'alert_type' => 'reorder_needed',
            'message' => "Product '{$product->name}' needs to be reordered. Current quantity: {$product->quantity}",
        ]);
    }
}

