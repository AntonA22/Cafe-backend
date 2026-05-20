<?php

namespace App\Services;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Throwable;

class FirebaseNotificationService
{
    public function __construct(private Messaging $messaging) {}

    public function sendOrderStatusUpdate(
        string $fcmToken,
        string $orderId,
        string $status,
        ?string $deliveryMode = null
    ): void
    {
        $isPickup = $deliveryMode === 'pickup';

        $titles = $isPickup
            ? [
                'processing' => 'Заказ принят',
                'shipped' => 'Заказ готов к выдаче',
                'delivered' => 'Заказ выдан',
                'cancelled' => 'Заказ отменён',
            ]
            : [
                'processing' => 'Заказ принят',
                'shipped' => 'Заказ в пути',
                'delivered' => 'Заказ доставлен',
                'cancelled' => 'Заказ отменён',
            ];

        $bodies = $isPickup
            ? [
                'processing' => 'Ваш заказ принят и готовится.',
                'shipped' => 'Ваш заказ готов. Заберите его в кофейне на Проспекте Мира, 95с1.',
                'delivered' => 'Ваш заказ выдан. Спасибо, что выбрали нас!',
                'cancelled' => 'Ваш заказ был отменён.',
            ]
            : [
                'processing' => 'Ваш заказ принят и готовится.',
                'shipped' => 'Ваш заказ передан курьеру.',
                'delivered' => 'Ваш заказ доставлен. Приятного аппетита!',
                'cancelled' => 'Ваш заказ был отменён.',
            ];

        $title = $titles[$status] ?? 'Статус заказа изменён';
        $body = $bodies[$status] ?? "Новый статус: {$status}";

        $message = CloudMessage::new()->withToken($fcmToken)
            ->withNotification(Notification::create($title, $body))
            ->withData([
                'order_id' => $orderId,
                'status' => $status,
                'delivery_mode' => $deliveryMode ?? '',
            ]);

        try {
            $this->messaging->send($message);
        } catch (Throwable) {
            // Токен устарел или недействителен — молча пропускаем
        }
    }
}
