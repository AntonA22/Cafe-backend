<?php

namespace Tests\Feature;

use App\Models\Dessert;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_products_include_unavailable_items(): void
    {
        $admin = User::factory()->create(['is_staff' => true]);
        Sanctum::actingAs($admin);

        Dessert::query()->create([
            'name' => 'Hidden cake',
            'category' => 'cake',
            'description' => 'Hidden',
            'composition' => 'Sugar',
            'price' => 100,
            'available' => false,
        ]);

        $this->getJson('/api/admin/products')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Hidden cake')
            ->assertJsonPath('data.0.available', false);
    }

    public function test_admin_products_hide_archived_items(): void
    {
        $admin = User::factory()->create(['is_staff' => true]);
        Sanctum::actingAs($admin);

        Dessert::query()->create([
            'name' => 'Visible unavailable cake',
            'category' => 'cake',
            'description' => 'Hidden from clients only',
            'composition' => 'Sugar',
            'price' => 100,
            'available' => false,
        ]);

        Dessert::query()->create([
            'name' => 'Archived cake',
            'category' => 'cake',
            'description' => 'Hidden everywhere',
            'composition' => 'Sugar',
            'price' => 100,
            'available' => true,
            'archived' => true,
        ]);

        $response = $this->getJson('/api/admin/products')
            ->assertOk();

        $this->assertSame(['Visible unavailable cake'], $response->json('data.*.name'));
    }

    public function test_public_products_hide_unavailable_items(): void
    {
        Dessert::query()->create([
            'name' => 'Hidden cake',
            'category' => 'cake',
            'description' => 'Hidden',
            'composition' => 'Sugar',
            'price' => 100,
            'available' => false,
        ]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_public_products_hide_archived_items(): void
    {
        Dessert::query()->create([
            'name' => 'Archived cake',
            'category' => 'cake',
            'description' => 'Hidden everywhere',
            'composition' => 'Sugar',
            'price' => 100,
            'available' => true,
            'archived' => true,
        ]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_admin_delete_archives_product_and_keeps_order_items(): void
    {
        $admin = User::factory()->create(['is_staff' => true]);
        $user = User::factory()->create();
        Sanctum::actingAs($admin);

        $dessert = Dessert::query()->create([
            'name' => 'Ordered cake',
            'category' => 'cake',
            'description' => 'Already ordered',
            'composition' => 'Sugar',
            'price' => 100,
            'available' => true,
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_NEW,
            'items_count' => 1,
            'subtotal_price' => 100,
            'delivery_fee' => 0,
            'bonus_points_spent' => 0,
            'bonus_points_earned' => 0,
            'total_price' => 100,
            'payment_mode' => Order::PAYMENT_MODE_CARD,
            'delivery_mode' => Order::DELIVERY_MODE_PICKUP,
            'leave_at_door' => false,
            'customer_phone' => '+70000000000',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'dessert_id' => $dessert->id,
            'qty' => 1,
            'price' => 100,
            'sum' => 100,
        ]);

        $this->deleteJson("/api/admin/products/{$dessert->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Product archived');

        $this->assertDatabaseHas('desserts', [
            'id' => $dessert->id,
            'archived' => true,
            'available' => false,
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'dessert_id' => $dessert->id,
        ]);

        $this->getJson('/api/admin/products')
            ->assertOk()
            ->assertJsonPath('data', []);
    }
}
