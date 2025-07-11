<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'total_items' => $this->total_items,
            'total' => $this->total,
            'formatted_total' => 'Rp ' . number_format($this->total, 0, ',', '.'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}