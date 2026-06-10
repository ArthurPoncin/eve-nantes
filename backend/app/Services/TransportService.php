<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TransportService
{
    /** @var array<int, string> Types de ligne TAN (open.tan.fr). */
    private const LINE_TYPES = [
        1 => 'tram',
        2 => 'busway',
        3 => 'bus',
        4 => 'navibus',
    ];

    /** Nombre maximum de prochains passages renvoyés au front. */
    private const MAX_DEPARTURES = 6;

    /**
     * Arrêt TAN le plus proche d'un point + prochains passages temps réel.
     *
     * Deux appels open.tan.fr (API publique, sans clé) :
     * - arrets.json/{lat}/{lon} : arrêts triés du plus proche au plus loin,
     *   mis en cache 1 h (les arrêts ne bougent pas) ;
     * - tempsattente.json/{codeLieu} : attentes temps réel, cache 60 s
     *   seulement (la donnée se périme à la minute).
     *
     * @return array{stop: array{code: string, name: string, distance: string|null}|null, departures: list<array{line: string, type: string, terminus: string, wait: string, realtime: bool}>}
     */
    public function nextDepartures(?float $latitude, ?float $longitude): array
    {
        $none = ['stop' => null, 'departures' => []];

        if ($latitude === null || $longitude === null) {
            return $none;
        }

        $stop = $this->nearestStop($latitude, $longitude);

        if ($stop === null) {
            return $none;
        }

        return [
            'stop' => $stop,
            'departures' => $this->waitingTimes($stop['code']),
        ];
    }

    /**
     * @return array{code: string, name: string, distance: string|null}|null
     */
    private function nearestStop(float $latitude, float $longitude): ?array
    {
        // 4 décimales ≈ 11 m : assez fin pour distinguer deux lieux,
        // assez large pour partager le cache entre appels identiques.
        $key = sprintf('cache:tan:stop:%.4f,%.4f', $latitude, $longitude);

        return Cache::remember($key, now()->addHour(), function () use ($latitude, $longitude): ?array {
            $stops = Http::get(sprintf(
                '%s/arrets.json/%s/%s',
                config('services.tan.endpoint'),
                $latitude,
                $longitude,
            ))->throw()->json();

            // L'API renvoie les arrêts du plus proche au plus éloigné.
            $nearest = $stops[0] ?? null;

            if (! is_array($nearest) || ! isset($nearest['codeLieu'])) {
                return null;
            }

            return [
                'code' => (string) $nearest['codeLieu'],
                'name' => (string) ($nearest['libelle'] ?? $nearest['codeLieu']),
                'distance' => isset($nearest['distance']) ? (string) $nearest['distance'] : null,
            ];
        });
    }

    /**
     * @return list<array{line: string, type: string, terminus: string, wait: string, realtime: bool}>
     */
    private function waitingTimes(string $stopCode): array
    {
        return Cache::remember("cache:tan:wait:{$stopCode}", now()->addSeconds(60), function () use ($stopCode): array {
            $times = Http::get(sprintf(
                '%s/tempsattente.json/%s',
                config('services.tan.endpoint'),
                $stopCode,
            ))->throw()->json();

            if (! is_array($times)) {
                return [];
            }

            return collect($times)
                ->filter(fn ($time) => is_array($time) && isset($time['ligne']['numLigne']))
                // L'API est triée par temps d'attente : on garde le passage le plus
                // proche de chaque couple ligne + terminus pour montrer la variété
                // des lignes plutôt que six fois le même tram.
                ->unique(fn (array $time) => $time['ligne']['numLigne'].'|'.($time['terminus'] ?? ''))
                ->take(self::MAX_DEPARTURES)
                ->map(fn (array $time): array => [
                    'line' => (string) $time['ligne']['numLigne'],
                    'type' => self::LINE_TYPES[(int) ($time['ligne']['typeLigne'] ?? 3)] ?? 'bus',
                    'terminus' => (string) ($time['terminus'] ?? ''),
                    'wait' => (string) ($time['temps'] ?? ''),
                    // L'API renvoie le booléen sous forme de chaîne "true"/"false".
                    'realtime' => ($time['tempsReel'] ?? 'false') === 'true',
                ])
                ->values()
                ->all();
        });
    }
}
