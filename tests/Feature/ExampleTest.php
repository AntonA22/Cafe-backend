<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_endpoint_returns_successful_response(): void
    {
        $response = $this->getJson('/api/products');

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }
}
