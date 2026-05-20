<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ProxiesDessertPhotos;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    use ProxiesDessertPhotos;

    public function toArray(Request $request): array
    {
        $items = $this->items ?? collect();
        $proxyBaseUrl = $this->proxyBaseUrl();

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'formatted_order_number' => $this->formattedOrderNumber(),
            'user_id' => $this->user_id,
            'address_id' => $this->address_id,
            'status' => $this->status,
            'items_count' => $this->items_count,
            'subtotal_price' => $this->subtotal_price,
            'delivery_fee' => $this->delivery_fee,
            'bonus_points_spent' => (int) ($this->bonus_points_spent ?? 0),
            'bonus_points_earned' => (int) ($this->bonus_points_earned ?? 0),
            'bonus_points_refunded_at' => optional($this->bonus_points_refunded_at)->toJSON(),
            'total_price' => $this->total_price,
            'comment' => $this->comment,
            'delivery_mode' => $this->delivery_mode,
            'payment_mode' => $this->payment_mode,
            'leave_at_door' => $this->leave_at_door,
            'customer_phone' => $this->customer_phone,
            'created_at' => optional($this->created_at)->toJSON(),
            'updated_at' => optional($this->updated_at)->toJSON(),
            'address' => $this->whenLoaded('address'),
            'user' => $this->whenLoaded('user'),
            'items' => $items->map(function ($item) use ($proxyBaseUrl) {
                $dessert = $item->dessert;

                return [
                    'id' => $item->id,
                    'order_id' => $item->order_id,
                    'dessert_id' => $item->dessert_id,
                    'qty' => $item->qty,
                    'price' => (int) $item->price,
                    'sum' => (int) $item->sum,
                    'dessert' => $dessert ? [
                        'id' => $dessert->id,
                        'name' => $dessert->name,
                        'category' => $dessert->category,
                        'description' => $dessert->description,
                        'composition' => $dessert->composition,
                        'price' => (float) $dessert->price,
                        'photos' => $this->proxiedPhotos($dessert->photos, $proxyBaseUrl),
                        'available' => (bool) $dessert->available,
                        'weight' => $dessert->weight !== null ? (float) $dessert->weight : null,
                        'calories' => $dessert->calories,
                        'proteins' => $dessert->proteins !== null ? (float) $dessert->proteins : null,
                        'fats' => $dessert->fats !== null ? (float) $dessert->fats : null,
                        'carbohydrates' => $dessert->carbohydrates !== null ? (float) $dessert->carbohydrates : null,
                    ] : null,
                ];
            })->values(),
        ];
    }
}
