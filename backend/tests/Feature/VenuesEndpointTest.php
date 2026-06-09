<?php

namespace Tests\Feature;

use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenuesEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_venues_with_numeric_coordinates(): void
    {
        Venue::create([
            'name' => 'Stereolux',
            'slug' => 'stereolux',
            'address_line' => '4 Boulevard Leon Bureau',
            'postal_code' => '44200',
            'city' => 'Nantes',
            'capacity' => 1200,
            'latitude' => 47.2027,
            'longitude' => -1.565,
        ]);

        $response = $this->getJson('/api/v1/venues');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'stereolux')
            ->assertJsonPath('data.0.city', 'Nantes')
            ->assertJsonPath('data.0.latitude', fn (mixed $value): bool => is_float($value) && abs($value - 47.2027) < 0.0001)
            ->assertJsonPath('data.0.longitude', fn (mixed $value): bool => is_float($value) && abs($value - (-1.565)) < 0.0001);
    }
}
