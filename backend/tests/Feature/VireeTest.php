<?php

namespace Tests\Feature;

use App\Models\Checkin;
use App\Models\User;
use App\Models\Venue;
use App\Models\Viree;
use App\Services\BadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VireeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Fallback IA local : la narration est déterministe et hors réseau.
        config(['services.mistral.key' => '']);
    }

    private function fakeWeather(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response(['current' => [
                'temperature_2m' => 14.0, 'apparent_temperature' => 12.5,
                'relative_humidity_2m' => 70, 'weather_code' => 3,
                'wind_speed_10m' => 8.0, 'is_day' => 0,
            ]]),
        ]);
    }

    /** Une virée active avec un check-in par lieu donné, dans l'ordre. */
    private function startViree(User $user, Venue ...$venues): Viree
    {
        $viree = Viree::factory()->active()->create(['user_id' => $user->id]);

        foreach ($venues as $i => $venue) {
            Checkin::factory()->create([
                'viree_id' => $viree->id,
                'user_id' => $user->id,
                'venue_id' => $venue->id,
                'happened_at' => now()->subMinutes(30 - $i),
            ]);
        }

        return $viree;
    }

    public function test_current_returns_null_without_an_active_viree(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/virees/current')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_current_returns_the_active_viree_with_its_checkins(): void
    {
        $user = User::factory()->create();
        [$first, $second] = Venue::factory()->count(2)->create();
        $this->startViree($user, $first, $second);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/virees/current')
            ->assertOk()
            ->assertJsonPath('data.status', 'en_cours')
            ->assertJsonCount(2, 'data.checkins')
            ->assertJsonPath('data.checkins.0.venue.slug', $first->slug)
            ->assertJsonPath('data.checkins.1.venue.slug', $second->slug);
    }

    public function test_close_computes_distance_duration_and_moods(): void
    {
        $this->fakeWeather();
        $user = User::factory()->create();
        $first = Venue::factory()->create(['latitude' => 47.21, 'longitude' => -1.55, 'mood' => 'festif']);
        $second = Venue::factory()->create(['latitude' => 47.22, 'longitude' => -1.56, 'mood' => 'chill']);
        $viree = $this->startViree($user, $first, $second);
        $viree->update(['started_at' => now()->subHours(2)]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/virees/current/close');

        $response->assertOk()
            ->assertJsonPath('data.status', 'terminee')
            ->assertJsonPath('data.stats.venues', 2)
            ->assertJsonPath('data.stats.duration_min', 120)
            ->assertJsonPath('data.stats.moods', ['festif', 'chill']);

        // ~1,34 km à vol d'oiseau entre les deux points (haversine).
        $this->assertEqualsWithDelta(1345, $response->json('data.stats.distance_m'), 30);
    }

    public function test_close_persists_weather_and_a_narrative(): void
    {
        $this->fakeWeather();
        $user = User::factory()->create();
        $venue = Venue::factory()->create(['name' => 'Le Chat Noir']);
        $this->startViree($user, $venue);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/virees/current/close');

        $response->assertOk()
            ->assertJsonPath('data.weather.condition', 'Couvert');
        // Fallback IA déterministe : la narration cite le lieu.
        $this->assertStringContainsString('Le Chat Noir', $response->json('data.narrative'));
    }

    public function test_close_survives_a_weather_outage(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response([], 500)]);
        $user = User::factory()->create();
        $this->startViree($user, Venue::factory()->create());
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/virees/current/close')
            ->assertOk()
            ->assertJsonPath('data.status', 'terminee')
            ->assertJsonPath('data.weather', null);
    }

    public function test_close_without_an_active_viree_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/virees/current/close')->assertNotFound();
    }

    public function test_current_is_null_after_close(): void
    {
        $this->fakeWeather();
        $user = User::factory()->create();
        $this->startViree($user, Venue::factory()->create());
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/virees/current/close')->assertOk();
        $this->getJson('/api/v1/virees/current')->assertOk()->assertJsonPath('data', null);
    }

    public function test_index_lists_only_my_completed_virees(): void
    {
        $user = User::factory()->create();
        // Une terminée à moi, une active à moi, une terminée à quelqu'un d'autre.
        $mine = Viree::factory()->create(['user_id' => $user->id]);
        Viree::factory()->active()->create(['user_id' => $user->id]);
        Viree::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/virees')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.public_id', $mine->public_id);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/virees')->assertUnauthorized();
    }

    public function test_show_is_public_via_the_share_id(): void
    {
        $viree = Viree::factory()->create();

        $this->getJson("/api/v1/virees/{$viree->public_id}")
            ->assertOk()
            ->assertJsonPath('data.public_id', $viree->public_id)
            ->assertJsonPath('data.status', 'terminee');
    }

    public function test_show_returns_404_for_an_unknown_id(): void
    {
        $this->getJson('/api/v1/virees/00000000-0000-0000-0000-000000000000')->assertNotFound();
    }

    public function test_close_triggers_badge_evaluation(): void
    {
        $this->fakeWeather();
        $user = User::factory()->create();
        $this->startViree($user, Venue::factory()->create());
        Sanctum::actingAs($user);

        $this->mock(BadgeService::class)
            ->shouldReceive('evaluate')
            ->once()
            ->andReturn([]);

        $this->postJson('/api/v1/virees/current/close')->assertOk();
    }
}
