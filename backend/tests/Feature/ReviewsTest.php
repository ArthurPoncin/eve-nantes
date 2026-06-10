<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $username = 'arthur'): User
    {
        return User::create([
            'username' => $username,
            'email' => "{$username}@example.com",
            'password' => 'password123',
        ]);
    }

    public function test_anyone_can_list_the_reviews_of_a_venue(): void
    {
        $venue = Venue::factory()->create();
        Review::create([
            'user_id' => $this->makeUser('alice')->id,
            'venue_id' => $venue->id,
            'rating' => 5,
            'comment' => 'Dancefloor incroyable.',
        ]);
        Review::create([
            'user_id' => $this->makeUser('bob')->id,
            'venue_id' => $venue->id,
            'rating' => 4,
            'comment' => null,
        ]);

        $response = $this->getJson("/api/v1/venues/{$venue->slug}/reviews");

        $response->assertOk()
            ->assertJsonPath('average', 4.5)
            ->assertJsonPath('count', 2)
            ->assertJsonCount(2, 'reviews')
            // Avis triés du plus récent au plus ancien.
            ->assertJsonPath('reviews.0.username', 'bob')
            ->assertJsonPath('reviews.0.rating', 4)
            ->assertJsonPath('reviews.1.username', 'alice')
            ->assertJsonPath('reviews.1.comment', 'Dancefloor incroyable.');
    }

    public function test_a_venue_without_reviews_returns_an_empty_summary(): void
    {
        $venue = Venue::factory()->create();

        $this->getJson("/api/v1/venues/{$venue->slug}/reviews")
            ->assertOk()
            ->assertExactJson(['average' => null, 'count' => 0, 'reviews' => []]);
    }

    public function test_an_authenticated_user_can_post_a_review(): void
    {
        Sanctum::actingAs($this->makeUser());
        $venue = Venue::factory()->create();

        $this->postJson("/api/v1/venues/{$venue->slug}/reviews", [
            'rating' => 5,
            'comment' => 'Programmation au top.',
        ])
            ->assertCreated()
            ->assertJsonPath('rating', 5)
            ->assertJsonPath('username', 'arthur');

        $this->assertDatabaseHas('reviews', [
            'venue_id' => $venue->id,
            'rating' => 5,
            'comment' => 'Programmation au top.',
        ]);
    }

    public function test_posting_again_replaces_the_previous_review(): void
    {
        Sanctum::actingAs($this->makeUser());
        $venue = Venue::factory()->create();

        $this->postJson("/api/v1/venues/{$venue->slug}/reviews", ['rating' => 2])
            ->assertCreated();
        $this->postJson("/api/v1/venues/{$venue->slug}/reviews", [
            'rating' => 4,
            'comment' => 'Bien mieux ce soir.',
        ])->assertOk();

        $this->assertSame(1, Review::count());
        $this->assertSame(4, Review::first()->rating);
    }

    public function test_the_rating_must_be_between_one_and_five(): void
    {
        Sanctum::actingAs($this->makeUser());
        $venue = Venue::factory()->create();

        $this->postJson("/api/v1/venues/{$venue->slug}/reviews", ['rating' => 6])
            ->assertStatus(422);
        $this->postJson("/api/v1/venues/{$venue->slug}/reviews", ['comment' => 'Sans note'])
            ->assertStatus(422);
    }

    public function test_posting_a_review_requires_authentication(): void
    {
        $venue = Venue::factory()->create();

        $this->postJson("/api/v1/venues/{$venue->slug}/reviews", ['rating' => 3])
            ->assertUnauthorized();
    }

    public function test_listing_reviews_of_an_unknown_venue_returns_404(): void
    {
        $this->getJson('/api/v1/venues/inexistant/reviews')->assertNotFound();
    }
}
