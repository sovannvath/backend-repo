<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    /**
     * Display a listing of transactions with filtering and search capabilities.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = Transaction::with(['order.user', 'order.orderItems.product', 'user']);
        
        // Filter by date range if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59'
            ]);
        }
        
        // Search by username
        if ($request->has('username')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->username . '%');
            });
        }
        
        // Search by user email
        if ($request->has('user_email')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('email', 'like', '%' . $request->user_email . '%');
            });
        }
        
        // Search by ticket number
        if ($request->has('ticket_number')) {
            $query->where('ticket_number', 'like', '%' . $request->ticket_number . '%');
        }
        
        // Filter by transaction type
        if ($request->has('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        $transactions = $query->orderBy('created_at', 'desc')
                             ->paginate($request->get('per_page', 15));
        
        return response()->json([
            'transactions' => $transactions
        ]);
    }

    /**
     * Display the specified transaction.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $transaction = Transaction::with(['order.user', 'order.orderItems.product', 'user'])
                                 ->findOrFail($id);
        
        return response()->json([
            'transaction' => $transaction
        ]);
    }

    /**
     * Create a new transaction for an order.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'amount' => 'required|numeric|min:0',
            'transaction_type' => 'required|in:Payment,Refund',
            'transaction_id' => 'nullable|string',
            'status' => 'required|in:Success,Failed,Pending',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $order = Order::findOrFail($request->order_id);
        
        $transaction = Transaction::create([
            'order_id' => $request->order_id,
            'user_id' => $order->user_id,
            'amount' => $request->amount,
            'transaction_type' => $request->transaction_type,
            'transaction_id' => $request->transaction_id,
            'ticket_number' => Transaction::generateTicketNumber(),
            'status' => $request->status,
        ]);

        return response()->json([
            'message' => 'Transaction created successfully',
            'transaction' => $transaction->fresh()->load(['order.user', 'order.orderItems.product', 'user'])
        ], 201);
    }

    /**
     * Update the specified transaction.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'sometimes|numeric|min:0',
            'transaction_type' => 'sometimes|in:Payment,Refund',
            'transaction_id' => 'nullable|string',
            'status' => 'sometimes|in:Success,Failed,Pending',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $transaction = Transaction::findOrFail($id);
        $transaction->update($request->only([
            'amount', 'transaction_type', 'transaction_id', 'status'
        ]));

        return response()->json([
            'message' => 'Transaction updated successfully',
            'transaction' => $transaction->fresh()->load(['order.user', 'order.orderItems.product', 'user'])
        ]);
    }

    /**
     * Get transaction summary statistics.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function summary(Request $request)
    {
        $query = Transaction::query();
        
        // Filter by date range if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59'
            ]);
        }
        
        $summary = [
            'total_transactions' => $query->count(),
            'successful_transactions' => $query->where('status', 'Success')->count(),
            'pending_transactions' => $query->where('status', 'Pending')->count(),
            'failed_transactions' => $query->where('status', 'Failed')->count(),
            'total_amount' => $query->where('status', 'Success')->sum('amount'),
            'payment_amount' => $query->where('status', 'Success')
                                    ->where('transaction_type', 'Payment')
                                    ->sum('amount'),
            'refund_amount' => $query->where('status', 'Success')
                                   ->where('transaction_type', 'Refund')
                                   ->sum('amount'),
        ];
        
        return response()->json([
            'summary' => $summary
        ]);
    }

    /**
     * Search transactions by ticket number.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchByTicket(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ticket_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $transaction = Transaction::with(['order.user', 'order.orderItems.product', 'user'])
                                 ->where('ticket_number', $request->ticket_number)
                                 ->first();

        if (!$transaction) {
            return response()->json([
                'message' => 'Transaction not found'
            ], 404);
        }

        return response()->json([
            'transaction' => $transaction
        ]);
    }
}

