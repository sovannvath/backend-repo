<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'order_number',
        'total_amount',
        'payment_method_id',
        'payment_status',
        'order_status',
        'staff_id',
        'notes',
        'approved_at',
        'rejected_at',
        'staff_notes',
        'approval_status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'total_amount' => 'decimal:2',
        'payment_status' => 'string',
        'order_status' => 'string',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * Get the user that owns the order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the staff that processed the order.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /**
     * Get the payment method for the order.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * Get the order items for the order.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the transactions for the order.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Scope a query to filter orders by date range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to filter orders by order number.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $orderNumber
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderNumber($query, $orderNumber)
    {
        return $query->where('order_number', $orderNumber);
    }

    /**
     * Scope a query to filter orders by approval status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeApprovalStatus($query, $status)
    {
        return $query->where('approval_status', $status);
    }

    /**
     * Approve the order.
     *
     * @param int $staffId
     * @param string|null $notes
     * @return bool
     */
    public function approve($staffId, $notes = null)
    {
        return $this->update([
            'approval_status' => 'approved',
            'staff_id' => $staffId,
            'approved_at' => now(),
            'staff_notes' => $notes,
            'order_status' => 'processing',
        ]);
    }

    /**
     * Reject the order.
     *
     * @param int $staffId
     * @param string|null $notes
     * @return bool
     */
    public function reject($staffId, $notes = null)
    {
        return $this->update([
            'approval_status' => 'rejected',
            'staff_id' => $staffId,
            'rejected_at' => now(),
            'staff_notes' => $notes,
            'order_status' => 'cancelled',
        ]);
    }

    /**
     * Check if order is pending approval.
     *
     * @return bool
     */
    public function isPending()
    {
        return $this->approval_status === 'pending';
    }

    /**
     * Check if order is approved.
     *
     * @return bool
     */
    public function isApproved()
    {
        return $this->approval_status === 'approved';
    }

    /**
     * Check if order is rejected.
     *
     * @return bool
     */
    public function isRejected()
    {
        return $this->approval_status === 'rejected';
    }
}
