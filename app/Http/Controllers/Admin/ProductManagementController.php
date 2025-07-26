<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('category');

        if ($request->has('search')) {
            $query->search($request->search);
        }

        if ($request->has('category_id')) {
            $query->byCategory($request->category_id);
        }

        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($request->has('stock_status')) {
            if ($request->stock_status === 'low') {
                $query->where('stock', '<=', 10);
            } elseif ($request->stock_status === 'out') {
                $query->where('stock', 0);
            }
        }

        if ($request->has('sku')) {
            $query->where('sku', 'like', '%'.$request->sku.'%');
        }

        $products = $query->orderBy($request->get('sort_by', 'created_at'), $request->get('sort_order', 'desc'))
            ->paginate($request->get('per_page', 15));

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'sku' => 'required|string|unique:products,sku',
            'weight' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'images' => 'array|max:5',
            'images.*' => 'string',
        ]);

        $product = Product::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'price' => $request->price,
            'stock' => $request->stock,
            'category_id' => $request->category_id,
            'sku' => $request->sku,
            'weight' => $request->weight,
            'is_active' => $request->get('is_active', true),
            'images' => $request->get('images', []),
            'views' => 0,
        ]);

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product->load('category'),
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'product' => $product->load(['category', 'cartItems']),
        ]);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'sku' => ['required', 'string', Rule::unique('products', 'sku')->ignore($product->id)],
            'weight' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'images' => 'array|max:5',
            'images.*' => 'string',
        ]);

        $product->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'price' => $request->price,
            'stock' => $request->stock,
            'category_id' => $request->category_id,
            'sku' => $request->sku,
            'weight' => $request->weight,
            'is_active' => $request->get('is_active', $product->is_active),
            'images' => $request->get('images', $product->images),
        ]);

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product->load('category'),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        if ($product->cartItems()->exists()) {
            return response()->json([
                'error' => 'Cannot delete product that exists in shopping carts',
            ], 422);
        }

        if ($product->images) {
            foreach ($product->images as $image) {
                $imagePath = str_replace(url('storage/'), '', $image);
                Storage::disk('public')->delete($imagePath);
            }
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }

    public function toggleStatus(Product $product): JsonResponse
    {
        $product->update([
            'is_active' => ! $product->is_active,
        ]);

        return response()->json([
            'message' => 'Product status updated successfully',
            'product' => $product,
        ]);
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $filename = time().'_'.uniqid().'.'.$image->getClientOriginalExtension();

            $path = $image->storeAs('images/products', $filename, 'public');
            $url = Storage::disk('public')->url($path);

            return response()->json([
                'message' => 'Image uploaded successfully',
                'url' => $url,
                'path' => $path,
            ]);
        }

        return response()->json([
            'error' => 'No image file provided',
        ], 422);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        $products = Product::whereIn('id', $request->product_ids)->get();
        $deletedCount = 0;
        $skippedCount = 0;

        foreach ($products as $product) {
            if ($product->cartItems()->exists()) {
                $skippedCount++;

                continue;
            }

            if ($product->images) {
                foreach ($product->images as $image) {
                    $imagePath = str_replace(url('storage/'), '', $image);
                    Storage::disk('public')->delete($imagePath);
                }
            }

            $product->delete();
            $deletedCount++;
        }

        return response()->json([
            'message' => "Deleted {$deletedCount} products, skipped {$skippedCount} products that are in shopping carts",
            'deleted_count' => $deletedCount,
            'skipped_count' => $skippedCount,
        ]);
    }

    public function exportCsv(Request $request): JsonResponse
    {
        $products = Product::with('category')->get();

        $csvData = [];
        $csvData[] = ['ID', 'Name', 'SKU', 'Category', 'Price', 'Stock', 'Status', 'Created At'];

        foreach ($products as $product) {
            $csvData[] = [
                $product->id,
                $product->name,
                $product->sku,
                $product->category->name ?? 'N/A',
                $product->price,
                $product->stock,
                $product->is_active ? 'Active' : 'Inactive',
                $product->created_at->format('Y-m-d H:i:s'),
            ];
        }

        $filename = 'products_export_'.now()->format('Y_m_d_H_i_s').'.csv';
        $filePath = 'exports/'.$filename;

        $handle = fopen(storage_path('app/public/'.$filePath), 'w');
        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return response()->json([
            'message' => 'Products exported successfully',
            'download_url' => Storage::disk('public')->url($filePath),
            'filename' => $filename,
        ]);
    }

    public function getStatistics(): JsonResponse
    {
        $totalProducts = Product::count();
        $activeProducts = Product::where('is_active', true)->count();
        $lowStockProducts = Product::where('stock', '<=', 10)->count();
        $outOfStockProducts = Product::where('stock', 0)->count();

        return response()->json([
            'total_products' => $totalProducts,
            'active_products' => $activeProducts,
            'low_stock_products' => $lowStockProducts,
            'out_of_stock_products' => $outOfStockProducts,
        ]);
    }
}
