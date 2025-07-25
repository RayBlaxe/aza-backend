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
     * Calculate shipping cost
     */
    public function calculateShipping(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'destination_city' => 'required|string',
            'total_weight' => 'numeric|min:0.1',
            'courier_service' => 'string|in:regular,express,same_day',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $destinationCity = $request->destination_city;
        $totalWeight = $request->total_weight ?? 1.0;
        $courierService = $request->courier_service ?? 'regular';

        try {
            $shippingCost = $this->shippingService->calculateShippingCost(
                $destinationCity,
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
            'destination_city' => 'required_without:destination_postal_code|string',
            'destination_postal_code' => 'required_without:destination_city|string',
            'courier_service' => 'string|in:regular,express,same_day',
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

            // Use postal code if provided, otherwise use city
            if ($request->destination_postal_code) {
                $shippingCost = $this->shippingService->calculateShippingByPostalCode(
                    $request->destination_postal_code,
                    $totalWeight,
                    $courierService
                );
            } else {
                $shippingCost = $this->shippingService->calculateShippingCost(
                    $request->destination_city,
                    $totalWeight,
                    $courierService
                );
            }

            return response()->json([
                'success' => true,
                'data' => array_merge($shippingCost, [
                    'total_weight' => $totalWeight,
                    'item_count' => $cart->items->sum('quantity'),
                    'origin' => $this->shippingService->getOriginInfo(),
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
     * Get available courier services for a city
     */
    public function getCourierServices(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'city' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $services = $this->shippingService->getAvailableCourierServices($request->city);

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
     * Get supported cities
     */
    public function getSupportedCities(): JsonResponse
    {
        try {
            $cities = $this->shippingService->getSupportedCities();

            return response()->json([
                'success' => true,
                'data' => array_map('ucfirst', $cities),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get supported cities: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get origin information
     */
    public function getOriginInfo(): JsonResponse
    {
        try {
            $origin = $this->shippingService->getOriginInfo();

            return response()->json([
                'success' => true,
                'data' => $origin,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get origin info: ' . $e->getMessage(),
            ], 500);
        }
    }
}