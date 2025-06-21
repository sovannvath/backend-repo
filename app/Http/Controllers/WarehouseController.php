<?php

namespace App\Http\Controllers;

use App\Models\ReorderRequest;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WarehouseController extends Controller
{
    /**
     * Display a listing of pending reorder requests for warehouse staff.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function pendingReorders(Request $request)
    {
        $query = ReorderRequest::with(['product.category', 'admin'])
                              ->where('status', 'pending');
        
        // Filter by product name if provided
        if ($request->has('product_name')) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->product_name . '%');
            });
        }
        
        // Filter by date range if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59'
            ]);
        }
        
        $reorderRequests = $query->orderBy('created_at', 'desc')
                                ->paginate($request->get('per_page', 15));
        
        return response()->json([
            'reorder_requests' => $reorderRequests
        ]);
    }

    /**
     * Display the specified reorder request.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function showReorderRequest($id)
    {
        $reorderRequest = ReorderRequest::with(['product.category', 'admin', 'warehouseStaff'])
                                       ->findOrFail($id);
        
        return response()->json([
            'reorder_request' => $reorderRequest
        ]);
    }

    /**
     * Approve a reorder request by warehouse staff.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function approveReorder(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'quantity_approved' => 'required|integer|min:1',
            'warehouse_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $reorderRequest = ReorderRequest::findOrFail($id);
        
        if ($reorderRequest->status !== 'pending') {
            return response()->json([
                'message' => 'This reorder request has already been processed'
            ], 400);
        }

        $success = $reorderRequest->warehouseApprove(
            $request->user()->id,
            $request->quantity_approved,
            $request->warehouse_notes
        );

        if ($success) {
            // Notify admin about warehouse approval
            $reorderRequest->admin->notify(new \App\Notifications\ReorderApproved($reorderRequest));
            
            return response()->json([
                'message' => 'Reorder request approved successfully',
                'reorder_request' => $reorderRequest->fresh()->load(['product.category', 'admin', 'warehouseStaff'])
            ]);
        }

        return response()->json([
            'message' => 'Failed to approve reorder request'
        ], 500);
    }

    /**
     * Reject a reorder request by warehouse staff.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function rejectReorder(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'warehouse_notes' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $reorderRequest = ReorderRequest::findOrFail($id);
        
        if ($reorderRequest->status !== 'pending') {
            return response()->json([
                'message' => 'This reorder request has already been processed'
            ], 400);
        }

        $success = $reorderRequest->warehouseReject(
            $request->user()->id,
            $request->warehouse_notes
        );

        if ($success) {
            // Notify admin about warehouse rejection
            $reorderRequest->admin->notify(new \App\Notifications\ReorderRejected($reorderRequest));
            
            return response()->json([
                'message' => 'Reorder request rejected successfully',
                'reorder_request' => $reorderRequest->fresh()->load(['product.category', 'admin', 'warehouseStaff'])
            ]);
        }

        return response()->json([
            'message' => 'Failed to reject reorder request'
        ], 500);
    }

    /**
     * Get warehouse dashboard statistics.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function dashboard(Request $request)
    {
        $stats = [
            'pending_reorders' => ReorderRequest::where('status', 'pending')->count(),
            'approved_reorders' => ReorderRequest::where('status', 'approved')->count(),
            'completed_reorders' => ReorderRequest::where('status', 'completed')->count(),
            'rejected_reorders' => ReorderRequest::where('status', 'cancelled')->count(),
            'total_value_pending' => ReorderRequest::where('status', 'pending')->sum('estimated_cost'),
            'total_value_approved' => ReorderRequest::where('status', 'approved')->sum('estimated_cost'),
        ];

        // Recent activity
        $recentActivity = ReorderRequest::with(['product', 'admin', 'warehouseStaff'])
                                       ->whereIn('status', ['approved', 'cancelled'])
                                       ->orderBy('updated_at', 'desc')
                                       ->limit(10)
                                       ->get();

        return response()->json([
            'stats' => $stats,
            'recent_activity' => $recentActivity
        ]);
    }

    /**
     * Get all reorder requests handled by warehouse (for history).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reorderHistory(Request $request)
    {
        $query = ReorderRequest::with(['product.category', 'admin', 'warehouseStaff'])
                              ->whereIn('status', ['approved', 'completed', 'cancelled']);
        
        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by date range if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('updated_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59'
            ]);
        }
        
        $reorderRequests = $query->orderBy('updated_at', 'desc')
                                ->paginate($request->get('per_page', 15));
        
        return response()->json([
            'reorder_requests' => $reorderRequests
        ]);
    }
}

