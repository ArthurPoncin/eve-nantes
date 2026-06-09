<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventsEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_published_events_with_venue_and_categories(): void
    {
        $venue = Venue::create([
            'name' => 'Stereolux',
            'slug' => 'stereolux',
            'address_line' => '4 Boulevard Leon Bureau',
            'postal_code' => '44200',
            'city' => 'Nantes',
            'capacity' => 1200,
        ]);

        $category = Category::create(['name' => 'Musique', 'slug' => 'musique']);

        $published = Event::create([
            'title' => 'Concert electro',
            'slug' => 'concert-electro',
            'description' => 'Soiree electro avec artistes locaux.',
            'starts_at' => '2026-07-01 21:00:00',
            'ends_at' => '2026-07-02 01:00:00',
            'price_cents' => 1800,
            'is_published' => true,
            'venue_id' => $venue->id,
        ]);
        $published->categories()->attach($category->id);

        Event::create([
            'title' => 'Brouillon prive',
            'slug' => 'brouillon-prive',
            'description' => 'Pas encore publie.',
            'starts_at' => '2026-07-05 21:00:00',
            'is_published' => false,
            'venue_id' => $venue->id,
        ]);

        $response = $this->getJson('/api/v1/events');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Concert electro')
            ->assertJsonPath('data.0.venue.slug', 'stereolux')
            ->assertJsonPath('data.0.categories.0.slug', 'musique');
    }
}
