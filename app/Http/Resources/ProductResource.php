<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price,
            'formatted_price' => 'Rp '.number_format($this->price, 0, ',', '.'),
            'stock' => $this->stock,
            'in_stock' => $this->stock > 0,
            'images' => $this->images ?? [],
            'sku' => $this->sku,
            'weight' => $this->weight,
            'is_active' => $this->is_active,
            'views' => $this->views,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'category_id' => $this->category_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
