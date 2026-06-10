<?php

namespace Tests\Feature;

use App\Models\Checkin;
use App\Models\User;
use App\Models\Venue;
use App\Models\Viree;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BadgesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BadgeSeeder::class);
        config(['services.resend.key' => 'test-key']);
    }

    private function makeUser(): User
    {
        return User::create([
            'username' => 'arthur',
            'email' => 'arthur@example.com',
            'password' => 'password123',
        ]);
    }

    private function share(Venue $venue, string $mood = 'festif'): void
    {
        $this->postJson('/api/v1/soiree/share', [
            'email' => 'ami@example.com',
            'mood' => $mood,
            'venue_id' => $venue->id,
            'narrative' => 'Une nuit à Nantes.',
        ])->assertStatus(202);
    }

    public function test_badges_require_authentication(): void
    {
        $this->getJson('/api/v1/badges')->assertUnauthorized();
    }

    public function test_all_badges_start_locked(): void
    {
        Sanctum::actingAs($this->makeUser());

        $response = $this->getJson('/api/v1/badges');

        $response->assertOk()->assertJsonCount(8);
        foreach ($response->json() as $badge) {
            $this->assertFalse($badge['unlocked']);
            $this->assertNull($badge['unlocked_at']);
        }
    }

    public function test_posting_a_first_review_unlocks_the_critique_badge(): void
    {
        Sanctum::actingAs($this->makeUser());
        $venue = Venue::factory()->create();

        $this->postJson("/api/v1/venues/{$venue->slug}/reviews", ['rating' => 5])
            ->assertCreated();

        $response = $this->getJson('/api/v1/badges')->assertOk();
        $critique = collect($response->json())->firstWhere('id', 'critique');

        $this->assertTrue($critique['unlocked']);
        $this->assertNotNull($critique['unlocked_at']);
    }

    public function test_sharing_soirees_unlocks_the_progress_badges(): void
    {
        Http::fake(['api.resend.com/*' => Http::response(['id' => 'email_1'])]);
        Sanctum::actingAs($this->makeUser());

        [$a, $b, $c] = Venue::factory()->count(3)->create();

        // 3 soirées au même lieu (fidele) puis 2 ailleurs :
        // 5 partages (noctambule), 3 lieux (explorateur), 3 moods (melomane).
        $this->share($a, 'festif');
        $this->share($a, 'festif');
        $this->share($a, 'chill');
        $this->share($b, 'decouverte');
        $this->share($c, 'festif');

        $unlocked = collect($this->getJson('/api/v1/badges')->json())
            ->filter(fn (array $badge) => $badge['unlocked'])
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['explorateur', 'fidele', 'melomane', 'noctambule'], $unlocked);
    }

    public function test_two_shares_do_not_unlock_anything(): void
    {
        Http::fake(['api.resend.com/*' => Http::response(['id' => 'email_1'])]);
        Sanctum::actingAs($this->makeUser());

        $venue = Venue::factory()->create();
        $this->share($venue);
        $this->share($venue);

        $unlocked = collect($this->getJson('/api/v1/badges')->json())
            ->filter(fn (array $badge) => $badge['unlocked']);

        $this->assertCount(0, $unlocked);
    }

    public function test_ten_checkins_unlock_the_habitue_badge(): void
    {
        $user = $this->makeUser();
        $venue = Venue::factory()->create();
        $viree = Viree::factory()->active()->create(['user_id' => $user->id]);
        Checkin::factory()->count(10)->create([
            'viree_id' => $viree->id,
            'user_id' => $user->id,
            'venue_id' => $venue->id,
        ]);
        Sanctum::actingAs($user);

        // L'évaluation tourne au prochain événement gamifié (ici un avis).
        $this->postJson("/api/v1/venues/{$venue->slug}/reviews", ['rating' => 4]);

        $badges = collect($this->getJson('/api/v1/badges')->json());
        $this->assertTrue($badges->firstWhere('id', 'habitue')['unlocked']);
    }

    public function test_fifteen_night_kilometers_unlock_the_grand_marcheur_badge(): void
    {
        $user = $this->makeUser();
        $venue = Venue::factory()->create();
        Viree::factory()->count(2)->create([
            'user_id' => $user->id,
            'distance_m' => 8000,
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/venues/{$venue->slug}/reviews", ['rating' => 4]);

        $badges = collect($this->getJson('/api/v1/badges')->json());
        $this->assertTrue($badges->firstWhere('id', 'grand-marcheur')['unlocked']);
        // 2 virées seulement : « arpenteur » (5 virées) reste verrouillé.
        $this->assertFalse($badges->firstWhere('id', 'arpenteur')['unlocked']);
    }

    public function test_a_badge_is_not_unlocked_twice(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $venue = Venue::factory()->create();

        $this->postJson("/api/v1/venues/{$venue->slug}/reviews", ['rating' => 5]);
        $this->postJson("/api/v1/venues/{$venue->slug}/reviews", ['rating' => 3]);

        $this->assertSame(1, $user->badges()->where('badges.id', 'critique')->count());
    }
}
