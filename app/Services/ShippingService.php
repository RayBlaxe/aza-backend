<?php

namespace App\Services;

class ShippingService
{
    // Origin postal code
    private const ORIGIN_POSTAL_CODE = '28127'; // Pekanbaru
    
    // Base rates per city with distance from Pekanbaru (in Rupiah)
    private const CITY_RATES = [
        'pekanbaru' => 8000,    // Same city
        'dumai' => 10000,       // ~138 km
        'rengat' => 12000,      // ~182 km  
        'bangkinang' => 9000,   // ~56 km
        'duri' => 11000,        // ~122 km
        'batam' => 15000,       // ~272 km
        'tanjungpinang' => 16000, // ~290 km
        'jakarta' => 22000,     // ~870 km
        'bandung' => 24000,     // ~920 km
        'surabaya' => 28000,    // ~1200 km
        'medan' => 18000,       // ~440 km
        'padang' => 20000,      // ~340 km
        'jambi' => 16000,       // ~230 km
        'palembang' => 20000,   // ~350 km
        'lampung' => 25000,     // ~580 km
        'semarang' => 26000,    // ~1100 km
        'yogyakarta' => 27000,  // ~1150 km
        'makassar' => 35000,    // ~1600 km
        'manado' => 40000,      // ~2200 km
        'pontianak' => 22000,   // ~520 km
    ];

    // Weight-based multipliers
    private const WEIGHT_MULTIPLIERS = [
        'light' => 1.0,    // 0-1kg
        'medium' => 1.5,   // 1-5kg
        'heavy' => 2.0,    // 5kg+
    ];

    // Courier services with different rates
    private const COURIER_MULTIPLIERS = [
        'regular' => 1.0,
        'express' => 1.5,
        'same_day' => 2.5,
    ];

    /**
     * Calculate shipping cost based on destination, weight, and service type
     */
    public function calculateShippingCost(
        string $destinationCity,
        float $totalWeight = 1.0,
        string $courierService = 'regular'
    ): array {
        $city = strtolower(trim($destinationCity));
        
        // Get base rate for city (default to standard rate if city not found)
        $baseRate = self::CITY_RATES[$city] ?? 15000;
        
        // Determine weight category
        $weightCategory = $this->getWeightCategory($totalWeight);
        $weightMultiplier = self::WEIGHT_MULTIPLIERS[$weightCategory];
        
        // Get courier service multiplier
        $courierMultiplier = self::COURIER_MULTIPLIERS[$courierService] ?? 1.0;
        
        // Calculate final cost
        $finalCost = $baseRate * $weightMultiplier * $courierMultiplier;
        
        // Round to nearest 1000
        $finalCost = ceil($finalCost / 1000) * 1000;
        
        return [
            'base_rate' => $baseRate,
            'weight_category' => $weightCategory,
            'weight_multiplier' => $weightMultiplier,
            'courier_service' => $courierService,
            'courier_multiplier' => $courierMultiplier,
            'total_cost' => $finalCost,
            'estimated_days' => $this->getEstimatedDeliveryDays($city, $courierService),
        ];
    }

