<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use App\Models\Viree;
use App\Services\BadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Narration IA en fallback local (pas d'appel réseau) et météo simulée :
        // la clôture paresseuse peut se déclencher pendant un check-in.
        config(['services.mistral.key' => '']);
        Http::fake([
            'api.open-meteo.com/*' => Http::response(['current' => [
                'temperature_2m' => 14.0, 'apparent_temperature' => 12.5,
                'relative_humidity_2m' => 70, 'weather_code' => 3,
                'wind_speed_10m' => 8.0, 'is_day' => 0,
            ]]),
        ]);
    }

    public function test_checkin_requires_authentication(): void
    {
        $venue = Venue::factory()->create();

        $this->postJson("/api/v1/venues/{$venue->slug}/checkin")->assertUnauthorized();
    }

    public function test_checkin_returns_404_for_an_unknown_venue(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/venues/nulle-part/checkin')->assertNotFound();
    }

    public function test_first_checkin_starts_a_viree(): void
    {
        $user = User::factory()->create();
        $venue = Venue::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/venues/{$venue->slug}/checkin");

        $response->assertCreated()
            ->assertJsonPath('data.status', 'en_cours')
            ->assertJsonPath('data.checkins.0.venue.slug', $venue->slug)
            ->assertJsonCount(1, 'data.checkins');

        $this->assertNotNull($response->json('data.public_id'));
        $this->assertDatabaseCount('virees', 1);
        $this->assertDatabaseHas('checkins', ['user_id' => $user->id, 'venue_id' => $venue->id]);
    }

    public function test_checkin_at_another_venue_joins_the_active_viree(): void
    {
        $user = User::factory()->create();
        [$first, $second] = Venue::factory()->count(2)->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/venues/{$first->slug}/checkin")->assertCreated();
        $response = $this->postJson("/api/v1/venues/{$second->slug}/checkin");

        $response->assertCreated()->assertJsonCount(2, 'data.checkins');
        $this->assertDatabaseCount('virees', 1);
        // Les check-ins sont rendus dans l'ordre de passage.
        $this->assertSame(
            [$first->slug, $second->slug],
            array_column(array_column($response->json('data.checkins'), 'venue'), 'slug'),
        );
    }

    public function test_consecutive_checkin_at_the_same_venue_is_ignored(): void
    {
        $user = User::factory()->create();
        $venue = Venue::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/venues/{$venue->slug}/checkin")->assertCreated();
        $this->postJson("/api/v1/venues/{$venue->slug}/checkin")
            ->assertOk()
            ->assertJsonCount(1, 'data.checkins');

        $this->assertDatabaseCount('checkins', 1);
    }

    public function test_checkin_after_a_long_idle_closes_the_stale_viree_and_starts_a_new_one(): void
    {
        $user = User::factory()->create();
        [$first, $second] = Venue::factory()->count(2)->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/venues/{$first->slug}/checkin")->assertCreated();

        $this->travel(7)->hours();

        $this->postJson("/api/v1/venues/{$second->slug}/checkin")
            ->assertCreated()
            ->assertJsonCount(1, 'data.checkins');

        $this->assertDatabaseCount('virees', 2);
        $this->assertSame(1, Viree::query()->whereNotNull('ended_at')->count());
    }

    public function test_checkin_triggers_badge_evaluation(): void
    {
        $user = User::factory()->create();
        $venue = Venue::factory()->create();
        Sanctum::actingAs($user);

        $this->mock(BadgeService::class)
            ->shouldReceive('evaluate')
            ->once()
            ->andReturn([]);

        $this->postJson("/api/v1/venues/{$venue->slug}/checkin")->assertCreated();
    }
}
