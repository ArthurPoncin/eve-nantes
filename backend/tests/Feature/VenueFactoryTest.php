<?php

namespace Tests\Feature;

use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_venue_with_a_valid_mood(): void
    {
        $venue = Venue::factory()->create();

        $this->assertContains($venue->mood, Venue::MOODS);
        $this->assertSame('Nantes', $venue->city);
    }
}
