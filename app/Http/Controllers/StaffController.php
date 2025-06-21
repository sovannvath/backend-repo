<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
    /**
     * Display a listing of staff members.
     */
    public function index(Request $request)
    {
        $query = User::with('role')
            ->whereHas('role', function ($q) {
                $q->where('name', 'Staff');
            });

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%")
                  ->orWhere('department', 'like', "%{$search}%");
            });
        }

        // Filter by department
        if ($request->has('department') && $request->department) {
            $query->where('department', $request->department);
        }

        // Filter by active status
        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', $request->is_active);
        }

        $staff = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'staff' => $staff,
            'departments' => User::whereHas('role', function ($q) {
                $q->where('name', 'Staff');
            })->distinct()->pluck('department')->filter()
        ]);
    }

    /**
     * Store a newly created staff member.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'department' => 'required|string|max:100',
            'employee_id' => 'required|string|max:50|unique:users',
            'hire_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get Staff role ID
        $staffRole = \App\Models\Role::where('name', 'Staff')->first();
        if (!$staffRole) {
            return response()->json([
                'message' => 'Staff role not found'
            ], 500);
        }

        $staff = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $staffRole->id,
            'phone' => $request->phone,
            'department' => $request->department,
            'employee_id' => $request->employee_id,
            'hire_date' => $request->hire_date,
            'is_active' => true,
        ]);

        $staff->load('role');

        return response()->json([
            'message' => 'Staff member created successfully',
            'staff' => $staff
        ], 201);
    }

    /**
     * Display the specified staff member.
     */
    public function show($id)
    {
        $staff = User::with(['role', 'processedOrders.user', 'processedOrders.paymentMethod'])
            ->whereHas('role', function ($q) {
                $q->where('name', 'Staff');
            })
            ->findOrFail($id);

        // Get staff statistics
        $stats = $this->getStaffStats($id);

        return response()->json([
            'staff' => $staff,
            'stats' => $stats
        ]);
    }

    /**
     * Update the specified staff member.
     */
    public function update(Request $request, $id)
    {
        $staff = User::whereHas('role', function ($q) {
            $q->where('name', 'Staff');
        })->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'department' => 'required|string|max:100',
            'employee_id' => 'required|string|max:50|unique:users,employee_id,' . $id,
            'hire_date' => 'required|date',
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only([
            'name', 'email', 'phone', 'department', 
            'employee_id', 'hire_date', 'is_active'
        ]);

        // Update password if provided
        if ($request->filled('password')) {
            $request->validate(['password' => 'string|min:8|confirmed']);
            $updateData['password'] = Hash::make($request->password);
        }

        $staff->update($updateData);
        $staff->load('role');

        return response()->json([
            'message' => 'Staff member updated successfully',
            'staff' => $staff
        ]);
    }

    /**
     * Remove the specified staff member.
     */
    public function destroy($id)
    {
        $staff = User::whereHas('role', function ($q) {
            $q->where('name', 'Staff');
        })->findOrFail($id);

        // Check if staff has processed orders
        $hasProcessedOrders = Order::where('staff_id', $id)->exists();
        
        if ($hasProcessedOrders) {
            // Deactivate instead of delete to maintain data integrity
            $staff->update(['is_active' => false]);
            
            return response()->json([
                'message' => 'Staff member deactivated successfully (has processed orders)'
            ]);
        }

        $staff->delete();

        return response()->json([
            'message' => 'Staff member deleted successfully'
        ]);
    }

    /**
     * Get staff dashboard data.
     */
    public function dashboard(Request $request)
    {
        $staffId = auth()->id();
        
        // Date range filter
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());
        
        // Payment method filter
        $paymentMethodId = $request->get('payment_method_id');

        $stats = $this->getStaffStats($staffId, $startDate, $endDate, $paymentMethodId);
        $recentOrders = $this->getRecentOrders($staffId, 10);
        $pendingOrders = $this->getPendingOrders($staffId, 5);

        return response()->json([
            'stats' => $stats,
            'recent_orders' => $recentOrders,
            'pending_orders' => $pendingOrders,
            'payment_methods' => \App\Models\PaymentMethod::where('is_active', true)->get()
        ]);
    }

    /**
     * Get orders for staff to review.
     */
    public function getOrdersToReview(Request $request)
    {
        $query = Order::with(['user', 'orderItems.product', 'paymentMethod', 'transactions'])
            ->where('approval_status', 'pending')
            ->orderBy('created_at', 'asc');

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%")
                               ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by payment method
        if ($request->has('payment_method_id') && $request->payment_method_id) {
            $query->where('payment_method_id', $request->payment_method_id);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $orders = $query->paginate(15);

        return response()->json([
            'orders' => $orders,
            'payment_methods' => \App\Models\PaymentMethod::where('is_active', true)->get()
        ]);
    }

    /**
     * Approve an order.
     */
    public function approveOrder(Request $request, $orderId)
    {
        $request->validate([
            'notes' => 'nullable|string|max:1000'
        ]);

        $order = Order::findOrFail($orderId);
        
        if (!$order->isPending()) {
            return response()->json([
                'message' => 'Order is not pending approval'
            ], 400);
        }

        $order->approve(auth()->id(), $request->notes);

        // Send notification to customer
        $order->user->notify(new \App\Notifications\OrderApproved($order, $request->notes));

        return response()->json([
            'message' => 'Order approved successfully',
            'order' => $order->load(['user', 'orderItems.product', 'paymentMethod'])
        ]);
    }

    /**
     * Reject an order.
     */
    public function rejectOrder(Request $request, $orderId)
    {
        $request->validate([
            'notes' => 'required|string|max:1000'
        ]);

        $order = Order::findOrFail($orderId);
        
        if (!$order->isPending()) {
            return response()->json([
                'message' => 'Order is not pending approval'
            ], 400);
        }

        $order->reject(auth()->id(), $request->notes);

        // Send notification to customer
        $order->user->notify(new \App\Notifications\OrderRejected($order, $request->notes));

        return response()->json([
            'message' => 'Order rejected successfully',
            'order' => $order->load(['user', 'orderItems.product', 'paymentMethod'])
        ]);
    }

    /**
     * Get staff statistics.
     */
    private function getStaffStats($staffId, $startDate = null, $endDate = null, $paymentMethodId = null)
    {
        $startDate = $startDate ?: now()->startOfMonth();
        $endDate = $endDate ?: now()->endOfMonth();

        $query = Order::where('staff_id', $staffId)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($paymentMethodId) {
            $query->where('payment_method_id', $paymentMethodId);
        }

        $totalApproved = (clone $query)->where('approval_status', 'approved')->count();
        $totalRejected = (clone $query)->where('approval_status', 'rejected')->count();
        $totalIncome = (clone $query)->where('approval_status', 'approved')->sum('total_amount');

        // Income by payment method
        $incomeByPaymentMethod = Order::select('payment_method_id', DB::raw('SUM(total_amount) as total'))
            ->with('paymentMethod')
            ->where('staff_id', $staffId)
            ->where('approval_status', 'approved')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('payment_method_id')
            ->get()
            ->map(function ($item) {
                return [
                    'payment_method' => $item->paymentMethod->name ?? 'Unknown',
                    'total_income' => round($item->total, 2)
                ];
            });

        // Recent rejections for details
        $recentRejections = Order::with(['user', 'paymentMethod'])
            ->where('staff_id', $staffId)
            ->where('approval_status', 'rejected')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('rejected_at', 'desc')
            ->limit(10)
            ->get();

        return [
            'total_approved' => $totalApproved,
            'total_rejected' => $totalRejected,
            'total_income' => round($totalIncome, 2),
            'income_by_payment_method' => $incomeByPaymentMethod,
            'recent_rejections' => $recentRejections,
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d')
            ]
        ];
    }

    /**
     * Get recent orders processed by staff.
     */
    private function getRecentOrders($staffId, $limit = 10)
    {
        return Order::with(['user', 'paymentMethod', 'orderItems.product'])
            ->where('staff_id', $staffId)
            ->whereIn('approval_status', ['approved', 'rejected'])
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get pending orders for staff.
     */
    private function getPendingOrders($staffId, $limit = 5)
    {
        return Order::with(['user', 'paymentMethod', 'orderItems.product'])
            ->where('approval_status', 'pending')
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get income analytics with payment method breakdown.
     */
    public function getIncomeAnalytics(Request $request)
    {
        $staffId = auth()->id();
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());
        $paymentMethodId = $request->get('payment_method_id');

        $query = Order::where('staff_id', $staffId)
            ->where('approval_status', 'approved')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($paymentMethodId) {
            $query->where('payment_method_id', $paymentMethodId);
        }

        // Daily income breakdown
        $dailyIncome = (clone $query)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total_amount) as total'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        // Payment method breakdown
        $paymentMethodBreakdown = Order::select('payment_method_id', DB::raw('SUM(total_amount) as total'), DB::raw('COUNT(*) as count'))
            ->with('paymentMethod')
            ->where('staff_id', $staffId)
            ->where('approval_status', 'approved')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('payment_method_id')
            ->get()
            ->map(function ($item) {
                return [
                    'payment_method_id' => $item->payment_method_id,
                    'payment_method_name' => $item->paymentMethod->name ?? 'Unknown',
                    'total_income' => round($item->total, 2),
                    'order_count' => $item->count
                ];
            });

        return response()->json([
            'daily_income' => $dailyIncome,
            'payment_method_breakdown' => $paymentMethodBreakdown,
            'total_income' => $query->sum('total_amount'),
            'total_orders' => $query->count(),
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }
}

