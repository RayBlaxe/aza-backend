<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Services\MidtransService;
use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    protected $midtransService;
    protected $shippingService;

    public function __construct(MidtransService $midtransService, ShippingService $shippingService)
    {
        $this->midtransService = $midtransService;
        $this->shippingService = $shippingService;
    }

    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()
            ->orders()
            ->with(['orderItems.product'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => OrderResource::collection($orders),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'total_pages' => $orders->lastPage(),
                'total_items' => $orders->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'shipping_address' => 'required|array',
            'shipping_address.name' => 'required|string',
            'shipping_address.phone' => 'required|string',
            'shipping_address.address' => 'required|string',
            'shipping_address.city' => 'required|string',
            'shipping_address.state' => 'required|string',
            'shipping_address.postal_code' => 'required|string',
            'courier_service' => 'string|in:regular,express,same_day',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $cart = $user->cart;

        if (! $cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart not found',
            ], 400);
        }

        // Load cart items with products
        $cart->load('items.product');

        if ($cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Cart is empty',
            ], 400);
        }

        DB::beginTransaction();
        try {
            $subtotal = 0;
            $orderItems = [];

            foreach ($cart->items as $cartItem) { // <-- Dan juga di sini: $cart->items
                if ($cartItem->product->stock < $cartItem->quantity) {
                    throw new \Exception("Insufficient stock for product: {$cartItem->product->name}");
                }

                $itemTotal = $cartItem->product->price * $cartItem->quantity;
                $subtotal += $itemTotal;

                $orderItems[] = [
                    'product_id' => $cartItem->product_id,
                    'product_name' => $cartItem->product->name,
                    'product_sku' => $cartItem->product->sku,
                    'quantity' => $cartItem->quantity,
                    'price' => $cartItem->product->price,
                    'total' => $itemTotal,
                ];

                $cartItem->product->decrement('stock', $cartItem->quantity);
            }

            // Calculate dynamic shipping cost
            $courierService = $request->courier_service ?? 'regular';
            $totalWeight = $this->shippingService->calculateCartWeight($cart->items);
            
            // Use postal code if available, otherwise use city
            if (isset($request->shipping_address['postal_code']) && !empty($request->shipping_address['postal_code'])) {
                $shippingCalculation = $this->shippingService->calculateShippingByPostalCode(
                    $request->shipping_address['postal_code'],
                    $totalWeight,
                    $courierService
                );
            } else {
                $shippingCalculation = $this->shippingService->calculateShippingCost(
                    $request->shipping_address['city'],
                    $totalWeight,
                    $courierService
                );
            }
            $shippingCost = $shippingCalculation['total_cost'];
            $totalAmount = $subtotal + $shippingCost;

            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => Order::generateOrderNumber(),
                'status' => Order::STATUS_PENDING,
                'payment_status' => Order::PAYMENT_STATUS_PENDING,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'courier_service' => $courierService,
                'total_amount' => $totalAmount,
                'shipping_address' => $request->shipping_address,
                'notes' => $request->notes,
            ]);

            foreach ($orderItems as $itemData) {
                $order->orderItems()->create($itemData);
            }

            $cart->items()->delete();

            DB::commit();

            $order->load(['orderItems', 'user']);

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => new OrderResource($order),
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: '.$e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $order->load(['orderItems', 'user']);

        return response()->json([
            'success' => true,
            'data' => new OrderResource($order),
        ]);
    }

    public function createPayment(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        if ($order->payment_status === Order::PAYMENT_STATUS_PAID) {
            return response()->json([
                'success' => false,
                'message' => 'Order already paid',
            ], 400);
        }

        if ($order->payment_status === Order::PAYMENT_STATUS_FAILED) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot create payment for failed order',
            ], 400);
        }

        $order->load(['orderItems', 'user']);

        $paymentResult = $this->midtransService->createPaymentToken($order);

        if (! $paymentResult['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment token',
                'error' => $paymentResult['error'],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'snap_token' => $paymentResult['snap_token'],
                'redirect_url' => $paymentResult['redirect_url'],
                'order' => new OrderResource($order),
            ],
        ]);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:'.implode(',', [
                Order::STATUS_PENDING,
                Order::STATUS_PROCESSING,
                Order::STATUS_SHIPPED,
                Order::STATUS_DELIVERED,
                Order::STATUS_CANCELLED,
            ]),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $newStatus = $request->status;

        // Business logic: Only certain status transitions are allowed
        $allowedTransitions = [
            Order::STATUS_PENDING => [Order::STATUS_PROCESSING, Order::STATUS_CANCELLED],
            Order::STATUS_PROCESSING => [Order::STATUS_SHIPPED, Order::STATUS_CANCELLED],
            Order::STATUS_SHIPPED => [Order::STATUS_DELIVERED],
            Order::STATUS_DELIVERED => [], // No transitions from delivered
            Order::STATUS_CANCELLED => [], // No transitions from cancelled
        ];

        if (! in_array($newStatus, $allowedTransitions[$order->status] ?? [])) {
            return response()->json([
                'success' => false,
                'message' => "Cannot transition from {$order->status} to {$newStatus}",
            ], 400);
        }

        try {
            // If updating to processing status due to payment success, also update payment status
            if ($newStatus === Order::STATUS_PROCESSING && $order->payment_status === Order::PAYMENT_STATUS_PENDING) {
                $order->updatePaymentStatus(Order::PAYMENT_STATUS_PAID);
            } else {
                $order->update(['status' => $newStatus]);
            }

            $order->load(['orderItems', 'user']);

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully',
                'data' => new OrderResource($order),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order status: '.$e->getMessage(),
            ], 500);
        }
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        if (! $order->canBeCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'Order cannot be cancelled',
            ], 400);
        }

        DB::beginTransaction();
        try {
            foreach ($order->orderItems as $item) {
                $product = Product::find($item->product_id);
                if ($product) {
                    $product->increment('stock', $item->quantity);
                }
            }

            $order->update([
                'status' => Order::STATUS_CANCELLED,
                'payment_status' => Order::PAYMENT_STATUS_FAILED,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order',
            ], 500);
        }
    }

    public function updateTracking(Request $request, Order $order): JsonResponse
    {
        // Only admin or order owner can update tracking
        if ($order->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'tracking_status' => 'required|string',
            'location' => 'nullable|string',
            'description' => 'nullable|string',
            'tracking_number' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!$order->canUpdateTracking()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update tracking for this order',
            ], 400);
        }

        try {
            // Set tracking number if provided
            if ($request->tracking_number && !$order->tracking_number) {
                $order->setTrackingNumber($request->tracking_number);
            }

            $order->updateTrackingStatus($request->tracking_status, [
                'location' => $request->location,
                'description' => $request->description,
                'updated_by' => $request->user()->name,
            ]);

            // Update main order status if needed
            if ($request->tracking_status === 'delivered') {
                $order->update(['status' => Order::STATUS_DELIVERED]);
            } elseif ($request->tracking_status === 'in_transit' && $order->status === Order::STATUS_PROCESSING) {
                $order->update(['status' => Order::STATUS_SHIPPED]);
            }

            $order->load(['orderItems', 'user']);

            return response()->json([
                'success' => true,
                'message' => 'Tracking updated successfully',
                'data' => new OrderResource($order),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tracking: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getTracking(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'tracking_number' => $order->tracking_number,
                'tracking_history' => $order->tracking_history ?? [],
                'tracking_progress' => $order->getTrackingProgress(),
                'latest_status' => $order->getLatestTrackingStatus(),
                'shipped_at' => $order->shipped_at,
                'delivered_at' => $order->delivered_at,
                'courier_service' => $order->courier_service,
            ],
        ]);
    }
}
