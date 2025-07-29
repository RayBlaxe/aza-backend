<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class SuperadminController extends Controller
{
    /**
     * Get all admin users
     */
    public function getAdmins(Request $request): JsonResponse
    {
        $query = User::where('role', 'admin');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $admins = $query->orderBy($request->get('sort_by', 'created_at'), $request->get('sort_order', 'desc'))
            ->paginate($request->get('per_page', 15));

        $admins->getCollection()->transform(function ($admin) {
            return [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'phone' => $admin->phone,
                'role' => $admin->role,
                'status' => $admin->status,
                'last_login' => $admin->last_login,
                'email_verified_at' => $admin->email_verified_at,
                'created_at' => $admin->created_at,
                'updated_at' => $admin->updated_at,
            ];
        });

        return response()->json($admins);
    }

    /**
     * Get specific admin user
     */
    public function showAdmin(User $admin): JsonResponse
    {
        if ($admin->role !== 'admin') {
            return response()->json([
                'error' => 'User is not an admin',
            ], 404);
        }

        $adminData = [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'phone' => $admin->phone,
            'role' => $admin->role,
            'status' => $admin->status,
            'last_login' => $admin->last_login,
            'email_verified_at' => $admin->email_verified_at,
            'created_at' => $admin->created_at,
            'updated_at' => $admin->updated_at,
        ];

        return response()->json(['admin' => $adminData]);
    }

    /**
     * Create a new admin user
     */
    public function createAdmin(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
        ]);

        $admin = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'admin',
            'phone' => $request->phone,
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'message' => 'Admin created successfully',
            'admin' => $admin,
        ], 201);
    }

    /**
     * Update admin user
     */
    public function updateAdmin(Request $request, User $admin): JsonResponse
    {
        if ($admin->role !== 'admin') {
            return response()->json([
                'error' => 'User is not an admin',
            ], 422);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($admin->id)],
            'password' => 'nullable|string|min:8',
            'phone' => 'nullable|string|max:20',
        ]);

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $admin->update($updateData);

        return response()->json([
            'message' => 'Admin updated successfully',
            'admin' => $admin->fresh(),
        ]);
    }

    /**
     * Delete admin user
     */
    public function deleteAdmin(User $admin): JsonResponse
    {
        if ($admin->role !== 'admin') {
            return response()->json([
                'error' => 'User is not an admin',
            ], 422);
        }

        if ($admin->id === auth('sanctum')->id()) {
            return response()->json([
                'error' => 'Cannot delete your own account',
            ], 422);
        }

        $admin->delete();

        return response()->json([
            'message' => 'Admin deleted successfully',
        ]);
    }

    /**
     * Toggle admin status
     */
    public function toggleAdminStatus(User $admin): JsonResponse
    {
        if ($admin->role !== 'admin') {
            return response()->json([
                'error' => 'User is not an admin',
            ], 422);
        }

        if ($admin->id === auth('sanctum')->id()) {
            return response()->json([
                'error' => 'Cannot change your own status',
            ], 422);
        }

        // Toggle status using email_verified_at
        if ($admin->email_verified_at) {
            $admin->email_verified_at = null;
            $status = 'inactive';
        } else {
            $admin->email_verified_at = now();
            $status = 'active';
        }
        
        $admin->save();

        return response()->json([
            'message' => 'Admin status updated successfully',
            'admin' => $admin,
            'status' => $status,
        ]);
    }

    /**
     * Get admin statistics
     */
    public function getAdminStats(): JsonResponse
    {
        $totalAdmins = User::where('role', 'admin')->count();
        $activeAdmins = User::where('role', 'admin')->whereNotNull('email_verified_at')->count();
        $recentAdmins = User::where('role', 'admin')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return response()->json([
            'total_admins' => $totalAdmins,
            'active_admins' => $activeAdmins,
            'inactive_admins' => $totalAdmins - $activeAdmins,
            'recent_admins_this_month' => $recentAdmins,
        ]);
    }

    /**
     * Bulk delete admins
     */
    public function bulkDeleteAdmins(Request $request): JsonResponse
    {
        $request->validate([
            'admin_ids' => 'required|array',
            'admin_ids.*' => 'exists:users,id',
        ]);

        $admins = User::whereIn('id', $request->admin_ids)
            ->where('role', 'admin')
            ->get();

        $deletedCount = 0;
        $skippedCount = 0;

        foreach ($admins as $admin) {
            if ($admin->id === auth('sanctum')->id()) {
                $skippedCount++;
                continue;
            }

            $admin->delete();
            $deletedCount++;
        }

        return response()->json([
            'message' => "Deleted {$deletedCount} admins, skipped {$skippedCount} admins",
            'deleted_count' => $deletedCount,
            'skipped_count' => $skippedCount,
        ]);
    }
}
