<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query();
        $currentUser = auth('sanctum')->user();

        // If the user is admin (not superadmin), only show customers
        if ($currentUser->role === 'admin') {
            $query->where('role', 'customer');
        }
        // For superadmin, show all users based on filter or all if no filter

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Only apply role filter if user is superadmin
        if ($request->has('role') && $currentUser->role === 'superadmin') {
            $query->where('role', $request->role);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $users = $query->withCount(['orders'])
            ->orderBy($request->get('sort_by', 'created_at'), $request->get('sort_order', 'desc'))
            ->paginate($request->get('per_page', 15));

        $users->getCollection()->transform(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'status' => $user->status,
                'last_login' => $user->last_login,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'order_count' => $user->orders_count,
                'total_spent' => $user->orders()
                    ->where('payment_status', Order::PAYMENT_STATUS_PAID)
                    ->sum('total_amount'),
            ];
        });

        return response()->json($users);
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['orders.orderItems.product']);

        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'status' => $user->status,
            'last_login' => $user->last_login,
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'order_count' => $user->orders->count(),
            'total_spent' => $user->orders
                ->where('payment_status', Order::PAYMENT_STATUS_PAID)
                ->sum('total_amount'),
            'statistics' => [
                'total_orders' => $user->orders->count(),
                'completed_orders' => $user->orders->where('status', Order::STATUS_DELIVERED)->count(),
                'pending_orders' => $user->orders->where('status', Order::STATUS_PENDING)->count(),
                'cancelled_orders' => $user->orders->where('status', Order::STATUS_CANCELLED)->count(),
                'total_spent' => $user->orders
                    ->where('payment_status', Order::PAYMENT_STATUS_PAID)
                    ->sum('total_amount'),
                'average_order_value' => $user->orders->where('payment_status', Order::PAYMENT_STATUS_PAID)->count() > 0
                    ? $user->orders->where('payment_status', Order::PAYMENT_STATUS_PAID)->avg('total_amount')
                    : 0,
                'last_order_date' => $user->orders->max('created_at'),
            ],
            'recent_orders' => $user->orders()->with('orderItems.product')
                ->latest()
                ->limit(5)
                ->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => $order->status,
                        'payment_status' => $order->payment_status,
                        'total_amount' => $order->total_amount,
                        'items_count' => $order->orderItems->count(),
                        'created_at' => $order->created_at,
                    ];
                }),
        ];

        return response()->json(['user' => $userData]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:customer,admin,superadmin',
        ]);

        // Only superadmin can create superadmin users
        if ($request->role === 'superadmin' && auth('sanctum')->user()->role !== 'superadmin') {
            return response()->json([
                'error' => 'Only superadmin can create superadmin users',
            ], 403);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user,
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'role' => 'required|in:customer,admin,superadmin',
        ]);

        // Only superadmin can update to superadmin role
        if ($request->role === 'superadmin' && auth('sanctum')->user()->role !== 'superadmin') {
            return response()->json([
                'error' => 'Only superadmin can assign superadmin role',
            ], 403);
        }

        // Prevent modifying superadmin users unless you are superadmin
        if ($user->role === 'superadmin' && auth('sanctum')->user()->role !== 'superadmin') {
            return response()->json([
                'error' => 'Only superadmin can modify superadmin users',
            ], 403);
        }

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user->fresh(),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === auth('sanctum')->id()) {
            return response()->json([
                'error' => 'Cannot delete your own account',
            ], 422);
        }

        // Prevent deleting superadmin users unless you are superadmin
        if ($user->role === 'superadmin' && auth('sanctum')->user()->role !== 'superadmin') {
            return response()->json([
                'error' => 'Only superadmin can delete superadmin users',
            ], 403);
        }

        if ($user->orders()->exists()) {
            return response()->json([
                'error' => 'Cannot delete user with existing orders',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'role' => 'required|in:customer,admin,superadmin',
        ]);

        // Only superadmin can change roles to/from superadmin
        if (($request->role === 'superadmin' || $user->role === 'superadmin') && auth('sanctum')->user()->role !== 'superadmin') {
            return response()->json([
                'error' => 'Only superadmin can change superadmin roles',
            ], 403);
        }

        if ($user->id === auth('sanctum')->id() && !in_array($request->role, ['admin', 'superadmin'])) {
            return response()->json([
                'error' => 'Cannot change your own admin role',
            ], 422);
        }

        $oldRole = $user->role;
        $user->update(['role' => $request->role]);

        return response()->json([
            'message' => "User role updated from {$oldRole} to {$request->role}",
            'user' => $user->fresh(),
        ]);
    }

    public function getCustomerStats(): JsonResponse
    {
        $totalCustomers = User::where('role', 'customer')->count();
        $newCustomersThisMonth = User::where('role', 'customer')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $topCustomers = User::where('role', 'customer')
            ->withCount('orders')
            ->whereHas('orders', function ($query) {
                $query->where('payment_status', Order::PAYMENT_STATUS_PAID);
            })
            ->with(['orders' => function ($query) {
                $query->where('payment_status', Order::PAYMENT_STATUS_PAID);
            }])
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'orders_count' => $user->orders_count,
                    'total_spent' => $user->orders->sum('total_amount'),
                    'last_order_date' => $user->orders->max('created_at'),
                ];
            })
            ->sortByDesc('total_spent')
            ->take(10);

        $customerActivityStats = [
            'active_last_30_days' => User::where('role', 'customer')
                ->whereHas('orders', function ($query) {
                    $query->where('created_at', '>=', now()->subDays(30));
                })
                ->count(),
            'active_last_7_days' => User::where('role', 'customer')
                ->whereHas('orders', function ($query) {
                    $query->where('created_at', '>=', now()->subDays(7));
                })
                ->count(),
            'never_ordered' => User::where('role', 'customer')
                ->doesntHave('orders')
                ->count(),
        ];

        return response()->json([
            'total_customers' => $totalCustomers,
            'new_customers_this_month' => $newCustomersThisMonth,
            'top_customers' => $topCustomers->values(),
            'activity_stats' => $customerActivityStats,
        ]);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $users = User::whereIn('id', $request->user_ids)->get();
        $deletedCount = 0;
        $skippedCount = 0;

        foreach ($users as $user) {
            if ($user->id === auth('sanctum')->id() || $user->orders()->exists()) {
                $skippedCount++;

                continue;
            }

            $user->delete();
            $deletedCount++;
        }

        return response()->json([
            'message' => "Deleted {$deletedCount} users, skipped {$skippedCount} users",
            'deleted_count' => $deletedCount,
            'skipped_count' => $skippedCount,
        ]);
    }

    public function exportCsv(Request $request): JsonResponse
    {
        $query = User::withCount('orders');

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        $users = $query->get();

        $csvData = [];
        $csvData[] = ['ID', 'Name', 'Email', 'Role', 'Orders Count', 'Total Spent', 'Created At'];

        foreach ($users as $user) {
            $totalSpent = $user->orders()
                ->where('payment_status', Order::PAYMENT_STATUS_PAID)
                ->sum('total_amount');

            $csvData[] = [
                $user->id,
                $user->name,
                $user->email,
                $user->role,
                $user->orders_count,
                $totalSpent,
                $user->created_at->format('Y-m-d H:i:s'),
            ];
        }

        $filename = 'users_export_'.now()->format('Y_m_d_H_i_s').'.csv';
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
            'message' => 'Users exported successfully',
            'download_url' => asset('storage/'.$filePath),
            'filename' => $filename,
        ]);
    }

    public function getUserOrders(Request $request, User $user): JsonResponse
    {
        $query = $user->orders()->with(['orderItems.product']);

        // Add pagination support
        $perPage = $request->get('per_page', 10);
        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $orders->getCollection()->transform(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total_amount' => $order->total_amount,
                'items_count' => $order->orderItems->count(),
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
                'delivery_date' => $order->delivery_date,
                'items' => $order->orderItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'product' => [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'image' => $item->product->image,
                        ],
                    ];
                }),
            ];
        });

        return response()->json($orders);
    }

    public function toggleStatus(User $user): JsonResponse
    {
        // Toggle between active and inactive status
        // Since we don't have a status field in the User model, we'll use email_verified_at
        if ($user->email_verified_at) {
            $user->email_verified_at = null;
            $status = 'inactive';
        } else {
            $user->email_verified_at = now();
            $status = 'active';
        }
        
        $user->save();

        return response()->json([
            'message' => 'User status updated successfully',
            'user' => $user,
            'status' => $status,
        ]);
    }
}
