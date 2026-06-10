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

    /** Fenêtre d'import : toute la programmation des prochains mois. */
    private const WINDOW_MONTHS = 2;

    /**
     * Garde-fou de pagination uniquement : la fenêtre de 2 mois sert ~550
     * enregistrements, on ne veut juste pas boucler sans fin si l'API déraille.
     */
    private const MAX_RECORDS = 2000;

    /** Taille de page demandée à l'API OpenDataSoft. */
    private const PAGE_SIZE = 100;

    /**
     * Types d'évènements retenus : l'agenda métropolitain mélange tout (crèches,
     * marchés, ateliers…). On garde la programmation « sorties » au sens large
     * (concerts, fêtes, bals, spectacles, séances du soir) pour rester fidèle au
     * concept NOCTAMBULE. Exclus à dessein : expos, ateliers, visites, sport,
     * conférences, et Contes/Jeux (mesurés à 90–98 % en journée, jeune public).
     *
     * @var list<string>
     */
    private const NIGHTLIFE_TYPES = [
        'Concert - Musique',
        'Fête - Festival',
        'Danse - Performance - Bal',
        'Théâtre - Humour',
        'Cirque - Magie - Marionnettes',
        'Projection',
        'Défilé - Parade - Arts de la rue',
    ];

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
                    'mood' => $this->moodFor($record),
                ]
            );

            if ($venue->wasRecentlyCreated) {
                $venues++;
            }

            $event = Event::updateOrCreate(
                ['slug' => Str::slug($nom).'-'.$date],
                [
                    'title' => $nom,
                    'description' => $this->cleanText($record['description'] ?? null) ?: $nom,
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

        // Clause ODSQL : toute la programmation des 2 prochains mois, restreinte
        // aux types nightlife. `order_by date` remonte d'abord les plus proches.
        $typeClause = collect(self::NIGHTLIFE_TYPES)
            ->map(fn (string $type) => "types_libelles = '".str_replace("'", "''", $type)."'")
            ->implode(' or ');
        $where = "date >= date'".Carbon::now()->toDateString()."'"
            ." and date <= date'".Carbon::now()->addMonths(self::WINDOW_MONTHS)->toDateString()."'"
            ." and ({$typeClause})";

        while (count($records) < self::MAX_RECORDS) {
            $response = Http::get($baseUrl, [
                'limit' => self::PAGE_SIZE,
                'offset' => $offset,
                'order_by' => 'date',
                'where' => $where,
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
     * Déduit une ambiance NOCTAMBULE (Venue::MOODS) pour un évènement.
     *
     * Tous les enregistrements importés sont des concerts : on ne se fie donc
     * pas au type, mais au GENRE musical détecté dans le titre/description
     * (techno → festif, classique → chill, apéro → afterwork…). À défaut de
     * mot-clé, l'heure de début départage pour garantir de la variété.
     *
     * @param  array<string, mixed>  $record
     */
    private function moodFor(array $record): string
    {
        $themes = $record['themes_libelles'] ?? [];
        $types = $record['types_libelles'] ?? [];
        $haystack = Str::lower(Str::ascii(implode(' ', array_filter([
            (string) ($record['nom'] ?? ''),
            $this->cleanText($record['description'] ?? null),
            is_array($themes) ? implode(' ', $themes) : '',
            is_array($types) ? implode(' ', $types) : '',
        ]))));

        // L'ordre compte : on teste du plus spécifique au plus large.
        $map = [
            'afterwork' => ['apero', 'afterwork', 'after work', '5 a 7', 'before', 'cocktail', 'degustation', 'brunch', 'aperitif'],
            'chill' => ['classique', 'vocal', 'choeur', 'choral', 'chorale', 'opera', 'orgue', 'piano', 'jazz', 'acoustique', 'baroque', 'lyrique', 'quatuor', 'gospel', 'chanson', 'blues', 'folk', 'ambient', 'intimiste', 'berceuse', 'conte', 'symphoni', 'requiem', 'recital'],
            'festif' => ['dj', 'club', 'techno', 'electro', 'house', 'dancefloor', 'bass', 'garage', 'dub', 'reggae', 'ska', 'funk', 'disco', 'hip hop', 'hip-hop', 'rap', 'latino', 'salsa', 'afrobeat', 'groove', 'rave', 'soiree', 'bal ', 'dancehall', 'punk', 'metal', 'rock', 'fete', 'festival', 'bal populaire', 'performance - bal', 'cabaret', 'carnaval', 'parade', 'defile'],
            // Spectacles (théâtre, cirque, projections…) : une sortie à
            // découvrir plutôt qu'une ambiance musicale.
            'decouverte' => ['humour', 'theatre', 'comedie', 'impro', 'stand-up', 'stand up', 'one man show', 'one woman show', 'magie', 'cirque', 'marionnette', 'projection', 'cinema', 'cine-', 'seance', 'court metrage', 'documentaire'],
        ];

        foreach ($map as $mood => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return $mood;
                }
            }
        }

        // Repli sur l'heure (le genre prime) : tard = festif, soirée = afterwork.
        // Journée / heure inconnue -> découverte (chill reste réservé aux genres
        // calmes détectés ci-dessus, et les 4 ambiances restent peuplées).
        $hour = (int) substr((string) ($record['heure_debut'] ?? ''), 0, 2);
        if ($hour >= 22) {
            return 'festif';
        }
        if ($hour >= 19) {
            return 'afterwork';
        }

        return 'decouverte';
    }

    /**
     * Nettoie un texte HTML de l'open-data : balises de bloc -> espaces, puis
     * suppression des balises restantes, décodage des entités et compactage des
     * espaces (y compris les espaces insécables).
     */
    private function cleanText(?string $html): string
    {
        if (! $html) {
            return '';
        }

        $text = preg_replace('/<\/?(p|br|div|h[1-6]|li|ul|ol)[^>]*>/i', ' ', $html);
        $text = strip_tags((string) $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\s\x{00A0}]+/u', ' ', (string) $text);

        return trim((string) $text);
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
