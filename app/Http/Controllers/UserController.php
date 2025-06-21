<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Display a listing of users (customers).
     */
    public function index(Request $request)
    {
        $query = User::with('role')
            ->whereHas('role', function ($q) {
                $q->where('name', 'Customer');
            });

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by active status
        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', $request->is_active);
        }

        // Filter by registration date
        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'users' => $users
        ]);
    }

    /**
     * Display the specified user.
     */
    public function show($id)
    {
        $user = User::with(['role', 'orders.orderItems.product', 'orders.paymentMethod'])
            ->whereHas('role', function ($q) {
                $q->where('name', 'Customer');
            })
            ->findOrFail($id);

        // Get user statistics
        $stats = $this->getUserStats($id);

        return response()->json([
            'user' => $user,
            'stats' => $stats
        ]);
    }

    /**
     * Suspend a user.
     */
    public function suspend(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:1000'
        ]);

        $user = User::whereHas('role', function ($q) {
            $q->where('name', 'Customer');
        })->findOrFail($id);

        if (!$user->is_active) {
            return response()->json([
                'message' => 'User is already suspended'
            ], 400);
        }

        $user->update([
            'is_active' => false,
            'suspension_reason' => $request->reason,
            'suspension_notes' => $request->notes,
            'suspended_at' => now(),
            'suspended_by' => auth()->id()
        ]);

        // Log the suspension
        DB::table('user_suspensions')->insert([
            'user_id' => $user->id,
            'suspended_by' => auth()->id(),
            'reason' => $request->reason,
            'notes' => $request->notes,
            'suspended_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Send notification to user (optional)
        // $user->notify(new \App\Notifications\AccountSuspended($request->reason, $request->notes));

        return response()->json([
            'message' => 'User suspended successfully',
            'user' => $user->fresh()
        ]);
    }

    /**
     * Reactivate a suspended user.
     */
    public function reactivate(Request $request, $id)
    {
        $request->validate([
            'notes' => 'nullable|string|max:1000'
        ]);

        $user = User::whereHas('role', function ($q) {
            $q->where('name', 'Customer');
        })->findOrFail($id);

        if ($user->is_active) {
            return response()->json([
                'message' => 'User is already active'
            ], 400);
        }

        $user->update([
            'is_active' => true,
            'suspension_reason' => null,
            'suspension_notes' => null,
            'suspended_at' => null,
            'suspended_by' => null,
            'reactivated_at' => now(),
            'reactivated_by' => auth()->id(),
            'reactivation_notes' => $request->notes
        ]);

        // Log the reactivation
        DB::table('user_suspensions')->insert([
            'user_id' => $user->id,
            'reactivated_by' => auth()->id(),
            'action_type' => 'reactivated',
            'notes' => $request->notes,
            'reactivated_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Send notification to user (optional)
        // $user->notify(new \App\Notifications\AccountReactivated($request->notes));

        return response()->json([
            'message' => 'User reactivated successfully',
            'user' => $user->fresh()
        ]);
    }

    /**
     * Get user suspension history.
     */
    public function getSuspensionHistory($id)
    {
        $user = User::findOrFail($id);
        
        $history = DB::table('user_suspensions')
            ->leftJoin('users as suspended_by_user', 'user_suspensions.suspended_by', '=', 'suspended_by_user.id')
            ->leftJoin('users as reactivated_by_user', 'user_suspensions.reactivated_by', '=', 'reactivated_by_user.id')
            ->where('user_suspensions.user_id', $id)
            ->select(
                'user_suspensions.*',
                'suspended_by_user.name as suspended_by_name',
                'reactivated_by_user.name as reactivated_by_name'
            )
            ->orderBy('user_suspensions.created_at', 'desc')
            ->get();

        return response()->json([
            'user' => $user,
            'suspension_history' => $history
        ]);
    }

    /**
     * Get users dashboard data for admin.
     */
    public function dashboard(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        // User statistics
        $totalUsers = User::whereHas('role', function ($q) {
            $q->where('name', 'Customer');
        })->count();

        $activeUsers = User::whereHas('role', function ($q) {
            $q->where('name', 'Customer');
        })->where('is_active', true)->count();

        $suspendedUsers = User::whereHas('role', function ($q) {
            $q->where('name', 'Customer');
        })->where('is_active', false)->count();

        $newUsers = User::whereHas('role', function ($q) {
            $q->where('name', 'Customer');
        })->whereBetween('created_at', [$startDate, $endDate])->count();

        // Recent registrations
        $recentRegistrations = User::with('role')
            ->whereHas('role', function ($q) {
                $q->where('name', 'Customer');
            })
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Recently suspended users
        $recentSuspensions = User::with('role')
            ->whereHas('role', function ($q) {
                $q->where('name', 'Customer');
            })
            ->where('is_active', false)
            ->whereNotNull('suspended_at')
            ->orderBy('suspended_at', 'desc')
            ->limit(10)
            ->get();

        // User registration trends (daily for the past 30 days)
        $registrationTrends = User::whereHas('role', function ($q) {
                $q->where('name', 'Customer');
            })
            ->whereBetween('created_at', [now()->subDays(30), now()])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        return response()->json([
            'stats' => [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'suspended_users' => $suspendedUsers,
                'new_users' => $newUsers
            ],
            'recent_registrations' => $recentRegistrations,
            'recent_suspensions' => $recentSuspensions,
            'registration_trends' => $registrationTrends
        ]);
    }

    /**
     * Get user statistics.
     */
    private function getUserStats($userId)
    {
        $user = User::findOrFail($userId);

        // Order statistics
        $totalOrders = $user->orders()->count();
        $approvedOrders = $user->orders()->where('approval_status', 'approved')->count();
        $rejectedOrders = $user->orders()->where('approval_status', 'rejected')->count();
        $pendingOrders = $user->orders()->where('approval_status', 'pending')->count();

        // Spending statistics
        $totalSpent = $user->orders()
            ->where('approval_status', 'approved')
            ->where('payment_status', 'Paid')
            ->sum('total_amount');

        $averageOrderValue = $approvedOrders > 0 ? $totalSpent / $approvedOrders : 0;

        // Recent orders
        $recentOrders = $user->orders()
            ->with(['orderItems.product', 'paymentMethod'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Favorite products (most ordered)
        $favoriteProducts = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.user_id', $userId)
            ->where('orders.approval_status', 'approved')
            ->select(
                'products.id',
                'products.name',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('COUNT(DISTINCT orders.id) as order_count')
            )
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_quantity', 'desc')
            ->limit(5)
            ->get();

        return [
            'total_orders' => $totalOrders,
            'approved_orders' => $approvedOrders,
            'rejected_orders' => $rejectedOrders,
            'pending_orders' => $pendingOrders,
            'total_spent' => round($totalSpent, 2),
            'average_order_value' => round($averageOrderValue, 2),
            'recent_orders' => $recentOrders,
            'favorite_products' => $favoriteProducts
        ];
    }

    /**
     * Bulk suspend users.
     */
    public function bulkSuspend(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:1000'
        ]);

        $users = User::whereHas('role', function ($q) {
            $q->where('name', 'Customer');
        })->whereIn('id', $request->user_ids)->where('is_active', true)->get();

        $suspendedCount = 0;
        foreach ($users as $user) {
            $user->update([
                'is_active' => false,
                'suspension_reason' => $request->reason,
                'suspension_notes' => $request->notes,
                'suspended_at' => now(),
                'suspended_by' => auth()->id()
            ]);

            // Log the suspension
            DB::table('user_suspensions')->insert([
                'user_id' => $user->id,
                'suspended_by' => auth()->id(),
                'reason' => $request->reason,
                'notes' => $request->notes,
                'suspended_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $suspendedCount++;
        }

        return response()->json([
            'message' => "Successfully suspended {$suspendedCount} users",
            'suspended_count' => $suspendedCount
        ]);
    }

    /**
     * Bulk reactivate users.
     */
    public function bulkReactivate(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'notes' => 'nullable|string|max:1000'
        ]);

        $users = User::whereHas('role', function ($q) {
            $q->where('name', 'Customer');
        })->whereIn('id', $request->user_ids)->where('is_active', false)->get();

        $reactivatedCount = 0;
        foreach ($users as $user) {
            $user->update([
                'is_active' => true,
                'suspension_reason' => null,
                'suspension_notes' => null,
                'suspended_at' => null,
                'suspended_by' => null,
                'reactivated_at' => now(),
                'reactivated_by' => auth()->id(),
                'reactivation_notes' => $request->notes
            ]);

            // Log the reactivation
            DB::table('user_suspensions')->insert([
                'user_id' => $user->id,
                'reactivated_by' => auth()->id(),
                'action_type' => 'reactivated',
                'notes' => $request->notes,
                'reactivated_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $reactivatedCount++;
        }

        return response()->json([
            'message' => "Successfully reactivated {$reactivatedCount} users",
            'reactivated_count' => $reactivatedCount
        ]);
    }
}

