<?php

namespace Tests\Feature;

use App\Models\CakeDesign;
use App\Models\Cart;
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

    public function test_custom_cake_can_be_ordered_from_regular_cart_with_other_items(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $dessert = Dessert::query()->create([
            'name' => 'Cheesecake',
            'category' => 'cake',
            'description' => 'Classic',
            'price' => 300,
            'photos' => [],
            'available' => true,
            'archived' => false,
        ]);

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
            ],
            'price' => 250,
            'available' => true,
        ]);

        $this->postJson('/api/cart/items', [
            'dessert_id' => $dessert->id,
            'qty' => 2,
        ])->assertSuccessful();

        $this->postJson('/api/cart/custom-cakes', [
            'qty' => 2,
            'custom_cake' => [
                'design_id' => 'berry-cake',
                'design_name' => 'Berry Cake',
                'weight_title' => '0,8 кг',
                'weight_grams' => 800,
                'inscription' => 'Hi',
            ],
        ])
            ->assertSuccessful()
            ->assertJsonPath('data.items.1.item_type', 'custom_cake')
            ->assertJsonPath('data.items.1.qty', 2)
            ->assertJsonPath('data.total', 4600);

        $this->postJson('/api/orders', [
            'payment_mode' => Order::PAYMENT_MODE_CARD,
            'delivery_mode' => Order::DELIVERY_MODE_PICKUP,
            'phone' => '+70000000000',
        ])
            ->assertCreated()
            ->assertJsonPath('data.items_count', 4)
            ->assertJsonPath('data.subtotal_price', 4600)
            ->assertJsonPath('data.total_price', 4600);

        $cart = Cart::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(0, $cart->items()->count());
        $this->assertSame(0, $cart->customCakeItems()->count());
        $this->assertSame(1, Dessert::query()->where('category', 'custom_cake')->count());
    }
}
