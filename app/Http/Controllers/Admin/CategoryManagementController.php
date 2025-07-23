<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Category::withCount('products');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $categories = $query->orderBy($request->get('sort_by', 'name'), $request->get('sort_order', 'asc'))
            ->paginate($request->get('per_page', 15));

        return response()->json($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $category = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'is_active' => $request->get('is_active', true),
        ]);

        return response()->json([
            'message' => 'Category created successfully',
            'category' => $category,
        ], 201);
    }

    public function show(Category $category): JsonResponse
    {
        $category->load(['products' => function ($query) {
            $query->select('id', 'name', 'sku', 'price', 'stock', 'is_active', 'category_id')
                ->latest();
        }]);

        $categoryData = [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'is_active' => $category->is_active,
            'created_at' => $category->created_at,
            'updated_at' => $category->updated_at,
            'products_count' => $category->products->count(),
            'active_products_count' => $category->products->where('is_active', true)->count(),
            'total_stock' => $category->products->sum('stock'),
            'products' => $category->products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'price' => $product->price,
                    'stock' => $product->stock,
                    'is_active' => $product->is_active,
                ];
            }),
        ];

        return response()->json(['category' => $categoryData]);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category->id)],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $category->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'is_active' => $request->get('is_active', $category->is_active),
        ]);

        return response()->json([
            'message' => 'Category updated successfully',
            'category' => $category->fresh(),
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->products()->exists()) {
            return response()->json([
                'error' => 'Cannot delete category that has products. Please move or delete all products first.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }

    public function toggleStatus(Category $category): JsonResponse
    {
        $category->update([
            'is_active' => ! $category->is_active,
        ]);

        return response()->json([
            'message' => 'Category status updated successfully',
            'category' => $category,
        ]);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'category_ids' => 'required|array',
            'category_ids.*' => 'exists:categories,id',
        ]);

        $categories = Category::whereIn('id', $request->category_ids)->get();
        $deletedCount = 0;
        $skippedCount = 0;

        foreach ($categories as $category) {
            if ($category->products()->exists()) {
                $skippedCount++;

                continue;
            }

            $category->delete();
            $deletedCount++;
        }

        return response()->json([
            'message' => "Deleted {$deletedCount} categories, skipped {$skippedCount} categories with products",
            'deleted_count' => $deletedCount,
            'skipped_count' => $skippedCount,
        ]);
    }

    public function exportCsv(Request $request): JsonResponse
    {
        $categories = Category::withCount('products')->get();

        $csvData = [];
        $csvData[] = ['ID', 'Name', 'Slug', 'Description', 'Status', 'Products Count', 'Created At'];

        foreach ($categories as $category) {
            $csvData[] = [
                $category->id,
                $category->name,
                $category->slug,
                $category->description ?? 'N/A',
                $category->is_active ? 'Active' : 'Inactive',
                $category->products_count,
                $category->created_at->format('Y-m-d H:i:s'),
            ];
        }

        $filename = 'categories_export_'.now()->format('Y_m_d_H_i_s').'.csv';
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
            'message' => 'Categories exported successfully',
            'download_url' => asset('storage/'.$filePath),
            'filename' => $filename,
        ]);
    }

    public function getStatistics(): JsonResponse
    {
        $totalCategories = Category::count();
        $activeCategories = Category::where('is_active', true)->count();
        $categoriesWithProducts = Category::has('products')->count();
        $emptyCategories = Category::doesntHave('products')->count();

        $topCategories = Category::withCount('products')
            ->whereHas('products')
            ->orderByDesc('products_count')
            ->limit(5)
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'products_count' => $category->products_count,
                    'total_stock' => $category->products()->sum('stock'),
                    'active_products' => $category->products()->where('is_active', true)->count(),
                ];
            });

        return response()->json([
            'total_categories' => $totalCategories,
            'active_categories' => $activeCategories,
            'categories_with_products' => $categoriesWithProducts,
            'empty_categories' => $emptyCategories,
            'top_categories' => $topCategories,
        ]);
    }
}
