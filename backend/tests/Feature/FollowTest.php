<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FollowTest extends TestCase
{
    use RefreshDatabase;

    public function test_follow_requires_authentication(): void
    {
        $user = User::factory()->create();

        $this->postJson("/api/v1/users/{$user->username}/follow")->assertUnauthorized();
    }

    public function test_follow_creates_the_link(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        Sanctum::actingAs($me);

        $this->postJson("/api/v1/users/{$them->username}/follow")
            ->assertCreated()
            ->assertJsonPath('followers_count', 1)
            ->assertJsonPath('is_following', true);

        $this->assertTrue($me->isFollowing($them));
    }

    public function test_follow_is_idempotent(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        Sanctum::actingAs($me);

        $this->postJson("/api/v1/users/{$them->username}/follow")->assertCreated();
        $this->postJson("/api/v1/users/{$them->username}/follow")->assertCreated();

        $this->assertDatabaseCount('follows', 1);
    }

    public function test_self_follow_is_rejected(): void
    {
        $me = User::factory()->create();
        Sanctum::actingAs($me);

        $this->postJson("/api/v1/users/{$me->username}/follow")
            ->assertUnprocessable();

        $this->assertDatabaseCount('follows', 0);
    }

    public function test_unfollow_removes_the_link(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        $me->following()->attach($them->id);
        Sanctum::actingAs($me);

        $this->deleteJson("/api/v1/users/{$them->username}/follow")->assertNoContent();

        $this->assertFalse($me->isFollowing($them));
    }

    public function test_followers_and_following_lists_are_public(): void
    {
        $star = User::factory()->create(['username' => 'la-star']);
        $fan = User::factory()->create(['username' => 'le-fan']);
        $idol = User::factory()->create(['username' => 'l-idole']);
        $fan->following()->attach($star->id);
        $star->following()->attach($idol->id);

        $this->getJson('/api/v1/users/la-star/followers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'le-fan');

        $this->getJson('/api/v1/users/la-star/following')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'l-idole');
    }

    public function test_follow_an_unknown_username_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/users/personne/follow')->assertNotFound();
    }
}
