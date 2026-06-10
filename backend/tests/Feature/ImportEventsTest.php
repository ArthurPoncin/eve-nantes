<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportEventsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fixture de 3 enregistrements OpenDataSoft :
     *  - un concert gratuit (gratuit:'oui') au lieu "Stereolux"      -> mood festif, prix 0
     *  - un atelier payant (precisions_tarifs:'12 €') au lieu "Stereolux" -> prix 1200, MÊME lieu
     *  - un évènement payant au lieu "Le Lieu Unique"                -> autre lieu
     *
     * Les deux premiers partagent le même `lieu` pour prouver que le venue
     * n'est créé qu'une fois (upsert par slug).
     */
    private function fakeRecords(): array
    {
        return [
            'data.nantesmetropole.fr/*' => Http::response([
                'total_count' => 3,
                'results' => [
                    [
                        'nom' => 'Concert electro',
                        'description' => 'Soiree electro avec artistes locaux.',
                        'date' => '2026-07-01',
                        'heure_debut' => '20:30',
                        'heure_fin' => '23:59',
                        'lieu' => 'Stereolux',
                        'adresse' => '4 Boulevard Leon Bureau',
                        'code_postal' => 44200,
                        'ville' => 'Nantes',
                        'latitude' => 47.2058,
                        'longitude' => -1.5641,
                        'gratuit' => 'oui',
                        'precisions_tarifs' => null,
                        'themes_libelles' => ['Musique', 'Concert'],
                        'types_libelles' => ['Soiree'],
                    ],
                    [
                        'nom' => 'Atelier creation sonore',
                        'description' => null,
                        'date' => '2026-07-02',
                        'heure_debut' => '14:00',
                        'heure_fin' => null,
                        'lieu' => 'Stereolux',
                        'adresse' => '4 Boulevard Leon Bureau',
                        'code_postal' => '44200',
                        'ville' => 'Nantes',
                        'latitude' => 47.2058,
                        'longitude' => -1.5641,
                        'gratuit' => 'non',
                        'precisions_tarifs' => 'Tarif unique 12 €',
                        'themes_libelles' => ['Atelier'],
                        'types_libelles' => ['Famille'],
                    ],
                    [
                        'nom' => 'Exposition lumiere',
                        'description' => 'Parcours immersif.',
                        'date' => '2026-07-03',
                        'heure_debut' => null,
                        'heure_fin' => null,
                        'lieu' => 'Le Lieu Unique',
                        'adresse' => 'Quai Ferdinand-Favre',
                        'code_postal' => null,
                        'ville' => null,
                        'latitude' => null,
                        'longitude' => null,
                        'gratuit' => 'non',
                        'precisions_tarifs' => 'Plein tarif 8,50 € / reduit 5 €',
                        'themes_libelles' => ['Exposition'],
                        'types_libelles' => ['Art'],
                    ],
                ],
            ]),
        ];
    }

    public function test_it_imports_venues_and_events_from_the_open_data_api(): void
    {
        Http::fake($this->fakeRecords());

        $this->artisan('events:import')->assertExitCode(0);

        // 2 lieux : "Stereolux" (partagé par 2 events) et "Le Lieu Unique".
        $this->assertSame(2, Venue::count());
        // 3 évènements importés.
        $this->assertSame(3, Event::count());
    }

    public function test_it_maps_mood_price_and_parses_dates(): void
    {
        Http::fake($this->fakeRecords());

        $this->artisan('events:import')->assertExitCode(0);

        // Mood déduit de "Musique"/"Concert" -> festif.
        $stereolux = Venue::where('slug', 'stereolux')->firstOrFail();
        $this->assertSame('festif', $stereolux->mood);
        $this->assertContains($stereolux->mood, Venue::MOODS);

        // Concert gratuit -> prix 0, date parsée avec l'heure de début.
        $concert = Event::where('slug', 'concert-electro-2026-07-01')->firstOrFail();
        $this->assertSame(0, $concert->price_cents);
        $this->assertTrue($concert->is_published);
        $this->assertSame('2026-07-01 20:30:00', $concert->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-01 23:59:00', $concert->ends_at->format('Y-m-d H:i:s'));
        $this->assertSame($stereolux->id, $concert->venue_id);

        // "Tarif unique 12 €" -> 1200 cents ; pas d'heure de fin -> ends_at null.
        $atelier = Event::where('slug', 'atelier-creation-sonore-2026-07-02')->firstOrFail();
        $this->assertSame(1200, $atelier->price_cents);
        $this->assertNull($atelier->ends_at);
        // description vide -> fallback sur le titre.
        $this->assertSame('Atelier creation sonore', $atelier->description);

        // "8,50 €" -> 850 cents ; pas d'heure -> 00:00 ; code postal/ville absents -> defaults.
        $expo = Event::where('slug', 'exposition-lumiere-2026-07-03')->firstOrFail();
        $this->assertSame(850, $expo->price_cents);
        $this->assertSame('2026-07-03 00:00:00', $expo->starts_at->format('Y-m-d H:i:s'));

        $lieuUnique = Venue::where('slug', 'le-lieu-unique')->firstOrFail();
        $this->assertSame('44000', $lieuUnique->postal_code);
        $this->assertSame('Nantes', $lieuUnique->city);
    }

    public function test_it_requests_only_upcoming_nightlife_events(): void
    {
        Http::fake($this->fakeRecords());

        $this->artisan('events:import')->assertExitCode(0);

        // L'import ne doit demander à l'open-data que la programmation à venir
        // (date >= aujourd'hui) et les types « sorties » — pas tout l'agenda
        // (expos, ateliers, visites…).
        Http::assertSent(function (Request $request) {
            $url = urldecode($request->url());

            return str_contains($url, "types_libelles = 'Concert - Musique'")
                && str_contains($url, "types_libelles = 'Fête - Festival'")
                && str_contains($url, "types_libelles = 'Danse - Performance - Bal'")
                && str_contains($url, "types_libelles = 'Théâtre - Humour'")
                && str_contains($url, "types_libelles = 'Cirque - Magie - Marionnettes'")
                && str_contains($url, "types_libelles = 'Projection'")
                && str_contains($url, "types_libelles = 'Défilé - Parade - Arts de la rue'")
                && str_contains($url, 'date >= date')
                // Fenêtre bornée : toute la programmation des 2 prochains mois.
                && str_contains($url, 'date <= date');
        });
    }

    public function test_it_strips_html_from_event_descriptions(): void
    {
        Http::fake([
            'data.nantesmetropole.fr/*' => Http::response([
                'results' => [[
                    'nom' => 'Soiree test',
                    'lieu' => 'Salle Test',
                    'date' => '2026-07-10',
                    'heure_debut' => '21:00',
                    'description' => '<p>Super <strong>concert</strong>&nbsp;ce soir.</p><br /><p>Venez nombreux !</p>',
                    'types_libelles' => ['Concert - Musique'],
                ]],
            ]),
        ]);

        $this->artisan('events:import')->assertExitCode(0);

        $event = Event::firstOrFail();
        $this->assertStringNotContainsString('<', $event->description);
        $this->assertSame('Super concert ce soir. Venez nombreux !', $event->description);
    }

    public function test_it_derives_varied_moods_from_event_genre(): void
    {
        $make = fn (string $nom, string $lieu, string $desc, ?string $heure = '20:00'): array => [
            'nom' => $nom,
            'lieu' => $lieu,
            'date' => '2026-07-10',
            'heure_debut' => $heure,
            'description' => $desc,
            'types_libelles' => ['Concert - Musique'],
        ];

        Http::fake([
            'data.nantesmetropole.fr/*' => Http::response([
                'results' => [
                    $make('Nuit techno', 'Le Club', '<p>Grosse soiree techno toute la nuit.</p>'),
                    $make('Recital', 'La Salle', '<p>Musique classique au piano.</p>'),
                    $make('Apero-concert', 'Le Bar', '<p>Apero en musique des 18h.</p>'),
                    $make('Tour du monde', 'La Place', '<p>Musiques traditionnelles.</p>', null),
                ],
            ]),
        ]);

        $this->artisan('events:import')->assertExitCode(0);

        // Le genre détecté dans le titre/description donne des moods variés.
        $this->assertSame('festif', Venue::where('slug', 'le-club')->firstOrFail()->mood);
        $this->assertSame('chill', Venue::where('slug', 'la-salle')->firstOrFail()->mood);
        $this->assertSame('afterwork', Venue::where('slug', 'le-bar')->firstOrFail()->mood);
        $this->assertSame('decouverte', Venue::where('slug', 'la-place')->firstOrFail()->mood);
    }

    public function test_it_maps_the_new_event_types_to_moods(): void
    {
        $make = fn (string $nom, string $lieu, string $desc, string $type): array => [
            'nom' => $nom,
            'lieu' => $lieu,
            'date' => '2026-07-10',
            'heure_debut' => '20:00',
            'description' => $desc,
            'types_libelles' => [$type],
        ];

        Http::fake([
            'data.nantesmetropole.fr/*' => Http::response([
                'results' => [
                    $make('Grande fete de quartier', 'Le Hangar', '<p>Concerts et animations.</p>', 'Fête - Festival'),
                    $make('Grand bal populaire', 'La Halle', '<p>Orchestre en plein air.</p>', 'Danse - Performance - Bal'),
                    $make('Plateau stand-up', 'Le Studio', '<p>Cinq humoristes sur scene.</p>', 'Théâtre - Humour'),
                ],
            ]),
        ]);

        $this->artisan('events:import')->assertExitCode(0);

        // Les nouveaux types importés trouvent chacun une ambiance cohérente.
        $this->assertSame('festif', Venue::where('slug', 'le-hangar')->firstOrFail()->mood);
        $this->assertSame('festif', Venue::where('slug', 'la-halle')->firstOrFail()->mood);
        $this->assertSame('decouverte', Venue::where('slug', 'le-studio')->firstOrFail()->mood);
    }

    public function test_it_is_idempotent_when_run_twice(): void
    {
        Http::fake($this->fakeRecords());

        $this->artisan('events:import')->assertExitCode(0);

        $venuesAfterFirst = Venue::count();
        $eventsAfterFirst = Event::count();

        $this->artisan('events:import')->assertExitCode(0);

        $this->assertSame($venuesAfterFirst, Venue::count());
        $this->assertSame($eventsAfterFirst, Event::count());
    }

    public function test_it_preserves_an_existing_venue_mood(): void
    {
        // Un lieu déjà classé "chill" par un curateur ne doit pas être écrasé
        // par le mood déduit de l'open-data ("festif").
        Venue::create([
            'name' => 'Stereolux',
            'slug' => 'stereolux',
            'address_line' => '4 Boulevard Leon Bureau',
            'postal_code' => '44200',
            'city' => 'Nantes',
            'mood' => 'chill',
        ]);

        Http::fake($this->fakeRecords());

        $this->artisan('events:import')->assertExitCode(0);

        $this->assertSame('chill', Venue::where('slug', 'stereolux')->firstOrFail()->mood);
    }
}
