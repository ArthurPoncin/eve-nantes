<?php

namespace Tests\Feature;

use App\Models\Checkin;
use App\Models\User;
use App\Models\Venue;
use App\Models\Viree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PilierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Le classement est mis en cache 60 s : chaque test repart de zéro.
        Cache::flush();
    }

    private function checkIn(User $user, Venue $venue, int $daysAgo = 1): void
    {
        Checkin::factory()->create([
            'viree_id' => Viree::factory()->create(['user_id' => $user->id])->id,
            'user_id' => $user->id,
            'venue_id' => $venue->id,
            'happened_at' => now()->subDays($daysAgo),
        ]);
    }

    public function test_pilier_is_public_and_null_without_checkins(): void
    {
        $venue = Venue::factory()->create();

        $this->getJson("/api/v1/venues/{$venue->slug}/pilier")
            ->assertOk()
            ->assertJsonPath('pilier', null);
    }

    public function test_one_checkin_is_not_enough_for_the_throne(): void
    {
        $venue = Venue::factory()->create();
        $this->checkIn(User::factory()->create(), $venue);

        $this->getJson("/api/v1/venues/{$venue->slug}/pilier")
            ->assertOk()
            ->assertJsonPath('pilier', null);
    }

    public function test_the_top_checkiner_takes_the_throne(): void
    {
        $venue = Venue::factory()->create();
        $pilier = User::factory()->create(['username' => 'reine-de-la-nuit']);
        $rival = User::factory()->create();

        $this->checkIn($pilier, $venue, 10);
        $this->checkIn($pilier, $venue, 5);
        $this->checkIn($pilier, $venue, 2);
        $this->checkIn($rival, $venue, 3);
        $this->checkIn($rival, $venue, 1);

        $this->getJson("/api/v1/venues/{$venue->slug}/pilier")
            ->assertOk()
            ->assertJsonPath('pilier.username', 'reine-de-la-nuit')
            ->assertJsonPath('pilier.checkins_count', 3);
    }

    public function test_checkins_older_than_90_days_are_ignored(): void
    {
        $venue = Venue::factory()->create();
        $user = User::factory()->create();

        $this->checkIn($user, $venue, 120);
        $this->checkIn($user, $venue, 100);

        $this->getJson("/api/v1/venues/{$venue->slug}/pilier")
            ->assertOk()
            ->assertJsonPath('pilier', null);
    }

    public function test_tie_goes_to_the_earliest_first_checkin(): void
    {
        $venue = Venue::factory()->create();
        $ancien = User::factory()->create(['username' => 'l-ancien']);
        $nouveau = User::factory()->create(['username' => 'le-nouveau']);

        $this->checkIn($ancien, $venue, 30);
        $this->checkIn($ancien, $venue, 8);
        $this->checkIn($nouveau, $venue, 9);
        $this->checkIn($nouveau, $venue, 7);

        $this->getJson("/api/v1/venues/{$venue->slug}/pilier")
            ->assertOk()
            ->assertJsonPath('pilier.username', 'l-ancien');
    }

    public function test_unknown_venue_returns_404(): void
    {
        $this->getJson('/api/v1/venues/nulle-part/pilier')->assertNotFound();
    }

    public function test_the_ranking_is_cached(): void
    {
        $venue = Venue::factory()->create();
        $user = User::factory()->create(['username' => 'cache-toi']);
        $this->checkIn($user, $venue, 2);
        $this->checkIn($user, $venue, 1);

        $this->getJson("/api/v1/venues/{$venue->slug}/pilier")
            ->assertJsonPath('pilier.username', 'cache-toi');

        // Un nouveau prétendant n'apparaît pas tant que le cache (60 s) tient.
        $rival = User::factory()->create();
        foreach (range(1, 5) as $day) {
            $this->checkIn($rival, $venue, $day);
        }

        $this->getJson("/api/v1/venues/{$venue->slug}/pilier")
            ->assertJsonPath('pilier.username', 'cache-toi');
    }
}
