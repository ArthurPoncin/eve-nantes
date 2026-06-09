<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FavoritesTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'username' => 'arthur',
            'email' => 'arthur@example.com',
            'password' => 'password123',
        ]);
    }

    public function test_a_user_can_favorite_a_venue_and_list_it(): void
    {
        Sanctum::actingAs($this->makeUser());

        $venue = Venue::factory()->create();

        $this->postJson("/api/v1/venues/{$venue->slug}/favorite")
            ->assertCreated();

        $this->getJson('/api/v1/favorites')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $venue->slug);
    }

    public function test_a_user_can_unfavorite_a_venue(): void
    {
        Sanctum::actingAs($this->makeUser());

        $venue = Venue::factory()->create();

        $this->postJson("/api/v1/venues/{$venue->slug}/favorite")
            ->assertCreated();

        $this->deleteJson("/api/v1/venues/{$venue->slug}/favorite")
            ->assertNoContent();

        $this->getJson('/api/v1/favorites')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_favoriting_twice_keeps_a_single_link(): void
    {
        Sanctum::actingAs($this->makeUser());

        $venue = Venue::factory()->create();

        $this->postJson("/api/v1/venues/{$venue->slug}/favorite")->assertCreated();
        $this->postJson("/api/v1/venues/{$venue->slug}/favorite")->assertCreated();

        $this->getJson('/api/v1/favorites')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_favorites_require_authentication(): void
    {
        $this->getJson('/api/v1/favorites')->assertUnauthorized();
    }
}
