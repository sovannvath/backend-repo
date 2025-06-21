<?php

namespace App\Http\Controllers;

use App\Models\RequestOrder;
use App\Models\Product;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RequestOrderController extends Controller
{
    /**
     * Display a listing of the request orders.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = RequestOrder::with(['product', 'requestedBy']);
        
        // Filter based on user role
        if ($request->user()->isAdmin()) {
            // Admins can see all request orders
        } elseif ($request->user()->isWarehouseManager()) {
            // Warehouse managers see orders that need their approval
            $query->where('admin_approval_status', 'Approved')
                  ->where(function($q) {
                      $q->where('warehouse_approval_status', 'Pending')
                        ->orWhere('warehouse_approval_status', 'Approved')
                        ->orWhere('warehouse_approval_status', 'Rejected');
                  });
        } else {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        
        $requestOrders = $query->orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'request_orders' => $requestOrders
        ]);
    }

    /**
     * Create a new request order.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'admin_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $requestOrder = RequestOrder::create([
            'product_id' => $request->product_id,
            'quantity' => $request->quantity,
            'requested_by' => $request->user()->id,
            'status' => 'Pending',
            'admin_approval_status' => 'Pending',
            'warehouse_approval_status' => 'Pending',
            'admin_notes' => $request->admin_notes,
        ]);

        // Notify other admins about the new request order
        $adminRole = Role::where('name', 'Admin')->first();
        if ($adminRole) {
            $admins = User::where('role_id', $adminRole->id)
                          ->where('id', '!=', $request->user()->id)
                          ->get();
            
            foreach ($admins as $admin) {
                $admin->notify(new \App\Notifications\NewRequestOrderNotification($requestOrder));
            }
        }

        return response()->json([
            'message' => 'Request order created successfully',
            'request_order' => $requestOrder->fresh()->load(['product', 'requestedBy'])
        ], 201);
    }

    /**
     * Display the specified request order.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $requestOrder = RequestOrder::with(['product', 'requestedBy'])->findOrFail($id);
        
        return response()->json([
            'request_order' => $requestOrder
        ]);
    }

    /**
     * Update admin approval status.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminApproval(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'admin_approval_status' => 'required|in:Approved,Rejected',
            'admin_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $requestOrder = RequestOrder::findOrFail($id);
        $requestOrder->admin_approval_status = $request->admin_approval_status;
        
        if ($request->has('admin_notes')) {
            $requestOrder->admin_notes = $request->admin_notes;
        }
        
        // Update overall status based on admin approval
        if ($request->admin_approval_status === 'Rejected') {
            $requestOrder->status = 'Rejected';
        }
        
        $requestOrder->save();

        // Notify warehouse managers if approved
        if ($request->admin_approval_status === 'Approved') {
            $warehouseRole = Role::where('name', 'Warehouse Manager')->first();
            if ($warehouseRole) {
                $warehouseManagers = User::where('role_id', $warehouseRole->id)->get();
                
                foreach ($warehouseManagers as $manager) {
                    $manager->notify(new \App\Notifications\RequestOrderApprovalNotification($requestOrder));
                }
            }
        }

        return response()->json([
            'message' => 'Admin approval status updated successfully',
            'request_order' => $requestOrder->fresh()->load(['product', 'requestedBy'])
        ]);
    }

    /**
     * Update warehouse approval status.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function warehouseApproval(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'warehouse_approval_status' => 'required|in:Approved,Rejected',
            'warehouse_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $requestOrder = RequestOrder::findOrFail($id);
        
        // Ensure admin has approved first
        if ($requestOrder->admin_approval_status !== 'Approved') {
            return response()->json([
                'message' => 'Admin approval is required before warehouse approval'
            ], 400);
        }
        
        $requestOrder->warehouse_approval_status = $request->warehouse_approval_status;
        
        if ($request->has('warehouse_notes')) {
            $requestOrder->warehouse_notes = $request->warehouse_notes;
        }
        
        // Update overall status based on warehouse approval
        if ($request->warehouse_approval_status === 'Approved') {
            $requestOrder->status = 'Approved';
            
            // Update product quantity if approved
            $product = Product::findOrFail($requestOrder->product_id);
            $product->quantity += $requestOrder->quantity;
            $product->save();
        } else {
            $requestOrder->status = 'Rejected';
        }
        
        $requestOrder->save();

        // Notify the admin who requested the order
        $requestOrder->requestedBy->notify(new \App\Notifications\WarehouseApprovalNotification($requestOrder));

        return response()->json([
            'message' => 'Warehouse approval status updated successfully',
            'request_order' => $requestOrder->fresh()->load(['product', 'requestedBy'])
        ]);
    }
}
