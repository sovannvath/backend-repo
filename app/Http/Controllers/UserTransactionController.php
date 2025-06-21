<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class UserTransactionController extends Controller
{
    /**
     * Display a listing of the authenticated user's transactions with filtering.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Transaction::with(['order.orderItems.product', 'order.paymentMethod'])
                           ->where('user_id', $user->id);
        
        // Filter by date range if provided (Cambodia timezone)
        if ($request->has('start_date') && $request->start_date) {
            $startDate = Carbon::createFromFormat('Y-m-d', $request->start_date, 'Asia/Phnom_Penh')
                              ->startOfDay()
                              ->utc();
            $query->where('created_at', '>=', $startDate);
        }
        
        if ($request->has('end_date') && $request->end_date) {
            $endDate = Carbon::createFromFormat('Y-m-d', $request->end_date, 'Asia/Phnom_Penh')
                            ->endOfDay()
                            ->utc();
            $query->where('created_at', '<=', $endDate);
        }
        
        // Search by ticket number
        if ($request->has('ticket_number') && $request->ticket_number) {
            $query->where('ticket_number', 'like', '%' . $request->ticket_number . '%');
        }
        
        // Filter by transaction type
        if ($request->has('transaction_type') && $request->transaction_type) {
            $query->where('transaction_type', $request->transaction_type);
        }
        
        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }
        
        $transactions = $query->orderBy('created_at', 'desc')
                             ->paginate($request->get('per_page', 15));
        
        // Convert timestamps to Cambodia timezone for display
        $transactions->getCollection()->transform(function ($transaction) {
            $transaction->created_at_cambodia = $transaction->created_at->setTimezone('Asia/Phnom_Penh');
            $transaction->updated_at_cambodia = $transaction->updated_at->setTimezone('Asia/Phnom_Penh');
            return $transaction;
        });
        
        return response()->json([
            'transactions' => $transactions
        ]);
    }

    /**
     * Display the specified transaction for the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $transaction = Transaction::with(['order.orderItems.product', 'order.paymentMethod'])
                                 ->where('user_id', $user->id)
                                 ->findOrFail($id);
        
        // Convert timestamps to Cambodia timezone for display
        $transaction->created_at_cambodia = $transaction->created_at->setTimezone('Asia/Phnom_Penh');
        $transaction->updated_at_cambodia = $transaction->updated_at->setTimezone('Asia/Phnom_Penh');
        
        return response()->json([
            'transaction' => $transaction
        ]);
    }

    /**
     * Get transaction summary statistics for the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function summary(Request $request)
    {
        $user = $request->user();
        $query = Transaction::where('user_id', $user->id);
        
        // Filter by date range if provided (Cambodia timezone)
        if ($request->has('start_date') && $request->start_date) {
            $startDate = Carbon::createFromFormat('Y-m-d', $request->start_date, 'Asia/Phnom_Penh')
                              ->startOfDay()
                              ->utc();
            $query->where('created_at', '>=', $startDate);
        }
        
        if ($request->has('end_date') && $request->end_date) {
            $endDate = Carbon::createFromFormat('Y-m-d', $request->end_date, 'Asia/Phnom_Penh')
                            ->endOfDay()
                            ->utc();
            $query->where('created_at', '<=', $endDate);
        }
        
        $summary = [
            'total_transactions' => $query->count(),
            'successful_transactions' => $query->where('status', 'Success')->count(),
            'pending_transactions' => $query->where('status', 'Pending')->count(),
            'failed_transactions' => $query->where('status', 'Failed')->count(),
            'total_spent' => $query->where('status', 'Success')
                                  ->where('transaction_type', 'Payment')
                                  ->sum('amount'),
            'total_refunded' => $query->where('status', 'Success')
                                    ->where('transaction_type', 'Refund')
                                    ->sum('amount'),
        ];
        
        return response()->json([
            'summary' => $summary
        ]);
    }

    /**
     * Search user's transactions by ticket number.
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

        $user = $request->user();
        $transaction = Transaction::with(['order.orderItems.product', 'order.paymentMethod'])
                                 ->where('user_id', $user->id)
                                 ->where('ticket_number', $request->ticket_number)
                                 ->first();

        if (!$transaction) {
            return response()->json([
                'message' => 'Transaction not found'
            ], 404);
        }

        // Convert timestamps to Cambodia timezone for display
        $transaction->created_at_cambodia = $transaction->created_at->setTimezone('Asia/Phnom_Penh');
        $transaction->updated_at_cambodia = $transaction->updated_at->setTimezone('Asia/Phnom_Penh');

        return response()->json([
            'transaction' => $transaction
        ]);
    }
}

