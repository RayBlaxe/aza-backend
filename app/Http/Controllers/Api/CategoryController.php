<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::where('is_active', true);

        if ($request->filled('with_products')) {
            $query->with(['activeProducts' => function ($query) {
                $query->take(4);
            }]);
        }

        $categories = $query->orderBy('name')->get();

        return CategoryResource::collection($categories);
    }

    public function show(Category $category, Request $request)
    {
        if (!$category->is_active) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $query = $category->activeProducts();

        if ($request->filled('search')) {
            $query->search($request->search);
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

        return [
            'category' => new CategoryResource($category),
            'products' => ProductResource::collection($products)
        ];
    }
}