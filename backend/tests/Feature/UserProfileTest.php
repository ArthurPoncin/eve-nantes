<?php

namespace Tests\Feature;

use App\Models\Checkin;
use App\Models\User;
use App\Models\Venue;
use App\Models\Viree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    private function makeVireeFor(User $user, array $attributes = []): Viree
    {
        $viree = Viree::factory()->create(array_merge(['user_id' => $user->id], $attributes));
        Checkin::factory()->create([
            'viree_id' => $viree->id,
            'user_id' => $user->id,
            'venue_id' => Venue::factory()->create()->id,
        ]);

        return $viree;
    }

    public function test_the_public_profile_never_exposes_the_email(): void
    {
        $user = User::factory()->create(['username' => 'noctambule44']);
        $this->makeVireeFor($user, ['distance_m' => 2500]);

        $response = $this->getJson('/api/v1/users/noctambule44')->assertOk();

        $response->assertJsonPath('username', 'noctambule44')
            ->assertJsonPath('stats.virees_count', 1)
            ->assertJsonPath('stats.total_km', 2.5)
            ->assertJsonPath('stats.distinct_venues', 1)
            ->assertJsonPath('is_following', null);

        $this->assertStringNotContainsString(
            $user->email,
            $response->getContent(),
        );
    }

    public function test_recent_virees_are_capped_at_five(): void
    {
        $user = User::factory()->create(['username' => 'fetard']);
        foreach (range(1, 6) as $i) {
            $this->makeVireeFor($user);
        }

        $response = $this->getJson('/api/v1/users/fetard')->assertOk();

        $this->assertCount(5, $response->json('recent_virees'));
        $response->assertJsonPath('stats.virees_count', 6);
    }

    public function test_is_following_reflects_the_viewer(): void
    {
        $star = User::factory()->create(['username' => 'la-star']);
        $me = User::factory()->create();
        $me->following()->attach($star->id);
        Sanctum::actingAs($me);

        $this->getJson('/api/v1/users/la-star')
            ->assertOk()
            ->assertJsonPath('is_following', true)
            ->assertJsonPath('followers_count', 1);
    }

    public function test_unknown_username_returns_404(): void
    {
        $this->getJson('/api/v1/users/personne')->assertNotFound();
    }
}
