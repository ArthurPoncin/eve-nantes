<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Viree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VireePrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_can_make_a_viree_private_then_public(): void
    {
        $owner = User::factory()->create();
        $viree = Viree::factory()->create(['user_id' => $owner->id]);
        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/virees/{$viree->public_id}/visibility", ['is_public' => false])
            ->assertOk()
            ->assertJsonPath('is_public', false);

        $this->patchJson("/api/v1/virees/{$viree->public_id}/visibility", ['is_public' => true])
            ->assertOk()
            ->assertJsonPath('is_public', true);
    }

    public function test_only_the_owner_can_change_visibility(): void
    {
        $viree = Viree::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/v1/virees/{$viree->public_id}/visibility", ['is_public' => false])
            ->assertForbidden();
    }

    public function test_visibility_requires_authentication_and_a_boolean(): void
    {
        $owner = User::factory()->create();
        $viree = Viree::factory()->create(['user_id' => $owner->id]);

        $this->patchJson("/api/v1/virees/{$viree->public_id}/visibility", ['is_public' => false])
            ->assertUnauthorized();

        Sanctum::actingAs($owner);
        $this->patchJson("/api/v1/virees/{$viree->public_id}/visibility", [])
            ->assertUnprocessable();
    }

    public function test_a_public_recap_stays_open_to_logged_out_visitors(): void
    {
        $viree = Viree::factory()->create(['is_public' => true]);

        $this->getJson("/api/v1/virees/{$viree->public_id}")->assertOk();
    }

    public function test_a_private_recap_is_hidden_from_guests_and_strangers(): void
    {
        $viree = Viree::factory()->create(['is_public' => false]);

        $this->getJson("/api/v1/virees/{$viree->public_id}")->assertNotFound();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/virees/{$viree->public_id}")->assertNotFound();
    }

    public function test_a_private_recap_is_visible_to_the_owner_and_their_followers(): void
    {
        $owner = User::factory()->create();
        $viree = Viree::factory()->create(['user_id' => $owner->id, 'is_public' => false]);

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/virees/{$viree->public_id}")->assertOk();

        $follower = User::factory()->create();
        $follower->following()->attach($owner->id);
        Sanctum::actingAs($follower);
        $this->getJson("/api/v1/virees/{$viree->public_id}")->assertOk();
    }

    public function test_private_virees_are_filtered_from_the_public_profile(): void
    {
        $owner = User::factory()->create(['username' => 'discret']);
        Viree::factory()->create(['user_id' => $owner->id, 'is_public' => false]);
        Viree::factory()->create(['user_id' => $owner->id, 'is_public' => true]);

        // Anonyme : seule la virée publique compte.
        $this->getJson('/api/v1/users/discret')
            ->assertOk()
            ->assertJsonPath('stats.virees_count', 1);

        // Un abonné voit aussi la privée.
        $follower = User::factory()->create();
        $follower->following()->attach($owner->id);
        Sanctum::actingAs($follower);
        $this->getJson('/api/v1/users/discret')
            ->assertOk()
            ->assertJsonPath('stats.virees_count', 2);
    }
}
