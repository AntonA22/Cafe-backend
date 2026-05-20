<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientOrderCancelTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_cancel_own_new_order_and_get_bonus_refund(): void
    {
        $customer = User::factory()->create(['bonus_points' => 0]);
        Sanctum::actingAs($customer);

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'status' => Order::STATUS_NEW,
            'items_count' => 1,
            'subtotal_price' => 1000,
            'delivery_fee' => 0,
            'bonus_points_spent' => 100,
            'bonus_points_earned' => 0,
            'total_price' => 900,
            'delivery_mode' => Order::DELIVERY_MODE_PICKUP,
            'payment_mode' => Order::PAYMENT_MODE_CARD,
            'leave_at_door' => false,
        ]);

        $this->patchJson("/api/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_CANCELLED);

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame(100, $customer->fresh()->bonus_points);
        $this->assertNotNull($order->fresh()->bonus_points_refunded_at);
    }

    public function test_customer_cannot_cancel_order_after_processing_started(): void
    {
        $customer = User::factory()->create(['bonus_points' => 0]);
        Sanctum::actingAs($customer);

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'status' => Order::STATUS_PROCESSING,
            'items_count' => 1,
            'subtotal_price' => 1000,
            'delivery_fee' => 0,
            'bonus_points_spent' => 0,
            'bonus_points_earned' => 0,
            'total_price' => 1000,
            'delivery_mode' => Order::DELIVERY_MODE_DELIVERY,
            'payment_mode' => Order::PAYMENT_MODE_CARD,
            'leave_at_door' => false,
        ]);

        $this->patchJson("/api/orders/{$order->id}/cancel")
            ->assertStatus(409);

        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status);
    }
}
