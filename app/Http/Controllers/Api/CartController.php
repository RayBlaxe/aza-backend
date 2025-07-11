<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function getCart(Request $request)
    {
        $cart = $this->getOrCreateCart($request->user());
        
        return new CartResource($cart->load(['items.product.category']));
    }

    public function addItem(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1|max:10'
        ]);

        $product = Product::findOrFail($request->product_id);

        if (!$product->is_active) {
            return response()->json(['message' => 'Product is not available'], 422);
        }

        if ($product->stock < $request->quantity) {
            return response()->json(['message' => 'Insufficient stock'], 422);
        }

        $cart = $this->getOrCreateCart($request->user());

        $existingItem = $cart->items()->where('product_id', $product->id)->first();

        if ($existingItem) {
            $newQuantity = $existingItem->quantity + $request->quantity;
            
            if ($product->stock < $newQuantity) {
                return response()->json(['message' => 'Insufficient stock'], 422);
            }

            $existingItem->update([
                'quantity' => $newQuantity,
                'price' => $product->price
            ]);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'quantity' => $request->quantity,
                'price' => $product->price
            ]);
        }

        return new CartResource($cart->load(['items.product.category']));
    }

    public function updateItem(Request $request, CartItem $cartItem)
    {
        if ($cartItem->cart->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'quantity' => 'required|integer|min:1|max:10'
        ]);

        $product = $cartItem->product;

        if ($product->stock < $request->quantity) {
            return response()->json(['message' => 'Insufficient stock'], 422);
        }

        $cartItem->update([
            'quantity' => $request->quantity,
            'price' => $product->price
        ]);

        return new CartResource($cartItem->cart->load(['items.product.category']));
    }

    public function removeItem(Request $request, CartItem $cartItem)
    {
        if ($cartItem->cart->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $cart = $cartItem->cart;
        $cartItem->delete();

        return new CartResource($cart->load(['items.product.category']));
    }

    public function clearCart(Request $request)
    {
        $cart = $this->getOrCreateCart($request->user());
        $cart->items()->delete();

        return new CartResource($cart->load(['items.product.category']));
    }

    private function getOrCreateCart($user)
    {
        return Cart::firstOrCreate(['user_id' => $user->id]);
    }
}