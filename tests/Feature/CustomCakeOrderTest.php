<?php

namespace Tests\Feature;

use App\Models\CakeDesign;
use App\Models\Dessert;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomCakeOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_cake_order_archives_generated_product_and_prices_by_requested_weight(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        CakeDesign::query()->create([
            'slug' => 'berry-cake',
            'name' => 'Berry Cake',
            'subtitle' => 'Custom berry cake',
            'filling' => 'Vanilla',
            'accent' => 'Berries',
            'composition' => 'Vanilla, berries',
            'storage' => '0...+6 C',
            'weight_grams' => 1200,
            'available_weights' => [
                ['title' => '0,8 кг', 'grams' => 800],
                ['title' => '1,2 кг', 'grams' => 1200],
                ['title' => '1,5 кг', 'grams' => 1500],
            ],
            'price' => 250,
            'available' => true,
        ]);

        $this->postJson('/api/orders', [
            'payment_mode' => Order::PAYMENT_MODE_CARD,
            'delivery_mode' => Order::DELIVERY_MODE_PICKUP,
            'phone' => '+70000000000',
            'custom_cake' => [
                'design_id' => 'berry-cake',
                'design_name' => 'Berry Cake',
                'weight_title' => '0,8 кг',
                'weight_grams' => 800,
                'inscription' => 'Hi',
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.price', 2000)
            ->assertJsonPath('data.total_price', 2000);

        $generatedCake = Dessert::query()
            ->where('category', 'custom_cake')
            ->firstOrFail();

        $this->assertFalse($generatedCake->available);
        $this->assertTrue($generatedCake->archived);
    }

    public function test_custom_cake_order_rejects_unavailable_weight(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        CakeDesign::query()->create([
            'slug' => 'berry-cake',
            'name' => 'Berry Cake',
            'subtitle' => 'Custom berry cake',
            'filling' => 'Vanilla',
            'accent' => 'Berries',
            'composition' => 'Vanilla, berries',
            'storage' => '0...+6 C',
            'weight_grams' => 1200,
            'available_weights' => [
                ['title' => '0,8 кг', 'grams' => 800],
                ['title' => '1,2 кг', 'grams' => 1200],
                ['title' => '1,5 кг', 'grams' => 1500],
            ],
            'price' => 250,
            'available' => true,
        ]);

        $this->postJson('/api/orders', [
            'payment_mode' => Order::PAYMENT_MODE_CARD,
            'delivery_mode' => Order::DELIVERY_MODE_PICKUP,
            'phone' => '+70000000000',
            'custom_cake' => [
                'design_id' => 'berry-cake',
                'design_name' => 'Berry Cake',
                'weight_title' => '900 г',
                'weight_grams' => 900,
                'inscription' => 'Hi',
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'Выбранный вес торта недоступен.');
    }
}
