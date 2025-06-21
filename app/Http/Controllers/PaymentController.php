<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Transaction;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * Initiate payment for an order
     *
     * @param Request $request
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function initiatePayment(Request $request, $orderId)
    {
        $validator = Validator::make($request->all(), [
            'payment_method_id' => 'required|exists:payment_methods,id',
            'return_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $order = Order::with(['user', 'paymentMethod'])->findOrFail($orderId);

        // Check if user owns the order
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if order is already paid
        if ($order->payment_status === 'Paid') {
            return response()->json(['message' => 'Order is already paid'], 400);
        }

        $paymentMethod = PaymentMethod::findOrFail($request->payment_method_id);

        // Create transaction record
        $transaction = Transaction::create([
            'order_id' => $order->id,
            'user_id' => $request->user()->id,
            'payment_method_id' => $request->payment_method_id,
            'amount' => $order->total_amount,
            'transaction_id' => 'TXN-' . strtoupper(Str::random(12)),
            'status' => 'Pending',
            'gateway_response' => json_encode([
                'initiated_at' => now(),
                'payment_method' => $paymentMethod->name,
            ]),
        ]);

        // Simulate payment gateway response
        $paymentResponse = $this->simulatePaymentGateway($transaction, $paymentMethod);

        return response()->json([
            'message' => 'Payment initiated successfully',
            'transaction' => $transaction->fresh(),
            'payment_url' => $paymentResponse['payment_url'],
            'gateway_reference' => $paymentResponse['gateway_reference'],
        ]);
    }

    /**
     * Handle payment callback/webhook
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handlePaymentCallback(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|string',
            'status' => 'required|in:success,failed,cancelled',
            'gateway_reference' => 'nullable|string',
            'gateway_response' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $transaction = Transaction::where('transaction_id', $request->transaction_id)->firstOrFail();
        $order = $transaction->order;

        // Update transaction status
        $transaction->update([
            'status' => $request->status === 'success' ? 'Completed' : 'Failed',
            'gateway_reference' => $request->gateway_reference,
            'gateway_response' => json_encode(array_merge(
                json_decode($transaction->gateway_response, true) ?? [],
                $request->gateway_response ?? [],
                ['callback_received_at' => now()]
            )),
            'completed_at' => $request->status === 'success' ? now() : null,
        ]);

        // Update order payment status
        if ($request->status === 'success') {
            $order->update([
                'payment_status' => 'Paid',
                'order_status' => $order->order_status === 'Pending' ? 'Processing' : $order->order_status,
            ]);

            // Send order confirmation notification
            $order->user->notify(new \App\Notifications\OrderStatusChanged($order));
        } else {
            $order->update(['payment_status' => 'Failed']);
        }

        return response()->json([
            'message' => 'Payment callback processed successfully',
            'transaction' => $transaction->fresh(),
            'order' => $order->fresh(),
        ]);
    }

    /**
     * Get payment status for an order
     *
     * @param Request $request
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentStatus(Request $request, $orderId)
    {
        $order = Order::with(['transactions', 'paymentMethod'])->findOrFail($orderId);

        // Check if user owns the order
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $latestTransaction = $order->transactions()->latest()->first();

        return response()->json([
            'order_id' => $order->id,
            'payment_status' => $order->payment_status,
            'total_amount' => $order->total_amount,
            'payment_method' => $order->paymentMethod,
            'latest_transaction' => $latestTransaction,
            'all_transactions' => $order->transactions,
        ]);
    }

    /**
     * Retry payment for a failed order
     *
     * @param Request $request
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function retryPayment(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);

        // Check if user owns the order
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if payment can be retried
        if ($order->payment_status === 'Paid') {
            return response()->json(['message' => 'Order is already paid'], 400);
        }

        if (!in_array($order->payment_status, ['Failed', 'Pending'])) {
            return response()->json(['message' => 'Payment cannot be retried'], 400);
        }

        // Use the same payment method as the original order
        return $this->initiatePayment($request->merge([
            'payment_method_id' => $order->payment_method_id
        ]), $orderId);
    }

    /**
     * Simulate payment gateway for testing purposes
     *
     * @param Transaction $transaction
     * @param PaymentMethod $paymentMethod
     * @return array
     */
    private function simulatePaymentGateway($transaction, $paymentMethod)
    {
        $gatewayReference = 'GW-' . strtoupper(Str::random(10));
        
        // Simulate different payment methods
        $paymentUrl = match($paymentMethod->name) {
            'Credit Card' => "https://payment-gateway.example.com/card/{$transaction->transaction_id}",
            'PayPal' => "https://paypal.com/checkout/{$transaction->transaction_id}",
            'Bank Transfer' => "https://bank-gateway.example.com/transfer/{$transaction->transaction_id}",
            'Digital Wallet' => "https://wallet-gateway.example.com/pay/{$transaction->transaction_id}",
            default => "https://payment-gateway.example.com/pay/{$transaction->transaction_id}",
        };

        return [
            'payment_url' => $paymentUrl,
            'gateway_reference' => $gatewayReference,
            'expires_at' => now()->addMinutes(30),
        ];
    }

    /**
     * Process mock payment completion (for testing)
     *
     * @param Request $request
     * @param string $transactionId
     * @return \Illuminate\Http\JsonResponse
     */
    public function mockPaymentComplete(Request $request, $transactionId)
    {
        $validator = Validator::make($request->all(), [
            'success' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $status = $request->success ? 'success' : 'failed';
        $gatewayReference = 'MOCK-' . strtoupper(Str::random(8));

        return $this->handlePaymentCallback($request->merge([
            'transaction_id' => $transactionId,
            'status' => $status,
            'gateway_reference' => $gatewayReference,
            'gateway_response' => [
                'mock_payment' => true,
                'processed_at' => now(),
                'success' => $request->success,
            ],
        ]));
    }

    /**
     * Get available payment methods
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentMethods()
    {
        $paymentMethods = PaymentMethod::where('is_active', true)
            ->select('id', 'name', 'description', 'type', 'is_active')
            ->get();

        return response()->json([
            'payment_methods' => $paymentMethods
        ]);
    }

    /**
     * Get transaction history for user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTransactionHistory(Request $request)
    {
        $transactions = Transaction::with(['order', 'paymentMethod'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($transactions);
    }
}

