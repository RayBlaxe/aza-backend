<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ShippingService
{
    // Origin postal code for 26 Store Pekanbaru
    private const ORIGIN_POSTAL_CODE = '28127';
    
    // RajaOngkir API base URL
    private const RAJAONGKIR_BASE_URL = 'https://rajaongkir.komerce.id/api/v1';
    
    // Default courier
    private const DEFAULT_COURIER = 'jne';

    /**
     * Calculate shipping cost using RajaOngkir API based on postal codes
     */
    public function calculateShippingCost(
        string $destinationPostalCode,
        float $totalWeight = 1.0,
        string $courierService = 'regular'
    ): array {
        try {
            // Convert weight to grams (RajaOngkir expects weight in grams)
            $weightInGrams = max(ceil($totalWeight * 1000), 1000); // Minimum 1kg
            
            $response = Http::withHeaders([
                'key' => env('RAJAONGKIR_API_KEY'),
                'Content-Type' => 'application/x-www-form-urlencoded'
            ])->asForm()->post(self::RAJAONGKIR_BASE_URL . '/calculate/domestic-cost', [
                'origin' => self::ORIGIN_POSTAL_CODE,
                'destination' => $destinationPostalCode,
                'weight' => $weightInGrams,
                'courier' => self::DEFAULT_COURIER
            ]);
            
            if (!$response->successful()) {
                Log::error('RajaOngkir API failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new Exception('Failed to calculate shipping cost');
            }
            
            $data = $response->json();
            
            if (!isset($data['data']) || empty($data['data'])) {
                throw new Exception('No shipping options available');
            }
            
            // Find the requested service or get the cheapest option
            $shippingOption = null;
            if ($courierService && $courierService !== 'regular') {
                // Look for the specific service requested
                foreach ($data['data'] as $service) {
                    if ($service['service'] === $courierService) {
                        $shippingOption = $service;
                        break;
                    }
                }
            }
            
            // If no specific service found or requesting 'regular', get the cheapest option
            if (!$shippingOption) {
                // Find REG service first (preferred default), otherwise use first option
                foreach ($data['data'] as $service) {
                    if ($service['service'] === 'REG') {
                        $shippingOption = $service;
                        break;
                    }
                }
                // If no REG service, use first option
                if (!$shippingOption) {
                    $shippingOption = $data['data'][0];
                }
            }
            
            return [
                'courier_name' => $shippingOption['name'],
                'courier_code' => $shippingOption['code'],
                'service' => $shippingOption['service'],
                'service_description' => $shippingOption['description'],
                'total_cost' => $shippingOption['cost'],
                'estimated_delivery' => $shippingOption['etd'],
                'weight_grams' => $weightInGrams,
                'origin' => self::ORIGIN_POSTAL_CODE,
                'destination' => $destinationPostalCode,
                'all_services' => $data['data'] // Include all available services
            ];
            
        } catch (Exception $e) {
            Log::error('Shipping calculation error', [
                'error' => $e->getMessage(),
                'destination' => $destinationPostalCode,
                'weight' => $totalWeight
            ]);
            
            // Fallback to basic calculation
            return $this->getFallbackShippingCost($totalWeight);
        }
    }

    /**
     * Get all shipping services available for a destination
     */
    public function getAvailableCourierServices(string $destinationPostalCode, float $totalWeight = 1.0): array
    {
        try {
            $weightInGrams = max(ceil($totalWeight * 1000), 1000);
            
            $response = Http::withHeaders([
                'key' => env('RAJAONGKIR_API_KEY'),
                'Content-Type' => 'application/x-www-form-urlencoded'
            ])->asForm()->post(self::RAJAONGKIR_BASE_URL . '/calculate/domestic-cost', [
                'origin' => self::ORIGIN_POSTAL_CODE,
                'destination' => $destinationPostalCode,
                'weight' => $weightInGrams,
                'courier' => self::DEFAULT_COURIER
            ]);
            
            if (!$response->successful()) {
                throw new Exception('Failed to get courier services');
            }
            
            $data = $response->json();
            
            if (!isset($data['data']) || empty($data['data'])) {
                throw new Exception('No courier services available');
            }
            
            return array_map(function ($service) {
                return [
                    'code' => $service['service'],
                    'name' => $service['name'],
                    'service' => $service['service'],
                    'description' => $service['description'],
                    'cost' => $service['cost'],
                    'etd' => $service['etd']
                ];
            }, $data['data']);
            
        } catch (Exception $e) {
            Log::error('Failed to get courier services', [
                'error' => $e->getMessage(),
                'destination' => $destinationPostalCode
            ]);
            
            // Return basic fallback service
            return [[
                'code' => 'REG',
                'name' => 'JNE',
                'service' => 'REG',
                'description' => 'Layanan Reguler',
                'cost' => 15000,
                'etd' => '2-3 day'
            ]];
        }
    }

    /**
     * Calculate estimated weight from cart items
     */
    public function calculateCartWeight($cartItems): float
    {
        $totalWeight = 0;
        
        foreach ($cartItems as $item) {
            // Default weight estimation based on product type
            $productWeight = $item->product->weight ?? $this->estimateProductWeight($item->product);
            $totalWeight += $productWeight * $item->quantity;
        }
        
        return max($totalWeight, 1.0); // Minimum 1kg for RajaOngkir
    }

    /**
     * Estimate product weight based on category/name
     */
    private function estimateProductWeight($product): float
    {
        $name = strtolower($product->name);
        
        // Weight estimation based on product type
        if (str_contains($name, 'sepatu') || str_contains($name, 'shoes')) {
            return 0.8;
        } elseif (str_contains($name, 'jersey') || str_contains($name, 'kaos')) {
            return 0.3;
        } elseif (str_contains($name, 'raket') || str_contains($name, 'racket')) {
            return 0.4;
        } elseif (str_contains($name, 'bola') || str_contains($name, 'ball')) {
            return 0.5;
        } elseif (str_contains($name, 'tas') || str_contains($name, 'bag')) {
            return 0.6;
        } elseif (str_contains($name, 'helm') || str_contains($name, 'helmet')) {
            return 1.2;
        } else {
            return 0.5; // Default weight
        }
    }

    /**
     * Search for destination addresses using RajaOngkir API
     */
    public function searchDestinations(string $query, int $limit = 10, int $offset = 0): array
    {
        try {
            $response = Http::withHeaders([
                'key' => env('RAJAONGKIR_API_KEY'),
            ])->get(self::RAJAONGKIR_BASE_URL . '/destination/domestic-destination', [
                'search' => $query,
                'limit' => $limit,
                'offset' => $offset
            ]);
            
            if (!$response->successful()) {
                throw new Exception('Failed to search destinations');
            }
            
            $data = $response->json();
            
            if (!isset($data['data'])) {
                return [];
            }
            
            return $data['data'];
            
        } catch (Exception $e) {
            Log::error('Failed to search destinations', [
                'error' => $e->getMessage(),
                'query' => $query
            ]);
            
            return [];
        }
    }
    
    /**
     * Fallback shipping cost calculation when API fails
     */
    private function getFallbackShippingCost(float $totalWeight): array
    {
        $baseCost = 15000; // Base cost in Rupiah
        $weightMultiplier = 1 + (($totalWeight - 1) * 0.1); // Add 10% per kg above 1kg
        $finalCost = ceil($baseCost * $weightMultiplier / 1000) * 1000;
        
        return [
            'courier_name' => 'JNE',
            'courier_code' => 'jne',
            'service' => 'REG',
            'service_description' => 'Layanan Reguler (Fallback)',
            'total_cost' => $finalCost,
            'estimated_delivery' => '2-3 day',
            'weight_grams' => ceil($totalWeight * 1000),
            'origin' => self::ORIGIN_POSTAL_CODE,
            'destination' => 'unknown',
            'is_fallback' => true
        ];
    }

    /**
     * Get origin information
     */
    public function getOriginInfo(): array
    {
        return [
            'postal_code' => self::ORIGIN_POSTAL_CODE,
            'city' => 'Pekanbaru',
            'province' => 'Riau',
            'country' => 'Indonesia',
        ];
    }

    /**
     * Calculate shipping cost with destination postal code support (main method)
     */
    public function calculateShippingByPostalCode(
        string $destinationPostalCode,
        float $totalWeight = 1.0,
        string $courierService = 'regular'
    ): array {
        // Use the main calculateShippingCost method which now uses RajaOngkir API
        return $this->calculateShippingCost($destinationPostalCode, $totalWeight, $courierService);
    }
}