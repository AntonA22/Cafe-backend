<?php

namespace Tests\Feature;

use App\Models\CakeDesign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CakeDesignAvailableWeightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cake_designs_include_configured_available_weights(): void
    {
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
                ['title' => '0,9 кг', 'grams' => 900],
                ['title' => '1,3 кг', 'grams' => 1300],
                ['title' => '1,7 кг', 'grams' => 1700],
            ],
            'price' => 250,
            'available' => true,
        ]);

        $this->getJson('/api/cake-designs')
            ->assertOk()
            ->assertJsonPath('data.0.availableWeights.0.title', '0,9 кг')
            ->assertJsonPath('data.0.availableWeights.0.grams', 900)
            ->assertJsonPath('data.0.availableWeights.1.title', '1,3 кг')
            ->assertJsonPath('data.0.availableWeights.1.grams', 1300)
            ->assertJsonPath('data.0.availableWeights.2.title', '1,7 кг')
            ->assertJsonPath('data.0.availableWeights.2.grams', 1700)
            ->assertJsonPath('data.0.pricePerKg', 2500);
    }
}
