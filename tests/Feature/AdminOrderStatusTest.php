<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivered_order_earns_bonus_points_once_from_reward_base(): void
    {
        $admin = User::factory()->create(['is_staff' => true]);
        $customer = User::factory()->create(['bonus_points' => 0]);
        Sanctum::actingAs($admin);

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'status' => Order::STATUS_SHIPPED,
            'items_count' => 2,
            'subtotal_price' => 1000,
            'delivery_fee' => 149,
            'bonus_points_spent' => 100,
            'bonus_points_earned' => 0,
            'total_price' => 1049,
            'delivery_mode' => Order::DELIVERY_MODE_DELIVERY,
            'payment_mode' => Order::PAYMENT_MODE_CARD,
            'leave_at_door' => false,
        ]);

        $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => Order::STATUS_DELIVERED,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_DELIVERED)
            ->assertJsonPath('data.bonus_points_earned', 45);

        $this->assertSame(45, $customer->fresh()->bonus_points);

        $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => Order::STATUS_DELIVERED,
        ])->assertStatus(409);

        $this->assertSame(45, $customer->fresh()->bonus_points);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $admin = User::factory()->create(['is_staff' => true]);
        $customer = User::factory()->create();
        Sanctum::actingAs($admin);

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'status' => Order::STATUS_NEW,
            'items_count' => 1,
            'subtotal_price' => 500,
            'delivery_fee' => 0,
            'bonus_points_spent' => 0,
            'bonus_points_earned' => 0,
            'total_price' => 500,
            'delivery_mode' => Order::DELIVERY_MODE_PICKUP,
            'payment_mode' => Order::PAYMENT_MODE_CARD,
            'leave_at_door' => false,
        ]);

        $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => Order::STATUS_DELIVERED,
        ])->assertStatus(422);

        $this->assertSame(Order::STATUS_NEW, $order->fresh()->status);
    }
}
