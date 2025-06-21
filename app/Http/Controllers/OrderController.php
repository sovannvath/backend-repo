<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * Display a listing of the orders.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = Order::with(['orderItems.product', 'paymentMethod', 'user']);
        
        // Filter by date range if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }
        
        // Filter by order number if provided
        if ($request->has('order_number')) {
            $query->where('order_number', 'like', '%' . $request->order_number . '%');
        }
        
        // For customers, only show their own orders
        if ($request->user()->isCustomer()) {
            $query->where('user_id', $request->user()->id);
        }
        
        $orders = $query->orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'orders' => $orders
        ]);
    }

    /**
     * Create a new order from the user's cart.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_method_id' => 'required|exists:payment_methods,id',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $cart = $request->user()->cart()->with('cartItems.product')->first();
        
        if (!$cart || $cart->cartItems->isEmpty()) {
            return response()->json([
                'message' => 'Cart is empty'
            ], 400);
        }

        // Check stock availability for all items
        foreach ($cart->cartItems as $item) {
            if ($item->quantity > $item->product->quantity) {
                return response()->json([
                    'message' => "Not enough stock for {$item->product->name}"
                ], 400);
            }
        }

        // Create order
        $order = Order::create([
            'user_id' => $request->user()->id,
            'order_number' => 'ORD-' . strtoupper(Str::random(10)),
            'total_amount' => $cart->getTotalAmount(),
            'payment_method_id' => $request->payment_method_id,
            'payment_status' => 'Pending',
            'order_status' => 'Pending',
            'notes' => $request->notes,
        ]);

        // Create order items and reduce product stock
        foreach ($cart->cartItems as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => $item->product->price,
            ]);

            // Reduce product stock
            $product = $item->product;
            $product->quantity -= $item->quantity;
            $product->save();
            
            // Check if product is now low on stock
            if ($product->isLowStock()) {
                // Assuming you have a ProductController with this method
                app(ProductController::class)->sendLowStockNotification($product);
                $product->checkAndCreateAlerts();
            }
        }

        // Create transaction record with ticket number
        $transaction = \App\Models\Transaction::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'amount' => $order->total_amount,
            'transaction_type' => 'Payment',
            'ticket_number' => \App\Models\Transaction::generateTicketNumber(),
            'status' => 'Pending',
        ]);

        // Clear the cart
        CartItem::where('cart_id', $cart->id)->delete();

        return response()->json([
            'message' => 'Order created successfully',
            'order' => $order->fresh()->load(['orderItems.product', 'paymentMethod', 'user']),
            'transaction' => $transaction->fresh(),
            'ticket_number' => $transaction->ticket_number
        ], 201);
    }

    /**
     * Display the specified order.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        $order = Order::with(['orderItems.product', 'paymentMethod', 'user'])->findOrFail($id);
        
        // Ensure customers can only view their own orders
        if ($request->user()->isCustomer() && $order->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        
        return response()->json([
            'order' => $order
        ]);
    }

    /**
     * Update order status by staff.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'order_status' => 'required|in:Pending,Approved,Rejected,Processing,Shipped,Delivered,Cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $order = Order::findOrFail($id);
        $order->order_status = $request->order_status;
        $order->staff_id = $request->user()->id;
        $order->save();

        // Notify customer about order status change
        $order->user->notify(new \App\Notifications\OrderStatusChanged($order));

        return response()->json([
            'message' => 'Order status updated successfully',
            'order' => $order->fresh()->load(['orderItems.product', 'paymentMethod', 'user', 'staff'])
        ]);
    }

    /**
     * Update payment status.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePaymentStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'payment_status' => 'required|in:Pending,Paid,Failed,Refunded',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $order = Order::findOrFail($id);
        $order->payment_status = $request->payment_status;
        $order->save();

        return response()->json([
            'message' => 'Payment status updated successfully',
            'order' => $order->fresh()->load(['orderItems.product', 'paymentMethod', 'user'])
        ]);
    }

    /**
     * Get all payment methods.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentMethods()
    {
        $paymentMethods = PaymentMethod::where('is_active', true)->get();
        
        return response()->json([
            'payment_methods' => $paymentMethods
        ]);
    }

    /**
     * Get order history for customer
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function history(Request $request)
    {
        $orders = Order::with(['orderItems.product', 'paymentMethod'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($orders);
    }

    /**
     * Cancel an order
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancel(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        // Check if user owns the order or is admin
        if ($request->user()->isCustomer() && $order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if order can be cancelled
        if (in_array($order->order_status, ['Shipped', 'Delivered', 'Cancelled'])) {
            return response()->json(['message' => 'Order cannot be cancelled'], 400);
        }

        // Restore product stock
        foreach ($order->orderItems as $item) {
            $product = $item->product;
            $product->quantity += $item->quantity;
            $product->save();
        }

        $order->update(['order_status' => 'Cancelled']);

        return response()->json([
            'message' => 'Order cancelled successfully',
            'order' => $order->fresh()->load(['orderItems.product', 'paymentMethod'])
        ]);
    }

    /**
     * Request order return
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function return(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $order = Order::findOrFail($id);

        // Check if user owns the order
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if order is delivered
        if ($order->order_status !== 'Delivered') {
            return response()->json(['message' => 'Only delivered orders can be returned'], 400);
        }

        $order->update([
            'order_status' => 'Return Requested',
            'return_reason' => $request->reason
        ]);

        return response()->json([
            'message' => 'Return request submitted successfully',
            'order' => $order->fresh()->load(['orderItems.product', 'paymentMethod'])
        ]);
    }

    /**
     * Track order status
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function track(Request $request, $id)
    {
        $order = Order::with(['orderItems.product', 'paymentMethod'])->findOrFail($id);

        // Check if user owns the order
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $trackingInfo = [
            'order_number' => $order->order_number,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
            'estimated_delivery' => $order->estimated_delivery_date,
            'tracking_number' => $order->tracking_number ?? null,
        ];

        return response()->json([
            'tracking_info' => $trackingInfo,
            'order' => $order
        ]);
    }
}