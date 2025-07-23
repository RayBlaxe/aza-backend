<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function getStats(): JsonResponse
    {
        $totalRevenue = Order::where('payment_status', Order::PAYMENT_STATUS_PAID)
            ->sum('total_amount');

        $totalOrders = Order::count();
        $totalProducts = Product::count();
        $totalCustomers = User::where('role', 'customer')->count();

        $pendingOrders = Order::where('status', Order::STATUS_PENDING)->count();
        $processingOrders = Order::where('status', Order::STATUS_PROCESSING)->count();
        $completedOrders = Order::where('status', Order::STATUS_DELIVERED)->count();

        $lowStockProducts = Product::where('stock', '<=', 10)->count();

        return response()->json([
            'revenue' => [
                'total' => $totalRevenue,
                'this_month' => Order::where('payment_status', Order::PAYMENT_STATUS_PAID)
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->sum('total_amount'),
            ],
            'orders' => [
                'total' => $totalOrders,
                'pending' => $pendingOrders,
                'processing' => $processingOrders,
                'completed' => $completedOrders,
                'today' => Order::whereDate('created_at', today())->count(),
            ],
            'products' => [
                'total' => $totalProducts,
                'active' => Product::where('is_active', true)->count(),
                'low_stock' => $lowStockProducts,
            ],
            'customers' => [
                'total' => $totalCustomers,
                'new_this_month' => User::where('role', 'customer')
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
            ],
        ]);
    }

    public function getRecentOrders(): JsonResponse
    {
        $orders = Order::with(['user', 'orderItems.product'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->user->name,
                    'customer_email' => $order->user->email,
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'created_at' => $order->created_at,
                    'items_count' => $order->orderItems->count(),
                ];
            });

        return response()->json(['orders' => $orders]);
    }

    public function getTopProducts(): JsonResponse
    {
        $topProducts = OrderItem::selectRaw('product_id, SUM(quantity) as total_sold, SUM(price * quantity) as total_revenue')
            ->with('product')
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'sku' => $item->product->sku,
                    'total_sold' => $item->total_sold,
                    'total_revenue' => $item->total_revenue,
                    'current_stock' => $item->product->stock,
                    'image' => $item->product->images ? ($item->product->images[0] ?? null) : null,
                ];
            });

        return response()->json(['products' => $topProducts]);
    }

    public function getSalesChart(): JsonResponse
    {
        $salesData = collect();
        $startDate = now()->subDays(29);

        for ($i = 0; $i < 30; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dailySales = Order::where('payment_status', Order::PAYMENT_STATUS_PAID)
                ->whereDate('created_at', $date)
                ->sum('total_amount');

            $salesData->push([
                'date' => $date->format('Y-m-d'),
                'sales' => $dailySales,
                'orders_count' => Order::whereDate('created_at', $date)->count(),
            ]);
        }

        return response()->json(['chart_data' => $salesData]);
    }
}
