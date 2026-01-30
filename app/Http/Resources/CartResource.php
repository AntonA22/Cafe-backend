<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->items ?? collect();

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'items' => $items->map(function ($item) {
                $dessert = $item->dessert;

                return [
                    'id' => $item->id,
                    'dessert_id' => $item->dessert_id,
                    'qty' => $item->qty,
                    'price' => $item->price, // цена за единицу (зафиксированная)
                    'sum' => $item->qty * $item->price,
                    'dessert' => $dessert ? [
                        'id' => $dessert->id,
                        'name' => $dessert->name,
                        'description' => $dessert->description,
                        'photos' => $dessert->photos,
                    ] : null,
                ];
            })->values(),
            'total' => $items->sum(fn($i) => $i->qty * $i->price),
        ];
    }
}