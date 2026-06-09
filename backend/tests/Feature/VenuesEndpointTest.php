<?php

namespace Tests\Feature;

use App\Models\Event;
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

    public function test_it_includes_the_next_upcoming_published_event_on_each_venue(): void
    {
        $venue = Venue::create([
            'name' => 'Stereolux',
            'slug' => 'stereolux',
            'address_line' => '4 Boulevard Leon Bureau',
            'postal_code' => '44200',
            'city' => 'Nantes',
        ]);

        // Evenement passe et publie : doit etre ignore.
        Event::create([
            'title' => 'Concert passe',
            'slug' => 'concert-passe',
            'description' => 'Deja termine.',
            'starts_at' => now()->subWeek(),
            'price_cents' => 1500,
            'is_published' => true,
            'venue_id' => $venue->id,
        ]);

        // Evenement a venir plus lointain : pas le prochain.
        Event::create([
            'title' => 'Concert lointain',
            'slug' => 'concert-lointain',
            'description' => 'Plus tard.',
            'starts_at' => now()->addWeeks(3),
            'price_cents' => 2000,
            'is_published' => true,
            'venue_id' => $venue->id,
        ]);

        // Evenement a venir le plus proche : le prochain attendu.
        Event::create([
            'title' => 'Concert imminent',
            'slug' => 'concert-imminent',
            'description' => 'Bientot.',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(3),
            'price_cents' => 1800,
            'is_published' => true,
            'venue_id' => $venue->id,
        ]);

        // Brouillon plus proche encore : non publie, doit etre ignore.
        Event::create([
            'title' => 'Brouillon',
            'slug' => 'brouillon',
            'description' => 'Pas publie.',
            'starts_at' => now()->addHours(2),
            'is_published' => false,
            'venue_id' => $venue->id,
        ]);

        $this->getJson('/api/v1/venues')
            ->assertOk()
            ->assertJsonPath('data.0.next_event.title', 'Concert imminent')
            ->assertJsonPath('data.0.next_event.price_cents', 1800);
    }

    public function test_next_event_is_null_when_a_venue_has_no_upcoming_event(): void
    {
        $venue = Venue::create([
            'name' => 'La Cantine',
            'slug' => 'la-cantine',
            'address_line' => '12 Quai de la Fosse',
            'postal_code' => '44000',
        ]);

        Event::create([
            'title' => 'Concert passe',
            'slug' => 'concert-passe',
            'description' => 'Termine.',
            'starts_at' => now()->subDay(),
            'price_cents' => 1500,
            'is_published' => true,
            'venue_id' => $venue->id,
        ]);

        $this->getJson('/api/v1/venues')
            ->assertOk()
            ->assertJsonPath('data.0.next_event', null);
    }
}
