<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Role;
use App\Models\RequestOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Display admin dashboard data.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminDashboard(Request $request)
    {
        // Get date range for filtering
        $startDate = $request->input('start_date', Carbon::now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());
        
        // Payment method filter
        $paymentMethodId = $request->input('payment_method_id');
        
        // Base query for orders
        $orderQuery = Order::whereBetween('created_at', [$startDate, $endDate]);
        
        if ($paymentMethodId) {
            $orderQuery->where('payment_method_id', $paymentMethodId);
        }
        
        // Total income (only approved orders)
        $totalIncome = (clone $orderQuery)->where('approval_status', 'approved')->sum('total_amount');
            
        // Orders count by status
        $ordersByStatus = (clone $orderQuery)
            ->select('order_status', DB::raw('count(*) as count'))
            ->groupBy('order_status')
            ->get();
            
        // Orders count by approval status
        $ordersByApprovalStatus = (clone $orderQuery)
            ->select('approval_status', DB::raw('count(*) as count'))
            ->groupBy('approval_status')
            ->get();
            
        // Income by payment method
        $incomeByPaymentMethod = Order::select('payment_method_id', DB::raw('SUM(total_amount) as total'), DB::raw('COUNT(*) as count'))
            ->with('paymentMethod')
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
            
        // Recent orders
        $recentOrders = Order::with(['user', 'orderItems.product', 'paymentMethod', 'staff'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
            
        // Low stock products
        $lowStockProducts = Product::whereRaw('quantity <= low_stock_threshold')
            ->get();
            
        // Pending request orders
        $pendingRequestOrders = RequestOrder::with(['product', 'requestedBy'])
            ->where('admin_approval_status', 'Pending')
            ->get();
            
        // Top selling products
        $topSellingProducts = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->where('orders.approval_status', 'approved')
            ->select(
                'products.id',
                'products.name',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(order_items.quantity * order_items.price) as total_revenue')
            )
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_quantity', 'desc')
            ->limit(5)
            ->get();
            
        // User statistics
        $userStats = [
            'total_customers' => User::whereHas('role', function($q) {
                $q->where('name', 'Customer');
            })->count(),
            'new_customers' => User::whereHas('role', function($q) {
                $q->where('name', 'Customer');
            })->whereBetween('created_at', [$startDate, $endDate])->count(),
            'total_staff' => User::whereHas('role', function($q) {
                $q->where('name', 'Staff');
            })->where('is_active', true)->count()
        ];

        // Staff performance
        $staffPerformance = Order::select('staff_id', DB::raw('COUNT(*) as total_processed'), 
                                        DB::raw('SUM(CASE WHEN approval_status = "approved" THEN 1 ELSE 0 END) as approved'),
                                        DB::raw('SUM(CASE WHEN approval_status = "rejected" THEN 1 ELSE 0 END) as rejected'),
                                        DB::raw('SUM(CASE WHEN approval_status = "approved" THEN total_amount ELSE 0 END) as revenue_generated'))
            ->with('staff:id,name,employee_id')
            ->whereNotNull('staff_id')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('staff_id')
            ->get()
            ->map(function ($item) {
                return [
                    'staff_id' => $item->staff_id,
                    'staff_name' => $item->staff->name ?? 'Unknown',
                    'employee_id' => $item->staff->employee_id ?? 'N/A',
                    'total_processed' => $item->total_processed,
                    'approved' => $item->approved,
                    'rejected' => $item->rejected,
                    'approval_rate' => $item->total_processed > 0 ? round(($item->approved / $item->total_processed) * 100, 2) : 0,
                    'revenue_generated' => round($item->revenue_generated, 2)
                ];
            });
        
        return response()->json([
            'total_income' => $totalIncome,
            'orders_by_status' => $ordersByStatus,
            'orders_by_approval_status' => $ordersByApprovalStatus,
            'income_by_payment_method' => $incomeByPaymentMethod,
            'recent_orders' => $recentOrders,
            'low_stock_products' => $lowStockProducts,
            'pending_request_orders' => $pendingRequestOrders,
            'top_selling_products' => $topSellingProducts,
            'user_stats' => $userStats,
            'staff_performance' => $staffPerformance,
            'payment_methods' => \App\Models\PaymentMethod::where('is_active', true)->get()
        ]);
    }

    /**
     * Get income analytics for charts
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getIncomeAnalytics(Request $request)
    {
        $period = $request->input('period', 'monthly'); // daily, weekly, monthly, yearly
        $startDate = $request->input('start_date', Carbon::now()->subDays(30));
        $endDate = $request->input('end_date', Carbon::now());

        $query = Order::where('payment_status', 'Paid')
            ->whereBetween('created_at', [$startDate, $endDate]);

        switch ($period) {
            case 'daily':
                $incomeData = $query->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('SUM(total_amount) as income'),
                    DB::raw('COUNT(*) as orders_count')
                )
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get();
                break;

            case 'weekly':
                $incomeData = $query->select(
                    DB::raw('YEARWEEK(created_at) as week'),
                    DB::raw('SUM(total_amount) as income'),
                    DB::raw('COUNT(*) as orders_count')
                )
                ->groupBy(DB::raw('YEARWEEK(created_at)'))
                ->orderBy('week')
                ->get();
                break;

            case 'monthly':
                $incomeData = $query->select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('SUM(total_amount) as income'),
                    DB::raw('COUNT(*) as orders_count')
                )
                ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('MONTH(created_at)'))
                ->orderBy('year')
                ->orderBy('month')
                ->get();
                break;

            case 'yearly':
                $incomeData = $query->select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('SUM(total_amount) as income'),
                    DB::raw('COUNT(*) as orders_count')
                )
                ->groupBy(DB::raw('YEAR(created_at)'))
                ->orderBy('year')
                ->get();
                break;

            default:
                $incomeData = collect();
        }

        return response()->json([
            'period' => $period,
            'data' => $incomeData,
            'total_income' => $incomeData->sum('income'),
            'total_orders' => $incomeData->sum('orders_count')
        ]);
    }

    /**
     * Get product order history for charts
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProductOrderHistory(Request $request)
    {
        $period = $request->input('period', 'monthly');
        $productId = $request->input('product_id');
        $categoryId = $request->input('category_id');
        $startDate = $request->input('start_date', Carbon::now()->subDays(30));
        $endDate = $request->input('end_date', Carbon::now());

        $query = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.payment_status', 'Paid')
            ->whereBetween('orders.created_at', [$startDate, $endDate]);

        if ($productId) {
            $query->where('products.id', $productId);
        }

        if ($categoryId) {
            $query->join('product_categories', 'products.id', '=', 'product_categories.product_id')
                  ->where('product_categories.category_id', $categoryId);
        }

        switch ($period) {
            case 'daily':
                $orderHistory = $query->select(
                    DB::raw('DATE(orders.created_at) as date'),
                    'products.name as product_name',
                    DB::raw('SUM(order_items.quantity) as total_quantity'),
                    DB::raw('SUM(order_items.quantity * order_items.price) as total_revenue')
                )
                ->groupBy(DB::raw('DATE(orders.created_at)'), 'products.id', 'products.name')
                ->orderBy('date')
                ->get();
                break;

            case 'monthly':
                $orderHistory = $query->select(
                    DB::raw('YEAR(orders.created_at) as year'),
                    DB::raw('MONTH(orders.created_at) as month'),
                    'products.name as product_name',
                    DB::raw('SUM(order_items.quantity) as total_quantity'),
                    DB::raw('SUM(order_items.quantity * order_items.price) as total_revenue')
                )
                ->groupBy(DB::raw('YEAR(orders.created_at)'), DB::raw('MONTH(orders.created_at)'), 'products.id', 'products.name')
                ->orderBy('year')
                ->orderBy('month')
                ->get();
                break;

            default:
                $orderHistory = collect();
        }

        return response()->json([
            'period' => $period,
            'data' => $orderHistory
        ]);
    }

    /**
     * Get category performance analytics
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCategoryAnalytics(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subDays(30));
        $endDate = $request->input('end_date', Carbon::now());

        $categoryPerformance = DB::table('categories')
            ->leftJoin('product_categories', 'categories.id', '=', 'product_categories.category_id')
            ->leftJoin('products', 'product_categories.product_id', '=', 'products.id')
            ->leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('orders', 'order_items.order_id', '=', 'orders.id')
            ->where(function($query) use ($startDate, $endDate) {
                $query->whereNull('orders.created_at')
                      ->orWhere(function($q) use ($startDate, $endDate) {
                          $q->where('orders.payment_status', 'Paid')
                            ->whereBetween('orders.created_at', [$startDate, $endDate]);
                      });
            })
            ->select(
                'categories.id',
                'categories.name',
                DB::raw('COUNT(DISTINCT products.id) as products_count'),
                DB::raw('COALESCE(SUM(order_items.quantity), 0) as total_quantity_sold'),
                DB::raw('COALESCE(SUM(order_items.quantity * order_items.price), 0) as total_revenue')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('total_revenue', 'desc')
            ->get();

        return response()->json([
            'category_performance' => $categoryPerformance
        ]);
    }

    /**
     * Get dashboard summary statistics
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDashboardSummary(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subDays(30));
        $endDate = $request->input('end_date', Carbon::now());

        // Current period stats
        $currentStats = [
            'total_revenue' => Order::where('payment_status', 'Paid')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('total_amount'),
            'total_orders' => Order::whereBetween('created_at', [$startDate, $endDate])->count(),
            'total_customers' => User::whereHas('role', function($q) {
                $q->where('name', 'Customer');
            })->whereBetween('created_at', [$startDate, $endDate])->count(),
            'total_products' => Product::count()
        ];

        // Previous period for comparison
        $previousStartDate = Carbon::parse($startDate)->subDays(Carbon::parse($endDate)->diffInDays(Carbon::parse($startDate)));
        $previousEndDate = Carbon::parse($startDate)->subDay();

        $previousStats = [
            'total_revenue' => Order::where('payment_status', 'Paid')
                ->whereBetween('created_at', [$previousStartDate, $previousEndDate])
                ->sum('total_amount'),
            'total_orders' => Order::whereBetween('created_at', [$previousStartDate, $previousEndDate])->count(),
            'total_customers' => User::whereHas('role', function($q) {
                $q->where('name', 'Customer');
            })->whereBetween('created_at', [$previousStartDate, $previousEndDate])->count()
        ];

        // Calculate percentage changes
        $changes = [
            'revenue_change' => $previousStats['total_revenue'] > 0 
                ? (($currentStats['total_revenue'] - $previousStats['total_revenue']) / $previousStats['total_revenue']) * 100 
                : 0,
            'orders_change' => $previousStats['total_orders'] > 0 
                ? (($currentStats['total_orders'] - $previousStats['total_orders']) / $previousStats['total_orders']) * 100 
                : 0,
            'customers_change' => $previousStats['total_customers'] > 0 
                ? (($currentStats['total_customers'] - $previousStats['total_customers']) / $previousStats['total_customers']) * 100 
                : 0
        ];

        return response()->json([
            'current_stats' => $currentStats,
            'previous_stats' => $previousStats,
            'changes' => $changes
        ]);
    }

    /**
     * Display warehouse manager dashboard data.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function warehouseDashboard(Request $request)
    {
        // Pending request orders that need warehouse approval
        $pendingApprovals = RequestOrder::with(['product', 'requestedBy'])
            ->where('admin_approval_status', 'Approved')
            ->where('warehouse_approval_status', 'Pending')
            ->get();
            
        // Low stock products
        $lowStockProducts = Product::whereRaw('quantity <= low_stock_threshold')
            ->get();
            
        // Recent approved request orders
        $recentApprovedRequests = RequestOrder::with(['product', 'requestedBy'])
            ->where('warehouse_approval_status', 'Approved')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();
            
        // Product inventory summary
        $inventorySummary = [
            'total_products' => Product::count(),
            'low_stock_count' => Product::whereRaw('quantity <= low_stock_threshold')->count(),
            'out_of_stock_count' => Product::where('quantity', 0)->count(),
        ];
        
        return response()->json([
            'pending_approvals' => $pendingApprovals,
            'low_stock_products' => $lowStockProducts,
            'recent_approved_requests' => $recentApprovedRequests,
            'inventory_summary' => $inventorySummary
        ]);
    }

    /**
     * Display staff dashboard data.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function staffDashboard(Request $request)
    {
        // Pending orders that need staff approval
        $pendingOrders = Order::with(['user', 'orderItems.product'])
            ->where('order_status', 'Pending')
            ->orderBy('created_at', 'asc')
            ->get();
            
        // Orders processed by this staff member
        $processedOrders = Order::with(['user', 'orderItems.product'])
            ->where('staff_id', $request->user()->id)
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();
            
        // Orders ready for delivery
        $readyForDelivery = Order::with(['user', 'orderItems.product'])
            ->where('order_status', 'Processing')
            ->orderBy('updated_at', 'asc')
            ->get();
            
        return response()->json([
            'pending_orders' => $pendingOrders,
            'processed_orders' => $processedOrders,
            'ready_for_delivery' => $readyForDelivery
        ]);
    }

    /**
     * Display customer dashboard data.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function customerDashboard(Request $request)
    {
        // Recent orders
        $recentOrders = Order::with(['orderItems.product', 'paymentMethod'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
            
        // Order statistics
        $orderStats = [
            'total_orders' => Order::where('user_id', $request->user()->id)->count(),
            'pending_orders' => Order::where('user_id', $request->user()->id)
                ->whereIn('order_status', ['Pending', 'Processing'])
                ->count(),
            'delivered_orders' => Order::where('user_id', $request->user()->id)
                ->where('order_status', 'Delivered')
                ->count()
        ];
        
        // Cart summary
        $cart = $request->user()->cart()->with('cartItems.product')->first();
        $cartSummary = [
            'items_count' => $cart ? $cart->cartItems->count() : 0,
            'total_amount' => $cart ? $cart->getTotalAmount() : 0
        ];
        
        return response()->json([
            'recent_orders' => $recentOrders,
            'order_stats' => $orderStats,
            'cart_summary' => $cartSummary
        ]);
    }
}
