<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SoireeShareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.resend.key' => 'test-key']);
    }

    private function seedVenue(): Venue
    {
        return Venue::create([
            'name' => 'Le Macadam',
            'slug' => 'le-macadam',
            'address_line' => '1 Rue Test',
            'postal_code' => '44000',
            'city' => 'Nantes',
            'mood' => 'festif',
        ]);
    }

    public function test_it_shares_a_soiree_by_email_via_resend(): void
    {
        Http::fake(['api.resend.com/*' => Http::response(['id' => 'email_123'])]);
        $venue = $this->seedVenue();
        $event = Event::create([
            'title' => 'Nuit Techno',
            'slug' => 'nuit-techno',
            'description' => 'Set.',
            'starts_at' => now()->addDay(),
            'price_cents' => 1200,
            'is_published' => true,
            'venue_id' => $venue->id,
        ]);

        $response = $this->postJson('/api/v1/soiree/share', [
            'email' => 'ami@example.com',
            'mood' => 'festif',
            'venue_id' => $venue->id,
            'event_id' => $event->id,
            'narrative' => 'Une nuit electro au Macadam.',
            'weather' => ['temp' => 14.0, 'condition' => 'Couvert'],
        ]);

        $response->assertStatus(202)->assertJsonPath('status', 'sent');

        $this->assertDatabaseHas('soirees', [
            'venue_id' => $venue->id,
            'event_id' => $event->id,
            'mood' => 'festif',
        ]);

        Http::assertSent(function ($request) use ($venue) {
            return str_contains($request->url(), 'resend.com')
                && $request['to'] === ['ami@example.com']
                && str_contains((string) $request['subject'], $venue->name)
                && str_contains((string) $request['html'], 'Une nuit electro');
        });
    }

    public function test_it_validates_the_share_payload(): void
    {
        $this->postJson('/api/v1/soiree/share', [
            'email' => 'not-an-email',
            'mood' => 'festif',
            'venue_id' => 9999,
            'narrative' => '',
        ])->assertStatus(422);
    }

    public function test_it_returns_502_when_resend_fails(): void
    {
        Http::fake(['api.resend.com/*' => Http::response(null, 500)]);
        $venue = $this->seedVenue();

        $this->postJson('/api/v1/soiree/share', [
            'email' => 'ami@example.com',
            'mood' => 'festif',
            'venue_id' => $venue->id,
            'narrative' => 'Test',
        ])->assertStatus(502);
    }
}
