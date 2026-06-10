<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Viree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KudosTest extends TestCase
{
    use RefreshDatabase;

    public function test_kudos_requires_authentication(): void
    {
        $viree = Viree::factory()->create();

        $this->postJson("/api/v1/virees/{$viree->public_id}/kudos")->assertUnauthorized();
    }

    public function test_giving_kudos_increments_the_count_idempotently(): void
    {
        $viree = Viree::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/virees/{$viree->public_id}/kudos")
            ->assertCreated()
            ->assertJsonPath('kudos_count', 1);
        $this->postJson("/api/v1/virees/{$viree->public_id}/kudos")
            ->assertCreated()
            ->assertJsonPath('kudos_count', 1);

        $this->assertDatabaseCount('kudos', 1);
    }

    public function test_kudos_on_my_own_viree_is_rejected(): void
    {
        $me = User::factory()->create();
        $viree = Viree::factory()->create(['user_id' => $me->id]);
        Sanctum::actingAs($me);

        $this->postJson("/api/v1/virees/{$viree->public_id}/kudos")->assertUnprocessable();
    }

    public function test_kudos_on_an_invisible_viree_returns_404(): void
    {
        $viree = Viree::factory()->create(['is_public' => false]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/virees/{$viree->public_id}/kudos")->assertNotFound();
    }

    public function test_removing_kudos(): void
    {
        $viree = Viree::factory()->create();
        $me = User::factory()->create();
        $viree->kudosGivers()->attach($me->id);
        Sanctum::actingAs($me);

        $this->deleteJson("/api/v1/virees/{$viree->public_id}/kudos")->assertNoContent();

        $this->assertDatabaseCount('kudos', 0);
    }

    public function test_the_givers_list_is_public_for_a_public_viree(): void
    {
        $viree = Viree::factory()->create();
        $fan = User::factory()->create(['username' => 'le-fan']);
        $viree->kudosGivers()->attach($fan->id);

        $this->getJson("/api/v1/virees/{$viree->public_id}/kudos")
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('users.0.username', 'le-fan');
    }

    public function test_the_givers_list_of_a_private_viree_is_hidden_from_guests(): void
    {
        $viree = Viree::factory()->create(['is_public' => false]);

        $this->getJson("/api/v1/virees/{$viree->public_id}/kudos")->assertNotFound();
    }
}
