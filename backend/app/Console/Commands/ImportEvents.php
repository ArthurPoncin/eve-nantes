<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ImportEvents extends Command
{
    protected $signature = 'events:import';

    protected $description = "Importe les évènements open-data de Nantes Métropole et met à jour lieux + évènements.";

    /** Nombre maximum d'enregistrements ingérés (borne la pagination). */
    private const MAX_RECORDS = 200;

    /** Taille de page demandée à l'API OpenDataSoft. */
    private const PAGE_SIZE = 100;

    public function handle(): int
    {
        $venues = 0;
        $events = 0;

        foreach ($this->fetchRecords() as $record) {
            $nom = $record['nom'] ?? null;
            $date = $record['date'] ?? null;
            $lieu = $record['lieu'] ?? null;

            // On ignore les enregistrements incomplets : sans nom, date ou lieu
            // on ne peut ni construire un slug stable ni rattacher l'évènement.
            if (! $nom || ! $date || ! $lieu) {
                continue;
            }

            $venue = Venue::firstOrCreate(
                ['slug' => Str::slug($lieu)],
                [
                    'name' => $lieu,
                    'address_line' => ($record['adresse'] ?? null) ?: 'Nantes',
                    'postal_code' => $this->normalizePostalCode($record['code_postal'] ?? null),
                    'city' => ($record['ville'] ?? null) ?: 'Nantes',
                    'latitude' => $record['latitude'] ?? null,
                    'longitude' => $record['longitude'] ?? null,
                    'mood' => $this->moodFor($record['themes_libelles'] ?? null, $record['types_libelles'] ?? null),
                ]
            );

            if ($venue->wasRecentlyCreated) {
                $venues++;
            }

            $event = Event::updateOrCreate(
                ['slug' => Str::slug($nom).'-'.$date],
                [
                    'title' => $nom,
                    'description' => ($record['description'] ?? null) ?: $nom,
                    'starts_at' => Carbon::parse($date.' '.(($record['heure_debut'] ?? null) ?: '00:00')),
                    'ends_at' => ($record['heure_fin'] ?? null)
                        ? Carbon::parse($date.' '.$record['heure_fin'])
                        : null,
                    'price_cents' => (($record['gratuit'] ?? null) === 'oui')
                        ? 0
                        : $this->priceFromText($record['precisions_tarifs'] ?? null),
                    'is_published' => true,
                    'venue_id' => $venue->id,
                ]
            );

            if ($event->wasRecentlyCreated) {
                $events++;
            }
        }

        $this->info("{$venues} lieux, {$events} évènements importés.");

        return self::SUCCESS;
    }

    /**
     * Récupère les enregistrements via l'API OpenDataSoft, en paginant avec
     * `offset` jusqu'à MAX_RECORDS pour rester borné.
     *
     * @return list<array<string, mixed>>
     */
    private function fetchRecords(): array
    {
        $baseUrl = config('services.nantes_open_data.events_url');
        $records = [];
        $offset = 0;

        while (count($records) < self::MAX_RECORDS) {
            $response = Http::get($baseUrl, [
                'limit' => self::PAGE_SIZE,
                'offset' => $offset,
                'order_by' => 'date',
            ]);

            if ($response->failed()) {
                break;
            }

            $results = $response->json('results') ?? [];

            if ($results === []) {
                break;
            }

            foreach ($results as $result) {
                $records[] = $result;
            }

            // Page incomplète : plus rien à paginer.
            if (count($results) < self::PAGE_SIZE) {
                break;
            }

            $offset += self::PAGE_SIZE;
        }

        return array_slice($records, 0, self::MAX_RECORDS);
    }

    /**
     * Réduit un code postal arbitraire (int|string|null) à 5 chiffres max,
     * avec un repli sur 44000 (colonne postal_code = 5 caractères).
     */
    private function normalizePostalCode(int|string|null $code): string
    {
        $digits = preg_replace('/\D/', '', (string) $code) ?: '44000';

        return substr($digits, 0, 5);
    }

    /**
     * Déduit une ambiance NOCTAMBULE (Venue::MOODS) à partir des libellés de
     * thèmes et de types, après mise en minuscules et désaccentuation.
     *
     * @param  list<string>|null  $themes
     * @param  list<string>|null  $types
     */
    private function moodFor(?array $themes, ?array $types): string
    {
        $haystack = Str::lower(
            Str::ascii(implode(' ', array_merge($themes ?? [], $types ?? [])))
        );

        $map = [
            'festif' => ['concert', 'musique', 'dj', 'club', 'soiree', 'festival', 'nuit'],
            'afterwork' => ['bar', 'apero', 'afterwork', 'degustation', 'gastronomie'],
            'chill' => ['detente', 'bien-etre', 'balade', 'nature', 'famille', 'enfance', 'atelier'],
        ];

        foreach ($map as $mood => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return $mood;
                }
            }
        }

        return 'decouverte';
    }

    /**
     * Extrait le premier montant d'un texte tarifaire (« 12 », « 12,50 ») et
     * le convertit en centimes. Aucun montant -> 0 (gratuit / inconnu).
     */
    private function priceFromText(?string $text): int
    {
        if (! $text) {
            return 0;
        }

        if (! preg_match('/(\d+)(?:[.,](\d{1,2}))?/', $text, $matches)) {
            return 0;
        }

        $euros = (int) $matches[1];
        $cents = isset($matches[2]) ? (int) str_pad($matches[2], 2, '0') : 0;

        return $euros * 100 + $cents;
    }
}
