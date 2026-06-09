<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SoireeGenerateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clé factice : on force le chemin « appel IA » (intercepté par Http::fake)
        // indépendamment du .env, pour des tests hermétiques.
        config(['services.mistral.key' => 'test-key']);
    }

    private function fakeWeather(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response([
                'current' => [
                    'temperature_2m' => 14.0,
                    'apparent_temperature' => 12.5,
                    'relative_humidity_2m' => 70,
                    'weather_code' => 3,
                    'wind_speed_10m' => 8.0,
                    'is_day' => 0,
                ],
            ]),
        ]);
    }

    private function seedFestifVenueWithEvent(): Venue
    {
        $venue = Venue::create([
            'name' => 'Le Macadam',
            'slug' => 'le-macadam',
            'address_line' => '1 Rue Test',
            'postal_code' => '44000',
            'city' => 'Nantes',
            'mood' => 'festif',
            'latitude' => 47.21,
            'longitude' => -1.55,
        ]);

        Event::create([
            'title' => 'Nuit Techno',
            'slug' => 'nuit-techno',
            'description' => 'Set techno.',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(4),
            'price_cents' => 1200,
            'is_published' => true,
            'venue_id' => $venue->id,
        ]);

        return $venue;
    }

    public function test_it_generates_a_soiree_for_a_mood(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response(['current' => [
                'temperature_2m' => 14.0, 'apparent_temperature' => 12.5,
                'relative_humidity_2m' => 70, 'weather_code' => 3,
                'wind_speed_10m' => 8.0, 'is_day' => 0,
            ]]),
            'api.mistral.ai/*' => Http::response([
                'choices' => [['message' => ['content' => "Ce soir au Macadam, la nuit electro t'attend."]]],
            ]),
        ]);
        $this->seedFestifVenueWithEvent();

        $response = $this->postJson('/api/v1/soiree/generate', ['mood' => 'festif']);

        $response->assertOk()
            ->assertJsonPath('mood', 'festif')
            ->assertJsonPath('venue.slug', 'le-macadam')
            ->assertJsonPath('event.title', 'Nuit Techno')
            ->assertJsonPath('weather.condition', 'Couvert')
            ->assertJsonPath('narrative', "Ce soir au Macadam, la nuit electro t'attend.");
    }

    public function test_it_rejects_an_unknown_mood(): void
    {
        $this->postJson('/api/v1/soiree/generate', ['mood' => 'banger'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mood');
    }

    public function test_it_returns_404_when_no_venue_matches_the_mood(): void
    {
        $this->fakeWeather();

        $this->postJson('/api/v1/soiree/generate', ['mood' => 'festif'])
            ->assertNotFound();
    }

    public function test_it_falls_back_to_a_local_narrative_when_the_ai_fails(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response(['current' => [
                'temperature_2m' => 14.0, 'apparent_temperature' => 12.5,
                'relative_humidity_2m' => 70, 'weather_code' => 3,
                'wind_speed_10m' => 8.0, 'is_day' => 0,
            ]]),
            'api.mistral.ai/*' => Http::response(null, 500),
        ]);
        $this->seedFestifVenueWithEvent();

        $response = $this->postJson('/api/v1/soiree/generate', ['mood' => 'festif']);

        $response->assertOk();
        $this->assertNotEmpty($response->json('narrative'));
        $this->assertStringContainsString('Macadam', (string) $response->json('narrative'));
    }
}
