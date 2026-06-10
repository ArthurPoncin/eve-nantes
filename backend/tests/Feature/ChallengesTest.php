<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\Checkin;
use App\Models\User;
use App\Models\Venue;
use App\Models\Viree;
use Database\Seeders\ChallengeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChallengesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChallengeSeeder::class);
        // Clôture de virée hermétique : météo simulée, IA en fallback local.
        config(['services.mistral.key' => '']);
        Http::fake([
            'api.open-meteo.com/*' => Http::response(['current' => [
                'temperature_2m' => 14.0, 'apparent_temperature' => 12.5,
                'relative_humidity_2m' => 70, 'weather_code' => 3,
                'wind_speed_10m' => 8.0, 'is_day' => 0,
            ]]),
        ]);
    }

    public function test_challenges_require_authentication(): void
    {
        $this->getJson('/api/v1/challenges')->assertUnauthorized();
    }

    public function test_active_challenges_start_at_zero_progress(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/v1/challenges')->assertOk()->assertJsonCount(3);

        foreach ($response->json() as $challenge) {
            $this->assertSame(0, $challenge['progress']);
            $this->assertFalse($challenge['completed']);
        }
    }

    public function test_an_out_of_period_challenge_is_not_listed(): void
    {
        Challenge::create([
            'id' => 'defi-perime',
            'label' => 'Défi périmé',
            'description' => 'Trop tard.',
            'icon' => '✗',
            'criteria' => ['type' => 'checkins_count', 'min' => 1],
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(),
        ]);
        Sanctum::actingAs(User::factory()->create());

        $ids = array_column($this->getJson('/api/v1/challenges')->json(), 'id');

        $this->assertNotContains('defi-perime', $ids);
    }

    public function test_checkins_move_the_explorateur_challenge_forward(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        [$a, $b] = Venue::factory()->count(2)->create();

        $this->postJson("/api/v1/venues/{$a->slug}/checkin")->assertCreated();
        $this->postJson("/api/v1/venues/{$b->slug}/checkin")->assertCreated();

        $challenges = collect($this->getJson('/api/v1/challenges')->json());
        $explorateur = $challenges->firstWhere('id', 'explorateur-du-mois');

        $this->assertSame(2, $explorateur['progress']);
        $this->assertFalse($explorateur['completed']);
    }

    public function test_venues_already_visited_before_the_window_do_not_count_as_new(): void
    {
        $user = User::factory()->create();
        $venue = Venue::factory()->create();

        // Déjà visité le mois dernier : pas « nouveau » ce mois-ci.
        Checkin::factory()->create([
            'viree_id' => Viree::factory()->create(['user_id' => $user->id])->id,
            'user_id' => $user->id,
            'venue_id' => $venue->id,
            'happened_at' => now()->subMonth(),
        ]);

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/venues/{$venue->slug}/checkin")->assertCreated();

        $explorateur = collect($this->getJson('/api/v1/challenges')->json())
            ->firstWhere('id', 'explorateur-du-mois');

        $this->assertSame(0, $explorateur['progress']);
    }

    public function test_closing_virees_completes_the_marathonien_challenge_once(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $venues = Venue::factory()->count(3)->create();

        foreach ($venues as $venue) {
            $this->postJson("/api/v1/venues/{$venue->slug}/checkin")->assertCreated();
            $this->postJson('/api/v1/virees/current/close')->assertOk();
        }

        $marathonien = collect($this->getJson('/api/v1/challenges')->json())
            ->firstWhere('id', 'marathonien');

        $this->assertSame(3, $marathonien['progress']);
        $this->assertTrue($marathonien['completed']);
        $this->assertNotNull($marathonien['completed_at']);

        // L'évaluation est idempotente : completed_at ne bouge plus.
        $firstCompletion = $marathonien['completed_at'];
        $this->travel(1)->hours();
        $this->postJson("/api/v1/venues/{$venues[0]->slug}/checkin")->assertCreated();

        $again = collect($this->getJson('/api/v1/challenges')->json())
            ->firstWhere('id', 'marathonien');
        $this->assertSame($firstCompletion, $again['completed_at']);
    }

    public function test_kilometers_are_floored_per_completed_viree_distance(): void
    {
        $user = User::factory()->create();
        Viree::factory()->create([
            'user_id' => $user->id,
            'started_at' => now()->subHours(5),
            'ended_at' => now()->subHours(2),
            'distance_m' => 4600,
        ]);
        Sanctum::actingAs($user);

        $semelles = collect($this->getJson('/api/v1/challenges')->json())
            ->firstWhere('id', 'semelles-de-feu');

        $this->assertSame(4, $semelles['progress']);
    }
}
