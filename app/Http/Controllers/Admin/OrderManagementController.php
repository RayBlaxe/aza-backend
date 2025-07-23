<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['user', 'orderItems.product']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('min_amount')) {
            $query->where('total_amount', '>=', $request->min_amount);
        }

        if ($request->has('max_amount')) {
            $query->where('total_amount', '<=', $request->max_amount);
        }

        $orders = $query->orderBy($request->get('sort_by', 'created_at'), $request->get('sort_order', 'desc'))
            ->paginate($request->get('per_page', 15));

        $orders->getCollection()->transform(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer' => [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                ],
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'subtotal' => $order->subtotal,
                'shipping_cost' => $order->shipping_cost,
                'total_amount' => $order->total_amount,
                'items_count' => $order->orderItems->count(),
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
                'can_be_cancelled' => $order->canBeCancelled(),
                'is_paid' => $order->isPaid(),
            ];
        });

        return response()->json($orders);
    }

    public function show(Order $order): JsonResponse
    {
        $order->load(['user', 'orderItems.product']);

        $orderData = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer' => [
                'id' => $order->user->id,
                'name' => $order->user->name,
                'email' => $order->user->email,
            ],
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'subtotal' => $order->subtotal,
            'shipping_cost' => $order->shipping_cost,
            'total_amount' => $order->total_amount,
            'shipping_address' => $order->shipping_address,
            'payment_response' => $order->payment_response,
            'notes' => $order->notes,
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
            'can_be_cancelled' => $order->canBeCancelled(),
            'is_paid' => $order->isPaid(),
            'items' => $order->orderItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'sku' => $item->product->sku,
                        'images' => $item->product->images,
                    ],
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'total' => $item->quantity * $item->price,
                ];
            }),
        ];

        return response()->json(['order' => $orderData]);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'status' => [
                'required',
                'string',
                Rule::in([
                    Order::STATUS_PENDING,
                    Order::STATUS_PROCESSING,
                    Order::STATUS_SHIPPED,
                    Order::STATUS_DELIVERED,
                    Order::STATUS_CANCELLED,
                ]),
            ],
            'notes' => 'nullable|string',
        ]);

        $oldStatus = $order->status;
        $newStatus = $request->status;

        if ($newStatus === Order::STATUS_CANCELLED && $order->isPaid()) {
            return response()->json([
                'error' => 'Cannot cancel a paid order. Please process a refund first.',
            ], 422);
        }

        if ($newStatus === Order::STATUS_CANCELLED) {
            $order->restoreStock();
        }

        $order->update([
            'status' => $newStatus,
            'notes' => $request->get('notes', $order->notes),
        ]);

        return response()->json([
            'message' => "Order status updated from {$oldStatus} to {$newStatus}",
            'order' => $order->fresh(['user', 'orderItems.product']),
        ]);
    }

    public function getOrdersByStatus(Request $request): JsonResponse
    {
        $statusCounts = [
            'pending' => Order::where('status', Order::STATUS_PENDING)->count(),
            'processing' => Order::where('status', Order::STATUS_PROCESSING)->count(),
            'shipped' => Order::where('status', Order::STATUS_SHIPPED)->count(),
            'delivered' => Order::where('status', Order::STATUS_DELIVERED)->count(),
            'cancelled' => Order::where('status', Order::STATUS_CANCELLED)->count(),
        ];

        if ($request->has('status')) {
            $orders = Order::where('status', $request->status)
                ->with(['user', 'orderItems.product'])
                ->latest()
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'status_counts' => $statusCounts,
                'orders' => $orders,
            ]);
        }

        return response()->json(['status_counts' => $statusCounts]);
    }

    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'exists:orders,id',
            'status' => [
                'required',
                'string',
                Rule::in([
                    Order::STATUS_PENDING,
                    Order::STATUS_PROCESSING,
                    Order::STATUS_SHIPPED,
                    Order::STATUS_DELIVERED,
                    Order::STATUS_CANCELLED,
                ]),
            ],
        ]);

        $orders = Order::whereIn('id', $request->order_ids)->get();
        $updatedCount = 0;
        $skippedCount = 0;

        foreach ($orders as $order) {
            if ($request->status === Order::STATUS_CANCELLED && $order->isPaid()) {
                $skippedCount++;

                continue;
            }

            if ($request->status === Order::STATUS_CANCELLED) {
                $order->restoreStock();
            }

            $order->update(['status' => $request->status]);
            $updatedCount++;
        }

        return response()->json([
            'message' => "Updated {$updatedCount} orders, skipped {$skippedCount} paid orders",
            'updated_count' => $updatedCount,
            'skipped_count' => $skippedCount,
        ]);
    }

    public function exportCsv(Request $request): JsonResponse
    {
        $query = Order::with(['user', 'orderItems.product']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->get();

        $csvData = [];
        $csvData[] = [
            'Order Number', 'Customer Name', 'Customer Email', 'Status',
            'Payment Status', 'Payment Method', 'Total Amount', 'Items Count', 'Created At',
        ];

        foreach ($orders as $order) {
            $csvData[] = [
                $order->order_number,
                $order->user->name,
                $order->user->email,
                $order->status,
                $order->payment_status,
                $order->payment_method,
                $order->total_amount,
                $order->orderItems->count(),
                $order->created_at->format('Y-m-d H:i:s'),
            ];
        }

        $filename = 'orders_export_'.now()->format('Y_m_d_H_i_s').'.csv';
        $filePath = 'exports/'.$filename;

        if (! file_exists(storage_path('app/public/exports'))) {
            mkdir(storage_path('app/public/exports'), 0755, true);
        }

        $handle = fopen(storage_path('app/public/'.$filePath), 'w');
        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return response()->json([
            'message' => 'Orders exported successfully',
            'download_url' => asset('storage/'.$filePath),
            'filename' => $filename,
        ]);
    }
}
