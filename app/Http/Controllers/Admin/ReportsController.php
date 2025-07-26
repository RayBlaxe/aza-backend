<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReportsController extends Controller
{
    public function getSalesReport(Request $request): JsonResponse
    {
        $period = $request->get('period', '30days');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        // Set date range based on period
        if ($period === 'custom' && $startDate && $endDate) {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
        } else {
            $end = Carbon::now();
            switch ($period) {
                case '7days':
                    $start = $end->copy()->subDays(7);
                    break;
                case '90days':
                    $start = $end->copy()->subDays(90);
                    break;
                case '6months':
                    $start = $end->copy()->subMonths(6);
                    break;
                case '1year':
                    $start = $end->copy()->subYear();
                    break;
                default: // 30days
                    $start = $end->copy()->subDays(30);
            }
        }

        // Calculate metrics
        $paidOrders = Order::where('payment_status', Order::PAYMENT_STATUS_PAID)
            ->whereBetween('created_at', [$start, $end]);

        $totalRevenue = $paidOrders->sum('total_amount');
        $totalOrders = Order::whereBetween('created_at', [$start, $end])->count();
        $avgOrderValue = $paidOrders->avg('total_amount') ?: 0;
        $totalCustomers = User::where('role', 'customer')->count();

        // Previous period for growth calculation
        $previousStart = $start->copy()->sub($end->diffInDays($start), 'days');
        $previousEnd = $start->copy();

        $previousRevenue = Order::where('payment_status', Order::PAYMENT_STATUS_PAID)
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->sum('total_amount');
        
        $previousOrders = Order::whereBetween('created_at', [$previousStart, $previousEnd])->count();

        $revenueGrowth = $previousRevenue > 0 ? (($totalRevenue - $previousRevenue) / $previousRevenue) * 100 : 0;
        $ordersGrowth = $previousOrders > 0 ? (($totalOrders - $previousOrders) / $previousOrders) * 100 : 0;

        // Top selling products
        $topSellingProducts = OrderItem::selectRaw('product_id, SUM(quantity) as total_sold, SUM(price * quantity) as total_revenue')
            ->with('product')
            ->whereHas('order', function($query) use ($start, $end) {
                $query->whereBetween('created_at', [$start, $end]);
            })
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'soldQty' => $item->total_sold,
                    'revenue' => $item->total_revenue,
                ];
            });

        // Recent orders
        $recentOrders = Order::with('user')
            ->whereBetween('created_at', [$start, $end])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'orderNumber' => $order->order_number,
                    'customer' => $order->user->name,
                    'total' => $order->total_amount,
                    'status' => $order->status,
                    'date' => $order->created_at->format('Y-m-d'),
                ];
            });

        // Monthly revenue (last 6 months)
        $monthlyRevenue = collect();
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = Carbon::now()->subMonths($i)->startOfMonth();
            $monthEnd = Carbon::now()->subMonths($i)->endOfMonth();
            
            $monthRevenue = Order::where('payment_status', Order::PAYMENT_STATUS_PAID)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('total_amount');
            
            $monthOrders = Order::whereBetween('created_at', [$monthStart, $monthEnd])->count();
            
            $monthlyRevenue->push([
                'month' => $monthStart->format('M'),
                'revenue' => $monthRevenue,
                'orders' => $monthOrders,
            ]);
        }

        return response()->json([
            'totalRevenue' => $totalRevenue,
            'totalOrders' => $totalOrders,
            'totalProducts' => Product::count(),
            'totalCustomers' => $totalCustomers,
            'avgOrderValue' => round($avgOrderValue, 0),
            'revenueGrowth' => round($revenueGrowth, 1),
            'ordersGrowth' => round($ordersGrowth, 1),
            'topSellingProducts' => $topSellingProducts,
            'recentOrders' => $recentOrders,
            'monthlyRevenue' => $monthlyRevenue,
        ]);
    }
}
