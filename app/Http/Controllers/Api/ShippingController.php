<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShippingController extends Controller
{
    protected $shippingService;

    public function __construct(ShippingService $shippingService)
    {
        $this->shippingService = $shippingService;
    }

    /**
     * Calculate shipping cost using postal code
     */
    public function calculateShipping(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'destination_postal_code' => 'required|string',
            'total_weight' => 'numeric|min:1',
            'courier_service' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $destinationPostalCode = $request->destination_postal_code;
        $totalWeight = $request->total_weight ?? 1.0;
        $courierService = $request->courier_service ?? 'regular';

        try {
            $shippingCost = $this->shippingService->calculateShippingCost(
                $destinationPostalCode,
                $totalWeight,
                $courierService
            );

            return response()->json([
                'success' => true,
                'data' => $shippingCost,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate shipping cost: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate shipping cost from cart
     */
    public function calculateCartShipping(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'destination_postal_code' => 'required|string',
            'courier_service' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $cart = $user->cart;

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Cart is empty',
            ], 400);
        }

        $cart->load('items.product');

        try {
            $totalWeight = $this->shippingService->calculateCartWeight($cart->items);
            $courierService = $request->courier_service ?? 'regular';

            // Use postal code for shipping calculation (RajaOngkir requires postal code)
            $shippingCost = $this->shippingService->calculateShippingCost(
                $request->destination_postal_code,
                $totalWeight,
                $courierService
            );

            return response()->json([
                'success' => true,
                'data' => array_merge($shippingCost, [
                    'total_weight' => $totalWeight,
                    'item_count' => $cart->items->sum('quantity'),
                ]),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate shipping cost: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available courier services for a postal code
     */
    public function getCourierServices(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'postal_code' => 'required|string',
            'weight' => 'numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $weight = $request->weight ?? 1.0;
            $services = $this->shippingService->getAvailableCourierServices($request->postal_code, $weight);

            return response()->json([
                'success' => true,
                'data' => $services,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get courier services: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search destinations using RajaOngkir API
     */
    public function searchDestinations(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'search' => 'required|string|min:2',
            'limit' => 'integer|min:1|max:50',
            'offset' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $search = $request->search;
            $limit = $request->limit ?? 10;
            $offset = $request->offset ?? 0;
            
            $destinations = $this->shippingService->searchDestinations($search, $limit, $offset);

            return response()->json([
                'success' => true,
                'data' => $destinations,
                'meta' => [
                    'search_query' => $search,
                    'limit' => $limit,
                    'offset' => $offset,
                    'count' => count($destinations)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search destinations: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Get supported cities (legacy method - now returns search suggestion)
     */
    public function getSupportedCities(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Use /api/shipping/search-destinations endpoint with search parameter',
            'data' => [],
        ]);
    }

    /**
     * Get origin information
     */
    public function getOriginInfo(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'postal_code' => '28127',
                'city' => 'Pekanbaru',
                'province' => 'Riau',
                'country' => 'Indonesia',
                'store_name' => '26 Store Pekanbaru'
            ],
        ]);
    }
}