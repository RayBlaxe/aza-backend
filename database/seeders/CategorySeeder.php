<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Football',
                'slug' => 'football',
                'description' => 'Football equipment including balls, boots, jerseys, and training gear',
                'image' => 'football.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Basketball',
                'slug' => 'basketball',
                'description' => 'Basketball equipment including balls, shoes, jerseys, and training accessories',
                'image' => 'basketball.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Badminton',
                'slug' => 'badminton',
                'description' => 'Badminton equipment including rackets, shuttlecocks, shoes, and bags',
                'image' => 'badminton.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Running',
                'slug' => 'running',
                'description' => 'Running gear including shoes, apparel, accessories, and fitness trackers',
                'image' => 'running.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Gym Equipment',
                'slug' => 'gym-equipment',
                'description' => 'Gym and fitness equipment including weights, machines, and training accessories',
                'image' => 'gym-equipment.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Tennis',
                'slug' => 'tennis',
                'description' => 'Tennis equipment including rackets, balls, shoes, and court accessories',
                'image' => 'tennis.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Swimming',
                'slug' => 'swimming',
                'description' => 'Swimming equipment including swimwear, goggles, caps, and pool accessories',
                'image' => 'swimming.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Cycling',
                'slug' => 'cycling',
                'description' => 'Cycling equipment including bikes, helmets, apparel, and accessories',
                'image' => 'cycling.jpg',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}