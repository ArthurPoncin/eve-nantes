<?php

namespace Tests\Feature;

use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportVenuesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fixture Overpass : un bar adressé (node), une boîte sans adresse (way,
     * coordonnées dans `center`), un pub, une cave à vin (mot-clé du nom) et
     * un élément sans nom qui doit être ignoré.
     */
    private function fakeOverpass(): array
    {
        return [
            'overpass-api.de/*' => Http::response([
                'elements' => [
                    [
                        'type' => 'node',
                        'id' => 1,
                        'lat' => 47.2154,
                        'lon' => -1.556,
                        'tags' => [
                            'amenity' => 'bar',
                            'name' => 'Le Berlingot',
                            'addr:housenumber' => '7',
                            'addr:street' => 'Rue Santeuil',
                            'addr:postcode' => '44000',
                            'addr:city' => 'Nantes',
                        ],
                    ],
                    [
                        'type' => 'way',
                        'id' => 2,
                        'center' => ['lat' => 47.21, 'lon' => -1.55],
                        'tags' => ['amenity' => 'nightclub', 'name' => 'Le Warehouse'],
                    ],
                    [
                        'type' => 'node',
                        'id' => 3,
                        'lat' => 47.216,
                        'lon' => -1.557,
                        'tags' => ['amenity' => 'pub', 'name' => 'John Mc Byrne'],
                    ],
                    [
                        'type' => 'node',
                        'id' => 4,
                        'lat' => 47.217,
                        'lon' => -1.558,
                        'tags' => ['amenity' => 'bar', 'name' => 'La Cave du Vigneron'],
                    ],
                    [
                        'type' => 'node',
                        'id' => 5,
                        'lat' => 47.0,
                        'lon' => -1.5,
                        'tags' => ['amenity' => 'bar'],
                    ],
                ],
            ]),
        ];
    }

    public function test_it_imports_named_bars_pubs_and_clubs_from_overpass(): void
    {
        Http::fake($this->fakeOverpass());

        $this->artisan('venues:import')->assertExitCode(0);

        // 4 lieux nommés ; l'élément sans nom est ignoré.
        $this->assertSame(4, Venue::count());

        $berlingot = Venue::where('slug', 'le-berlingot')->firstOrFail();
        $this->assertSame('7 Rue Santeuil', $berlingot->address_line);
        $this->assertSame('44000', $berlingot->postal_code);
        $this->assertSame('Nantes', $berlingot->city);
        $this->assertEqualsWithDelta(47.2154, (float) $berlingot->latitude, 0.0001);
        $this->assertEqualsWithDelta(-1.556, (float) $berlingot->longitude, 0.0001);
    }

    public function test_it_reads_way_coordinates_from_center_and_falls_back_on_address(): void
    {
        Http::fake($this->fakeOverpass());

        $this->artisan('venues:import')->assertExitCode(0);

        $warehouse = Venue::where('slug', 'le-warehouse')->firstOrFail();
        $this->assertEqualsWithDelta(47.21, (float) $warehouse->latitude, 0.0001);
        $this->assertEqualsWithDelta(-1.55, (float) $warehouse->longitude, 0.0001);
        // Pas de tags addr:* -> replis.
        $this->assertSame('Nantes', $warehouse->address_line);
        $this->assertSame('44000', $warehouse->postal_code);
        $this->assertSame('Nantes', $warehouse->city);
    }

    public function test_it_maps_amenity_and_name_keywords_to_moods(): void
    {
        Http::fake($this->fakeOverpass());

        $this->artisan('venues:import')->assertExitCode(0);

        // nightclub -> festif, pub -> afterwork, bar -> chill,
        // sauf mot-clé du nom (« cave » -> afterwork).
        $this->assertSame('festif', Venue::where('slug', 'le-warehouse')->firstOrFail()->mood);
        $this->assertSame('afterwork', Venue::where('slug', 'john-mc-byrne')->firstOrFail()->mood);
        $this->assertSame('chill', Venue::where('slug', 'le-berlingot')->firstOrFail()->mood);
        $this->assertSame('afterwork', Venue::where('slug', 'la-cave-du-vigneron')->firstOrFail()->mood);
    }

    public function test_it_is_idempotent_and_preserves_existing_venues(): void
    {
        // Un lieu déjà présent (créé par events:import ou un curateur) ne doit
        // être ni dupliqué ni écrasé.
        Venue::create([
            'name' => 'Le Berlingot',
            'slug' => 'le-berlingot',
            'address_line' => 'Adresse curatee',
            'postal_code' => '44000',
            'city' => 'Nantes',
            'mood' => 'festif',
        ]);

        Http::fake($this->fakeOverpass());

        $this->artisan('venues:import')->assertExitCode(0);
        $this->artisan('venues:import')->assertExitCode(0);

        $this->assertSame(4, Venue::count());
        $berlingot = Venue::where('slug', 'le-berlingot')->firstOrFail();
        $this->assertSame('Adresse curatee', $berlingot->address_line);
        $this->assertSame('festif', $berlingot->mood);
    }

    public function test_it_identifies_itself_to_overpass(): void
    {
        Http::fake($this->fakeOverpass());

        $this->artisan('venues:import')->assertExitCode(0);

        // Overpass refuse les clients anonymes (406) : User-Agent obligatoire.
        Http::assertSent(fn (Request $request) => $request->hasHeader('User-Agent')
            && str_contains($request->header('User-Agent')[0], 'NOCTAMBULE'));
    }

    public function test_it_fails_gracefully_when_overpass_is_down(): void
    {
        Http::fake(['overpass-api.de/*' => Http::response(null, 504)]);

        $this->artisan('venues:import')->assertExitCode(1);

        $this->assertSame(0, Venue::count());
    }
}
