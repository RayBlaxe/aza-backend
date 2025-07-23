<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected $midtransService;

    public function __construct(MidtransService $midtransService)
    {
        $this->midtransService = $midtransService;
    }

    public function handleNotification(Request $request): JsonResponse
    {
        try {
            $notification = $this->midtransService->handleNotification();
            $orderId = $notification['order_id'];
            $status = $notification['status'];

            $order = Order::where('order_number', $orderId)->first();

            if (! $order) {
                Log::error("Order not found for notification: {$orderId}");

                return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
            }

            if ($order->payment_status === Order::PAYMENT_STATUS_PAID) {
                return response()->json(['success' => true, 'message' => 'Payment already processed.']);
            }

            $signatureKey = hash('sha512', $orderId.$notification['status_code'].$order->total_amount.config('midtrans.server_key'));

            if ($signatureKey !== $notification['signature_key']) {
                Log::warning("Invalid signature for order {$orderId}");

                return response()->json(['success' => false, 'message' => 'Invalid signature.'], 403);
            }

            switch ($status) {
                case 'paid':
                    $order->update([
                        'payment_status' => Order::PAYMENT_STATUS_PAID,
                        'status' => Order::STATUS_PROCESSING,
                        'payment_details' => $notification['raw_notification'],
                    ]);
                    // Log::info("Order {$orderId} has been paid.");
                    break;
                case 'pending':
                    $order->update([
                        'payment_status' => Order::PAYMENT_STATUS_PENDING,
                        'payment_details' => $notification['raw_notification'],
                    ]);
                    break;
                case 'failed':
                case 'expired':
                case 'cancelled':
                    $order->update([
                        'payment_status' => Order::PAYMENT_STATUS_FAILED,
                        'status' => Order::STATUS_CANCELLED,
                        'payment_details' => $notification['raw_notification'],
                    ]);
                    $order->restoreStock();
                    // Log::info("Order {$orderId} has failed or expired.");
                    break;
            }

            return response()->json(['success' => true, 'message' => 'Notification handled.']);

        } catch (\Exception $e) {
            Log::error('Midtrans Notification Error: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to handle notification.'], 500);
        }
    }

    public function getPaymentStatus(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_number' => $order->order_number,
                'payment_status' => $order->payment_status,
                'status' => $order->status,
            ],
        ]);
    }
}
