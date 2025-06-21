<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReorderRequest extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'admin_id',
        'warehouse_staff_id',
        'quantity_requested',
        'quantity_approved',
        'estimated_cost',
        'status',
        'notes',
        'warehouse_notes',
        'approved_at',
        'completed_at',
        'warehouse_approved_at',
        'warehouse_rejected_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'estimated_cost' => 'decimal:2',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'warehouse_approved_at' => 'datetime',
        'warehouse_rejected_at' => 'datetime',
    ];

    /**
     * Get the product that needs reordering.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the admin who created the reorder request.
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Get the warehouse staff who handled the reorder request.
     */
    public function warehouseStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'warehouse_staff_id');
    }

    /**
     * Check if the reorder request is pending.
     *
     * @return bool
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the reorder request is approved.
     *
     * @return bool
     */
    public function isApproved()
    {
        return $this->status === 'approved';
    }

    /**
     * Check if the reorder request is completed.
     *
     * @return bool
     */
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    /**
     * Approve the reorder request by warehouse.
     *
     * @param int $warehouseStaffId
     * @param int $quantityApproved
     * @param string|null $notes
     * @return bool
     */
    public function warehouseApprove($warehouseStaffId, $quantityApproved, $notes = null)
    {
        return $this->update([
            'warehouse_staff_id' => $warehouseStaffId,
            'quantity_approved' => $quantityApproved,
            'warehouse_notes' => $notes,
            'warehouse_approved_at' => now(),
            'status' => 'approved',
        ]);
    }

    /**
     * Reject the reorder request by warehouse.
     *
     * @param int $warehouseStaffId
     * @param string|null $notes
     * @return bool
     */
    public function warehouseReject($warehouseStaffId, $notes = null)
    {
        return $this->update([
            'warehouse_staff_id' => $warehouseStaffId,
            'warehouse_notes' => $notes,
            'warehouse_rejected_at' => now(),
            'status' => 'cancelled',
        ]);
    }

    /**
     * Complete the reorder request and update product stock.
     *
     * @return bool
     */
    public function complete()
    {
        $quantityToAdd = $this->quantity_approved ?? $this->quantity_requested;
        
        $success = $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        if ($success) {
            // Update product stock with approved quantity
            $this->product->increment('quantity', $quantityToAdd);
            
            // Resolve related inventory alerts
            InventoryAlert::where('product_id', $this->product_id)
                         ->where('is_resolved', false)
                         ->update([
                             'is_resolved' => true,
                             'resolved_at' => now(),
                         ]);
        }

        return $success;
    }

    /**
     * Cancel the reorder request.
     *
     * @return bool
     */
    public function cancel()
    {
        return $this->update(['status' => 'cancelled']);
    }
}

