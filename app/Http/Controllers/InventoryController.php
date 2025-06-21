<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ReorderRequest;
use App\Models\InventoryAlert;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /**
     * Get inventory dashboard data.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function dashboard(Request $request)
    {
        $lowStockProducts = Product::lowStock()->with(['category', 'brand'])->get();
        $outOfStockProducts = Product::outOfStock()->with(['category', 'brand'])->get();
        $reorderNeededProducts = Product::needsReordering()->with(['category', 'brand'])->get();
        
        $unresolvedAlerts = InventoryAlert::with('product')
                                         ->where('is_resolved', false)
                                         ->orderBy('created_at', 'desc')
                                         ->limit(10)
                                         ->get();

        $pendingReorders = ReorderRequest::with(['product', 'admin'])
                                        ->where('status', 'pending')
                                        ->orderBy('created_at', 'desc')
                                        ->limit(10)
                                        ->get();

        $stats = [
            'total_products' => Product::count(),
            'low_stock_count' => $lowStockProducts->count(),
            'out_of_stock_count' => $outOfStockProducts->count(),
            'reorder_needed_count' => $reorderNeededProducts->count(),
            'unresolved_alerts_count' => $unresolvedAlerts->count(),
            'pending_reorders_count' => $pendingReorders->count(),
            'total_inventory_value' => Product::selectRaw('SUM(price * quantity) as total')->value('total') ?? 0,
        ];

        return response()->json([
            'stats' => $stats,
            'low_stock_products' => $lowStockProducts,
            'out_of_stock_products' => $outOfStockProducts,
            'reorder_needed_products' => $reorderNeededProducts,
            'unresolved_alerts' => $unresolvedAlerts,
            'pending_reorders' => $pendingReorders,
        ]);
    }

    /**
     * Get all inventory alerts.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAlerts(Request $request)
    {
        $query = InventoryAlert::with('product');

        // Filter by alert type
        if ($request->has('alert_type') && $request->alert_type) {
            $query->where('alert_type', $request->alert_type);
        }

        // Filter by resolved status
        if ($request->has('is_resolved') && $request->is_resolved !== '') {
            $query->where('is_resolved', $request->is_resolved);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $alerts = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'alerts' => $alerts
        ]);
    }

    /**
     * Resolve an inventory alert.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function resolveAlert(Request $request, $id)
    {
        $alert = InventoryAlert::findOrFail($id);
        $alert->resolve();

        return response()->json([
            'message' => 'Alert resolved successfully',
            'alert' => $alert->fresh()
        ]);
    }

    /**
     * Get all reorder requests.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getReorderRequests(Request $request)
    {
        $query = ReorderRequest::with(['product', 'admin']);

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $reorderRequests = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'reorder_requests' => $reorderRequests
        ]);
    }

    /**
     * Create a new reorder request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createReorderRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity_requested' => 'required|integer|min:1',
            'estimated_cost' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $reorderRequest = ReorderRequest::create([
            'product_id' => $request->product_id,
            'admin_id' => $request->user()->id,
            'quantity_requested' => $request->quantity_requested,
            'estimated_cost' => $request->estimated_cost,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'message' => 'Reorder request created successfully',
            'reorder_request' => $reorderRequest->fresh()->load(['product', 'admin'])
        ], 201);
    }

    /**
     * Approve a reorder request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function approveReorderRequest(Request $request, $id)
    {
        $reorderRequest = ReorderRequest::findOrFail($id);
        
        if (!$reorderRequest->isPending()) {
            return response()->json([
                'message' => 'Reorder request is not pending'
            ], 400);
        }

        $reorderRequest->approve();

        return response()->json([
            'message' => 'Reorder request approved successfully',
            'reorder_request' => $reorderRequest->fresh()->load(['product', 'admin'])
        ]);
    }

    /**
     * Complete a reorder request and update inventory.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function completeReorderRequest(Request $request, $id)
    {
        $reorderRequest = ReorderRequest::findOrFail($id);
        
        if (!$reorderRequest->isApproved()) {
            return response()->json([
                'message' => 'Reorder request must be approved before completion'
            ], 400);
        }

        $reorderRequest->complete();

        // Send notification to admin about successful reorder
        $admins = User::whereHas('role', function ($q) {
            $q->where('name', 'Admin');
        })->get();

        foreach ($admins as $admin) {
            $admin->notify(new \App\Notifications\ReorderCompleted($reorderRequest));
        }

        return response()->json([
            'message' => 'Reorder request completed successfully and inventory updated',
            'reorder_request' => $reorderRequest->fresh()->load(['product', 'admin'])
        ]);
    }

    /**
     * Cancel a reorder request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelReorderRequest(Request $request, $id)
    {
        $reorderRequest = ReorderRequest::findOrFail($id);
        $reorderRequest->cancel();

        return response()->json([
            'message' => 'Reorder request cancelled successfully',
            'reorder_request' => $reorderRequest->fresh()->load(['product', 'admin'])
        ]);
    }

    /**
     * Update product inventory settings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProductInventorySettings(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'low_stock_threshold' => 'required|integer|min:0',
            'reorder_quantity' => 'required|integer|min:1',
            'auto_reorder' => 'required|boolean',
            'reorder_cost' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $product = Product::findOrFail($id);
        $product->update($request->only([
            'low_stock_threshold',
            'reorder_quantity', 
            'auto_reorder',
            'reorder_cost'
        ]));

        // Check if alerts need to be created after updating thresholds
        $product->checkAndCreateAlerts();

        return response()->json([
            'message' => 'Product inventory settings updated successfully',
            'product' => $product->fresh()
        ]);
    }

    /**
     * Manually adjust product stock.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function adjustStock(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'adjustment_type' => 'required|in:increase,decrease,set',
            'quantity' => 'required|integer|min:0',
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $product = Product::findOrFail($id);
        $oldQuantity = $product->quantity;

        switch ($request->adjustment_type) {
            case 'increase':
                $product->quantity += $request->quantity;
                break;
            case 'decrease':
                $product->quantity = max(0, $product->quantity - $request->quantity);
                break;
            case 'set':
                $product->quantity = $request->quantity;
                break;
        }

        $product->save();

        // Check if alerts need to be created after stock adjustment
        $product->checkAndCreateAlerts();

        // Log the stock adjustment (you might want to create a StockAdjustment model for this)
        \Log::info("Stock adjustment for product {$product->name} (ID: {$product->id})", [
            'old_quantity' => $oldQuantity,
            'new_quantity' => $product->quantity,
            'adjustment_type' => $request->adjustment_type,
            'adjustment_quantity' => $request->quantity,
            'reason' => $request->reason,
            'admin_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Stock adjusted successfully',
            'product' => $product->fresh(),
            'old_quantity' => $oldQuantity,
            'new_quantity' => $product->quantity
        ]);
    }

    /**
     * Get low stock products.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLowStockProducts(Request $request)
    {
        $products = Product::lowStock()
                          ->with(['category', 'brand'])
                          ->orderBy('quantity', 'asc')
                          ->paginate(15);

        return response()->json([
            'products' => $products
        ]);
    }

    /**
     * Send low stock notifications to admins.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendLowStockNotifications(Request $request)
    {
        $lowStockProducts = Product::lowStock()->get();
        
        if ($lowStockProducts->isEmpty()) {
            return response()->json([
                'message' => 'No low stock products found'
            ]);
        }

        $admins = User::whereHas('role', function ($q) {
            $q->where('name', 'Admin');
        })->get();

        foreach ($lowStockProducts as $product) {
            foreach ($admins as $admin) {
                $admin->notify(new \App\Notifications\LowStockAlert($product));
            }
        }

        return response()->json([
            'message' => 'Low stock notifications sent successfully',
            'products_count' => $lowStockProducts->count(),
            'admins_notified' => $admins->count()
        ]);
    }
}

