<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category')->active();

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('category_id')) {
            $query->byCategory($request->category_id);
        }

        if ($request->filled('min_price') || $request->filled('max_price')) {
            $query->priceRange($request->min_price, $request->max_price);
        }

        if ($request->filled('in_stock') && $request->boolean('in_stock')) {
            $query->inStock();
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $allowedSortFields = ['name', 'price', 'created_at', 'views'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $products = $query->paginate($request->get('per_page', 12));

        return ProductResource::collection($products);
    }

    public function show(Product $product)
    {
        if (! $product->is_active) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $product->increment('views');

        return new ProductResource($product->load('category'));
    }

    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2|max:255',
        ]);

        $products = Product::with('category')
            ->active()
            ->search($request->q)
            ->paginate($request->get('per_page', 12));

        return ProductResource::collection($products);
    }

    public function featured(Request $request)
    {
        $products = Product::with('category')
            ->active()
            ->inStock()
            ->orderBy('views', 'desc')
            ->take($request->get('limit', 8))
            ->get();

        return ProductResource::collection($products);
    }

    public function latest(Request $request)
    {
        $products = Product::with('category')
            ->active()
            ->inStock()
            ->orderBy('created_at', 'desc')
            ->take($request->get('limit', 8))
            ->get();

        return ProductResource::collection($products);
    }
}
