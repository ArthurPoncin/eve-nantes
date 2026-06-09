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

    public function test_it_exposes_the_venue_mood(): void
    {
        Venue::create([
            'name' => 'Le Ferrailleur',
            'slug' => 'le-ferrailleur',
            'address_line' => '21 Quai des Antilles',
            'postal_code' => '44200',
            'mood' => 'festif',
        ]);

        $this->getJson('/api/v1/venues')
            ->assertOk()
            ->assertJsonPath('data.0.mood', 'festif');
    }

    public function test_it_filters_venues_by_mood(): void
    {
        Venue::create([
            'name' => 'Le Ferrailleur',
            'slug' => 'le-ferrailleur',
            'address_line' => '21 Quai des Antilles',
            'postal_code' => '44200',
            'mood' => 'festif',
        ]);

        Venue::create([
            'name' => 'La Cantine',
            'slug' => 'la-cantine',
            'address_line' => '12 Quai de la Fosse',
            'postal_code' => '44000',
            'mood' => 'chill',
        ]);

        $this->getJson('/api/v1/venues?mood=festif')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.mood', 'festif');
    }

    public function test_it_returns_all_venues_without_mood_filter(): void
    {
        Venue::create([
            'name' => 'Le Ferrailleur',
            'slug' => 'le-ferrailleur',
            'address_line' => '21 Quai des Antilles',
            'postal_code' => '44200',
            'mood' => 'festif',
        ]);

        Venue::create([
            'name' => 'La Cantine',
            'slug' => 'la-cantine',
            'address_line' => '12 Quai de la Fosse',
            'postal_code' => '44000',
            'mood' => 'chill',
        ]);

        $this->getJson('/api/v1/venues')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_it_rejects_an_unknown_mood(): void
    {
        $this->getJson('/api/v1/venues?mood=banger')
            ->assertStatus(422)
            ->assertJsonValidationErrors('mood');
    }
}
