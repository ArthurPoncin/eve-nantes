<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Viree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_feed_requires_authentication(): void
    {
        $this->getJson('/api/v1/feed')->assertUnauthorized();
    }

    public function test_the_feed_mixes_my_virees_and_those_of_people_i_follow(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create(['username' => 'amie']);
        $stranger = User::factory()->create();
        $me->following()->attach($friend->id);

        $mine = Viree::factory()->create(['user_id' => $me->id, 'ended_at' => now()->subHours(2)]);
        $theirs = Viree::factory()->create(['user_id' => $friend->id, 'ended_at' => now()->subHour()]);
        // Hors fil : virée d'un inconnu, et virée encore en cours d'une amie.
        Viree::factory()->create(['user_id' => $stranger->id]);
        Viree::factory()->active()->create(['user_id' => $friend->id]);

        Sanctum::actingAs($me);
        $response = $this->getJson('/api/v1/feed')->assertOk();

        // Plus récente d'abord : celle de l'amie, puis la mienne.
        $this->assertSame(
            [$theirs->public_id, $mine->public_id],
            array_column($response->json('data'), 'public_id'),
        );
        $response->assertJsonPath('data.0.user.username', 'amie');
    }

    public function test_the_feed_paginates_by_cursor(): void
    {
        $me = User::factory()->create();
        foreach (range(1, 15) as $i) {
            Viree::factory()->create([
                'user_id' => $me->id,
                'ended_at' => now()->subHours($i),
            ]);
        }
        Sanctum::actingAs($me);

        $first = $this->getJson('/api/v1/feed')->assertOk();
        $this->assertCount(10, $first->json('data'));
        $cursor = $first->json('meta.next_cursor');
        $this->assertNotNull($cursor);

        $second = $this->getJson('/api/v1/feed?cursor='.$cursor)->assertOk();
        $this->assertCount(5, $second->json('data'));
        $this->assertNull($second->json('meta.next_cursor'));

        // Aucune virée dupliquée entre les deux pages.
        $all = array_merge(
            array_column($first->json('data'), 'public_id'),
            array_column($second->json('data'), 'public_id'),
        );
        $this->assertCount(15, array_unique($all));
    }

    public function test_feed_items_carry_kudos_count_and_my_kudos_flag(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $me->following()->attach($friend->id);

        $applauded = Viree::factory()->create(['user_id' => $friend->id, 'ended_at' => now()->subHour()]);
        $applauded->kudosGivers()->attach([$me->id, User::factory()->create()->id]);
        Viree::factory()->create(['user_id' => $friend->id, 'ended_at' => now()->subHours(2)]);

        Sanctum::actingAs($me);
        $response = $this->getJson('/api/v1/feed')->assertOk();

        $response->assertJsonPath('data.0.kudos_count', 2)
            ->assertJsonPath('data.0.has_kudoed', true)
            ->assertJsonPath('data.1.kudos_count', 0)
            ->assertJsonPath('data.1.has_kudoed', false);
    }
}
