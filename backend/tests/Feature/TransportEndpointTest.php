<?php

namespace Tests\Feature;

use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TransportEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function makeVenue(array $overrides = []): Venue
    {
        return Venue::create([
            'name' => 'Stereolux',
            'slug' => 'stereolux',
            'address_line' => '4 Boulevard Leon Bureau',
            'postal_code' => '44200',
            'city' => 'Nantes',
            'latitude' => 47.2049,
            'longitude' => -1.5645,
            ...$overrides,
        ]);
    }

    /**
     * @return array<string, \Illuminate\Http\Client\Response>
     */
    private function fakeTan(): array
    {
        return [
            '*.tan.fr/ewp/arrets.json/*' => Http::response([
                [
                    'codeLieu' => 'CDCI',
                    'libelle' => 'Chantiers Navals',
                    'distance' => '143 m',
                    'ligne' => [['numLigne' => '1'], ['numLigne' => 'C5']],
                ],
                [
                    'codeLieu' => 'LMNE',
                    'libelle' => 'Les Machines',
                    'distance' => '290 m',
                    'ligne' => [['numLigne' => 'C5']],
                ],
            ]),
            '*.tan.fr/ewp/tempsattente.json/*' => Http::response([
                [
                    'sens' => 1,
                    'terminus' => 'Francois Mitterrand',
                    'temps' => '4mn',
                    'tempsReel' => 'true',
                    'ligne' => ['numLigne' => '1', 'typeLigne' => 1],
                    'arret' => ['codeArret' => 'CDCI1'],
                ],
                [
                    'sens' => 2,
                    'terminus' => 'Beaujoire',
                    'temps' => '9mn',
                    'tempsReel' => 'false',
                    'ligne' => ['numLigne' => '1', 'typeLigne' => 1],
                    'arret' => ['codeArret' => 'CDCI2'],
                ],
                // Doublon ligne 1 → Francois Mitterrand : dédoublonné côté service
                // (on ne garde que le passage le plus proche par ligne + terminus).
                [
                    'sens' => 1,
                    'terminus' => 'Francois Mitterrand',
                    'temps' => '15mn',
                    'tempsReel' => 'true',
                    'ligne' => ['numLigne' => '1', 'typeLigne' => 1],
                    'arret' => ['codeArret' => 'CDCI1'],
                ],
                [
                    'sens' => 1,
                    'terminus' => 'Quai des Antilles',
                    'temps' => 'horaire',
                    'tempsReel' => 'false',
                    'ligne' => ['numLigne' => 'C5', 'typeLigne' => 3],
                    'arret' => ['codeArret' => 'CDCI3'],
                ],
            ]),
        ];
    }

    public function test_it_returns_the_nearest_stop_and_next_departures(): void
    {
        Http::fake($this->fakeTan());
        $this->makeVenue();

        $response = $this->getJson('/api/v1/venues/stereolux/transport');

        $response->assertOk()->assertExactJson([
            'stop' => [
                'code' => 'CDCI',
                'name' => 'Chantiers Navals',
                'distance' => '143 m',
            ],
            'departures' => [
                [
                    'line' => '1',
                    'type' => 'tram',
                    'terminus' => 'Francois Mitterrand',
                    'wait' => '4mn',
                    'realtime' => true,
                ],
                [
                    'line' => '1',
                    'type' => 'tram',
                    'terminus' => 'Beaujoire',
                    'wait' => '9mn',
                    'realtime' => false,
                ],
                [
                    'line' => 'C5',
                    'type' => 'bus',
                    'terminus' => 'Quai des Antilles',
                    'wait' => 'horaire',
                    'realtime' => false,
                ],
            ],
        ]);
    }

    public function test_it_caches_tan_responses_between_calls(): void
    {
        Http::fake($this->fakeTan());
        $this->makeVenue();

        $this->getJson('/api/v1/venues/stereolux/transport')->assertOk();
        $this->getJson('/api/v1/venues/stereolux/transport')->assertOk();

        // 1 appel arrets.json + 1 appel tempsattente.json, puis cache.
        Http::assertSentCount(2);
    }

    public function test_it_returns_no_stop_when_the_venue_has_no_coordinates(): void
    {
        Http::fake();
        $this->makeVenue(['latitude' => null, 'longitude' => null]);

        $this->getJson('/api/v1/venues/stereolux/transport')
            ->assertOk()
            ->assertExactJson(['stop' => null, 'departures' => []]);

        Http::assertNothingSent();
    }

    public function test_it_returns_no_stop_when_tan_finds_none_nearby(): void
    {
        Http::fake(['*.tan.fr/ewp/arrets.json/*' => Http::response([])]);
        $this->makeVenue();

        $this->getJson('/api/v1/venues/stereolux/transport')
            ->assertOk()
            ->assertExactJson(['stop' => null, 'departures' => []]);

        // Pas d'arret trouve : on n'interroge pas les temps d'attente.
        Http::assertSentCount(1);
    }

    public function test_it_returns_404_for_an_unknown_venue(): void
    {
        $this->getJson('/api/v1/venues/inexistant/transport')
            ->assertNotFound();
    }
}
