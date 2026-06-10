<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_requires_authentication(): void
    {
        $this->getJson('/api/v1/users/search?q=noct')->assertUnauthorized();
    }

    public function test_search_matches_a_username_fragment(): void
    {
        $me = User::factory()->create(['username' => 'arthur']);
        User::factory()->create(['username' => 'noctambule44']);
        User::factory()->create(['username' => 'la-nocturne']);
        User::factory()->create(['username' => 'matinal']);
        Sanctum::actingAs($me);

        $response = $this->getJson('/api/v1/users/search?q=noct')->assertOk();

        $usernames = array_column($response->json('data'), 'username');
        $this->assertSame(['la-nocturne', 'noctambule44'], $usernames);
    }

    public function test_search_excludes_me_and_flags_who_i_follow(): void
    {
        $me = User::factory()->create(['username' => 'noct-arthur']);
        $followed = User::factory()->create(['username' => 'noct-amie']);
        User::factory()->create(['username' => 'noct-inconnu']);
        $me->following()->attach($followed->id);
        Sanctum::actingAs($me);

        $response = $this->getJson('/api/v1/users/search?q=noct')->assertOk();

        $byUsername = collect($response->json('data'))->keyBy('username');
        $this->assertFalse($byUsername->has('noct-arthur'));
        $this->assertTrue($byUsername->get('noct-amie')['is_following']);
        $this->assertFalse($byUsername->get('noct-inconnu')['is_following']);
        $this->assertSame(1, $byUsername->get('noct-amie')['followers_count']);
    }

    public function test_a_query_under_two_chars_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/users/search?q=n')->assertUnprocessable();
        $this->getJson('/api/v1/users/search')->assertUnprocessable();
    }

    public function test_results_are_capped_at_ten(): void
    {
        Sanctum::actingAs(User::factory()->create());
        foreach (range(1, 12) as $i) {
            User::factory()->create(['username' => "noctambule-{$i}"]);
        }

        $this->getJson('/api/v1/users/search?q=noctambule')
            ->assertOk()
            ->assertJsonCount(10, 'data');
    }
}
