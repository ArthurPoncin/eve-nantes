<?php

namespace Tests\Feature;

use App\Models\Checkin;
use App\Models\User;
use App\Models\Venue;
use App\Models\Viree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeStatsTest extends TestCase
{
    use RefreshDatabase;

    /** Une virée bouclée avec un check-in par lieu donné. */
    private function makeViree(User $user, array $venues, array $attributes = []): Viree
    {
        $viree = Viree::factory()->create(array_merge(['user_id' => $user->id], $attributes));

        foreach ($venues as $venue) {
            Checkin::factory()->create([
                'viree_id' => $viree->id,
                'user_id' => $user->id,
                'venue_id' => $venue->id,
            ]);
        }

        return $viree;
    }

    public function test_stats_require_authentication(): void
    {
        $this->getJson('/api/v1/me/stats')->assertUnauthorized();
    }

    public function test_a_fresh_user_gets_zeroed_stats(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/me/stats')
            ->assertOk()
            ->assertJson([
                'virees_count' => 0,
                'checkins_count' => 0,
                'distinct_venues' => 0,
                'total_km' => 0,
                'streak_weeks' => 0,
                'dominant_mood' => null,
                'moods' => [],
                'favorite_venue' => null,
                'heatmap' => [],
            ]);
    }

    public function test_counts_kilometers_moods_and_favorite_venue(): void
    {
        $user = User::factory()->create();
        $festif = Venue::factory()->create(['mood' => 'festif']);
        $chill = Venue::factory()->create(['mood' => 'chill']);

        $this->makeViree($user, [$festif, $chill], ['distance_m' => 1500]);
        $this->makeViree($user, [$festif], ['distance_m' => 800]);
        // Une virée encore en cours ne compte ni dans le total ni dans les km.
        Viree::factory()->active()->create(['user_id' => $user->id]);
        // Les stats d'un autre utilisateur ne fuient pas.
        $this->makeViree(User::factory()->create(), [$festif], ['distance_m' => 99000]);

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/v1/me/stats')->assertOk();

        $response->assertJsonPath('virees_count', 2)
            ->assertJsonPath('checkins_count', 3)
            ->assertJsonPath('distinct_venues', 2)
            ->assertJsonPath('total_km', 2.3)
            ->assertJsonPath('dominant_mood', 'festif')
            ->assertJsonPath('moods.0', ['mood' => 'festif', 'count' => 2])
            ->assertJsonPath('moods.1', ['mood' => 'chill', 'count' => 1])
            ->assertJsonPath('favorite_venue.slug', $festif->slug)
            ->assertJsonPath('favorite_venue.checkins_count', 2);

        $this->assertCount(2, $response->json('heatmap'));
    }

    public function test_streak_counts_consecutive_weeks_with_a_viree(): void
    {
        $user = User::factory()->create();
        $venue = Venue::factory()->create();

        // Trois semaines consécutives (l'actuelle comprise), un trou avant.
        foreach ([0, 1, 2, 4] as $weeksAgo) {
            $this->makeViree($user, [$venue], [
                'started_at' => now()->subWeeks($weeksAgo),
                'ended_at' => now()->subWeeks($weeksAgo)->addHours(3),
            ]);
        }

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me/stats')
            ->assertOk()
            ->assertJsonPath('streak_weeks', 3);
    }

    public function test_an_empty_current_week_does_not_break_the_streak(): void
    {
        $user = User::factory()->create();
        $venue = Venue::factory()->create();

        // Virées les deux semaines précédentes, rien cette semaine.
        foreach ([1, 2] as $weeksAgo) {
            $this->makeViree($user, [$venue], [
                'started_at' => now()->subWeeks($weeksAgo),
                'ended_at' => now()->subWeeks($weeksAgo)->addHours(3),
            ]);
        }

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me/stats')
            ->assertOk()
            ->assertJsonPath('streak_weeks', 2);
    }
}
