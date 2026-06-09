<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_a_venue_by_slug_with_upcoming_published_events(): void
    {
        $venue = Venue::create([
            'name' => 'Stereolux',
            'slug' => 'stereolux',
            'address_line' => '4 Boulevard Leon Bureau',
            'postal_code' => '44200',
            'city' => 'Nantes',
            'capacity' => 1200,
        ]);

        Event::create([
            'title' => 'Concert electro',
            'slug' => 'concert-electro',
            'description' => 'Soiree electro avec artistes locaux.',
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(4),
            'price_cents' => 1800,
            'is_published' => true,
            'venue_id' => $venue->id,
        ]);

        Event::create([
            'title' => 'Concert passe',
            'slug' => 'concert-passe',
            'description' => 'Deja termine.',
            'starts_at' => now()->subWeek(),
            'price_cents' => 1500,
            'is_published' => true,
            'venue_id' => $venue->id,
        ]);

        Event::create([
            'title' => 'Brouillon prive',
            'slug' => 'brouillon-prive',
            'description' => 'Pas encore publie.',
            'starts_at' => now()->addWeeks(2),
            'is_published' => false,
            'venue_id' => $venue->id,
        ]);

        $response = $this->getJson('/api/v1/venues/stereolux');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'stereolux')
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.title', 'Concert electro');
    }

    public function test_it_returns_404_for_an_unknown_venue(): void
    {
        $this->getJson('/api/v1/venues/inexistant')
            ->assertNotFound();
    }
}
