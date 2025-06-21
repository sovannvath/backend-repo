<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'phone',
        'avatar',
        'street',
        'city',
        'state',
        'zip_code',
        'country',
        'preferences',
        'hire_date',
        'department',
        'employee_id',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'preferences' => 'array',
    ];

    /**
     * Get the role that owns the user.
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the cart associated with the user.
     */
    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    /**
     * Get the orders for the user.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the request orders created by the user.
     */
    public function requestOrders()
    {
        return $this->hasMany(RequestOrder::class, 'requested_by');
    }

    /**
     * Get the wishlist items for the user.
     */
    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Get the reviews written by the user.
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get the orders processed by the staff.
     */
    public function processedOrders()
    {
        return $this->hasMany(Order::class, 'staff_id');
    }

    /**
     * Check if the user has a specific role.
     *
     * @param string $roleName
     * @return bool
     */
    public function hasRole($roleName)
    {
        return $this->role->name === $roleName;
    }

    /**
     * Check if the user is an admin.
     *
     * @return bool
     */
    public function isAdmin()
    {
        return $this->hasRole('Admin');
    }

    /**
     * Check if the user is a warehouse manager.
     *
     * @return bool
     */
    public function isWarehouseManager()
    {
        return $this->hasRole('Warehouse Manager');
    }

    /**
     * Check if the user is a staff member.
     *
     * @return bool
     */
    public function isStaff()
    {
        return $this->hasRole('Staff');
    }

    /**
     * Check if the user is a customer.
     *
     * @return bool
     */
    public function isCustomer()
    {
        return $this->hasRole('Customer');
    }

    /**
     * Get user statistics
     *
     * @return array
     */
    public function getStats()
    {
        $totalOrders = $this->orders()->count();
        $totalSpent = $this->orders()->where('status', 'completed')->sum('total_amount');
        $averageOrderValue = $totalOrders > 0 ? $totalSpent / $totalOrders : 0;
        
        return [
            'total_orders' => $totalOrders,
            'total_spent' => round($totalSpent, 2),
            'loyalty_points' => floor($totalSpent / 10), // 1 point per $10 spent
            'average_order_value' => round($averageOrderValue, 2),
        ];
    }

    /**
     * Get default user preferences
     *
     * @return array
     */
    public function getDefaultPreferences()
    {
        return [
            'email_notifications' => true,
            'sms_notifications' => false,
            'marketing_emails' => true,
            'order_updates' => true,
        ];
    }

    /**
     * Get user preferences with defaults
     *
     * @return array
     */
    public function getUserPreferences()
    {
        return array_merge($this->getDefaultPreferences(), $this->preferences ?? []);
    }
}
