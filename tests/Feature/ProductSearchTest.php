<?php

namespace Tests\Feature;

use App\Models\Dessert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_search_checks_name_category_description_and_composition(): void
    {
        Dessert::query()->create([
            'name' => 'Пирожное Павлова',
            'category' => 'dessert',
            'description' => 'Воздушная меренга',
            'composition' => 'Сливки, ягоды',
            'price' => 300,
            'available' => true,
        ]);

        Dessert::query()->create([
            'name' => 'Латте',
            'category' => 'coffee',
            'description' => 'Кофейный напиток',
            'composition' => 'Эспрессо, молоко',
            'price' => 250,
            'available' => true,
        ]);

        $this->getJson('/api/products/search?query='.urlencode('ягоды'))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Пирожное Павлова')
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/products/search?query='.urlencode('coffee'))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Латте')
            ->assertJsonCount(1, 'data');
    }

    public function test_product_search_splits_query_into_terms(): void
    {
        Dessert::query()->create([
            'name' => 'Чизкейк клубника',
            'category' => 'cake',
            'description' => 'Нежный сырный десерт',
            'composition' => 'Сыр, клубничный соус',
            'price' => 350,
            'available' => true,
        ]);

        Dessert::query()->create([
            'name' => 'Клубничный раф',
            'category' => 'coffee',
            'description' => 'Горячий напиток',
            'composition' => 'Кофе, сливки, клубника',
            'price' => 260,
            'available' => true,
        ]);

        $this->getJson('/api/products/search?query='.urlencode('чизкейк клубника'))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Чизкейк клубника')
            ->assertJsonCount(1, 'data');
    }

    public function test_product_search_hides_unavailable_and_archived_items(): void
    {
        Dessert::query()->create([
            'name' => 'Скрытый торт',
            'category' => 'cake',
            'description' => 'Недоступен',
            'composition' => 'Сахар',
            'price' => 500,
            'available' => false,
        ]);

        Dessert::query()->create([
            'name' => 'Архивный торт',
            'category' => 'cake',
            'description' => 'В архиве',
            'composition' => 'Сахар',
            'price' => 500,
            'available' => true,
            'archived' => true,
        ]);

        $this->getJson('/api/products/search?query='.urlencode('торт'))
            ->assertOk()
            ->assertJsonPath('data', []);
    }
}
