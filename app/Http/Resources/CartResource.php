<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ProxiesDessertPhotos;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    use ProxiesDessertPhotos;

    public function toArray(Request $request): array
    {
        $items = $this->items ?? collect();
        $customCakeItems = $this->customCakeItems ?? collect();
        $proxyBaseUrl = $this->proxyBaseUrl();
        $regularItems = $items->map(function ($item) use ($proxyBaseUrl) {
            $dessert = $item->dessert;

            return [
                'id' => $item->id,
                'item_type' => 'dessert',
                'dessert_id' => $item->dessert_id,
                'custom_cake_cart_item_id' => null,
                'qty' => $item->qty,
                'price' => $item->price, // цена за единицу (зафиксированная)
                'sum' => $item->qty * $item->price,
                'custom_cake' => null,
                'dessert' => $dessert ? [
                    'id' => $dessert->id,
                    'name' => $dessert->name,
                    'description' => $dessert->description,
                    'photos' => $this->proxiedPhotos($dessert->photos, $proxyBaseUrl),
                ] : null,
            ];
        });

        $customItems = $customCakeItems->map(function ($item) use ($proxyBaseUrl) {
            $payload = is_array($item->payload) ? $item->payload : [];
            $designName = trim((string) ($payload['design_name'] ?? ''));
            $weightTitle = trim((string) ($payload['weight_title'] ?? ''));
            $inscription = trim((string) ($payload['inscription'] ?? ''));
            $wishes = trim((string) ($payload['wishes'] ?? ''));
            $photos = [];

            if (! empty($payload['preview_image_base64'])) {
                $photos[] = 'data:image/jpeg;base64,'.$payload['preview_image_base64'];
            }

            $details = [
                'Надпись: '.($inscription !== '' ? $inscription : 'без текста'),
                'Пожелания: '.($wishes !== '' ? $wishes : 'не указаны'),
                'Вес: '.$weightTitle,
            ];

            return [
                'id' => $item->id,
                'item_type' => 'custom_cake',
                'dessert_id' => null,
                'custom_cake_cart_item_id' => $item->id,
                'qty' => $item->qty,
                'price' => $item->price,
                'sum' => $item->qty * $item->price,
                'custom_cake' => $payload,
                'dessert' => [
                    'id' => null,
                    'name' => $designName !== '' ? "Торт «{$designName}»" : 'Торт с надписью',
                    'description' => implode("\n", array_filter($details)),
                    'photos' => $this->proxiedPhotos($photos, $proxyBaseUrl),
                ],
            ];
        });

        $mergedItems = $regularItems
            ->concat($customItems)
            ->values();

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'items' => $mergedItems,
            'total' => $mergedItems->sum(fn ($i) => $i['sum']),
        ];
    }
}