    /**
     * Get available courier services for a destination
     */
    public function getAvailableCourierServices(string $destinationCity): array
    {
        $city = strtolower(trim($destinationCity));
        
        // Same day delivery only available for major cities
        $majorCities = ['pekanbaru', 'jakarta', 'bandung', 'surabaya', 'medan'];
        
        $services = [
            [
                'code' => 'regular',
                'name' => 'Reguler',
                'description' => 'Pengiriman standar',
                'multiplier' => 1.0,
            ],
            [
                'code' => 'express',
                'name' => 'Express',
                'description' => 'Pengiriman cepat',
                'multiplier' => 1.5,
            ],
        ];
        
        if (in_array($city, $majorCities)) {
            $services[] = [
                'code' => 'same_day',
                'name' => 'Same Day',
                'description' => 'Pengiriman hari yang sama',
                'multiplier' => 2.5,
            ];
        }
        
        return $services;
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
        
        return max($totalWeight, 0.5); // Minimum 0.5kg
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
     * Get weight category based on total weight
     */
    private function getWeightCategory(float $weight): string
    {
        if ($weight <= 1.0) {
            return 'light';
        } elseif ($weight <= 5.0) {
            return 'medium';
        } else {
            return 'heavy';
        }
    }

    /**
     * Get estimated delivery days
     */
    private function getEstimatedDeliveryDays(string $city, string $courierService): array
    {
        $baseDays = [
            'pekanbaru' => 1,
            'jakarta' => 2,
            'bandung' => 2,
            'surabaya' => 3,
            'medan' => 2,
            'semarang' => 3,
            'makassar' => 4,
            'yogyakarta' => 3,
            'palembang' => 2,
            'batam' => 2,
        ];
        
        $base = $baseDays[$city] ?? 3;
        
        switch ($courierService) {
            case 'same_day':
                return ['min' => 0, 'max' => 1];
            case 'express':
                return ['min' => max(1, $base - 1), 'max' => $base];
            default:
                return ['min' => $base, 'max' => $base + 2];
        }
    }

    /**
     * Get all supported cities
     */
    public function getSupportedCities(): array
    {
        return array_keys(self::CITY_RATES);
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
     * Calculate shipping cost with destination postal code support
     */
    public function calculateShippingByPostalCode(
        string $destinationPostalCode,
        float $totalWeight = 1.0,
        string $courierService = 'regular'
    ): array {
        // For now, we'll use city-based calculation
        // In a real application, you would use a postal code API service
        // to determine the city from postal code
        
        $city = $this->getCityFromPostalCode($destinationPostalCode);
        
        return $this->calculateShippingCost($city, $totalWeight, $courierService);
    }

    /**
     * Get city from postal code (simplified mapping)
     */
    private function getCityFromPostalCode(string $postalCode): string
    {
        // This is a simplified mapping - in production you'd use a proper API
        $postalCodeMap = [
            '28127' => 'pekanbaru',
            '28111' => 'pekanbaru',
            '28112' => 'pekanbaru',
            '28113' => 'pekanbaru',
            '28114' => 'pekanbaru',
            '28115' => 'pekanbaru',
            '28116' => 'pekanbaru',
            '28117' => 'pekanbaru',
            '28118' => 'pekanbaru',
            '28119' => 'pekanbaru',
            '28121' => 'pekanbaru',
            '28122' => 'pekanbaru',
            '28123' => 'pekanbaru',
            '28124' => 'pekanbaru',
            '28125' => 'pekanbaru',
            '28126' => 'pekanbaru',
            '28127' => 'pekanbaru',
            '28128' => 'pekanbaru',
            '28129' => 'pekanbaru',
            '28131' => 'pekanbaru',
            '28132' => 'pekanbaru',
            '28133' => 'pekanbaru',
            '28134' => 'pekanbaru',
            '28135' => 'pekanbaru',
            '28136' => 'pekanbaru',
            '28141' => 'pekanbaru',
            '28142' => 'pekanbaru',
            '28143' => 'pekanbaru',
            '28144' => 'pekanbaru',
            '28151' => 'pekanbaru',
            '28152' => 'pekanbaru',
            '28153' => 'pekanbaru',
            '28154' => 'pekanbaru',
            '28155' => 'pekanbaru',
            '28156' => 'pekanbaru',
            '28161' => 'pekanbaru',
            '28171' => 'pekanbaru',
            '28172' => 'pekanbaru',
            '28173' => 'pekanbaru',
            '28174' => 'pekanbaru',
            '28175' => 'pekanbaru',
            '28176' => 'pekanbaru',
            '28177' => 'pekanbaru',
            '28178' => 'pekanbaru',
            '28179' => 'pekanbaru',
            '28181' => 'pekanbaru',
            '28182' => 'pekanbaru',
            '28183' => 'pekanbaru',
            '28284' => 'dumai',
            '28285' => 'dumai',
            '28286' => 'dumai',
            '28287' => 'dumai',
            '28288' => 'dumai',
            '28289' => 'dumai',
            '29711' => 'batam',
            '29712' => 'batam',
            '29713' => 'batam',
            '29714' => 'batam',
            '29715' => 'batam',
            '29716' => 'batam',
            '10110' => 'jakarta',
            '10111' => 'jakarta',
            '10112' => 'jakarta',
            '20111' => 'medan',
            '20112' => 'medan',
            '20113' => 'medan',
        ];

        return $postalCodeMap[$postalCode] ?? 'jakarta'; // Default to Jakarta if not found
    }
}