<?php

namespace App\Console\Commands;

use App\Models\Venue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ImportVenues extends Command
{
    protected $signature = 'venues:import';

    protected $description = 'Importe les bars, pubs et boîtes nantais depuis OpenStreetMap (Overpass).';

    /**
     * Requête Overpass QL : tous les bars / pubs / nightclubs (nœuds, ways et
     * relations) dans les limites administratives de la commune de Nantes.
     * `out tags center` ramène les tags + un centroïde pour les surfaces.
     */
    private const OVERPASS_QUERY = <<<'OQL'
    [out:json][timeout:25];
    area['name'='Nantes']['boundary'='administrative']['admin_level'='8']->.a;
    (
      nwr['amenity'='bar'](area.a);
      nwr['amenity'='pub'](area.a);
      nwr['amenity'='nightclub'](area.a);
    );
    out tags center;
    OQL;

    public function handle(): int
    {
        $response = $this->fetchFromOverpass();

        if ($response === null) {
            $this->error('Aucune instance Overpass joignable : import annulé.');

            return self::FAILURE;
        }

        $created = 0;

        foreach ($response->json('elements') ?? [] as $element) {
            $tags = $element['tags'] ?? [];
            $name = $tags['name'] ?? null;

            // Sans nom, pas de slug stable ni de fiche présentable.
            if (! $name) {
                continue;
            }

            $venue = Venue::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'address_line' => $this->addressLine($tags),
                    'postal_code' => $this->normalizePostalCode($tags['addr:postcode'] ?? null),
                    'city' => ($tags['addr:city'] ?? null) ?: 'Nantes',
                    'latitude' => $element['lat'] ?? $element['center']['lat'] ?? null,
                    'longitude' => $element['lon'] ?? $element['center']['lon'] ?? null,
                    'mood' => $this->moodFor($name, $tags['amenity'] ?? 'bar'),
                ]
            );

            if ($venue->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->info("{$created} lieux importés depuis OpenStreetMap.");

        return self::SUCCESS;
    }

    /**
     * Interroge les instances Overpass dans l'ordre (OVERPASS_URL accepte une
     * liste séparée par des virgules) et s'arrête à la première qui répond :
     * l'instance principale rate-limite agressivement certaines IPs.
     */
    private function fetchFromOverpass(): ?\Illuminate\Http\Client\Response
    {
        $endpoints = array_filter(array_map('trim', explode(',', (string) config('services.overpass.url'))));

        foreach ($endpoints as $endpoint) {
            try {
                // Overpass renvoie 406 aux clients anonymes : User-Agent obligatoire.
                $response = Http::withHeaders([
                    'User-Agent' => 'NOCTAMBULE/1.0 (+https://noctambule.zespri.duckdns.org)',
                ])->timeout(40)->asForm()->post($endpoint, ['data' => self::OVERPASS_QUERY]);

                if ($response->successful()) {
                    return $response;
                }

                $this->warn("Overpass {$endpoint} -> {$response->status()}, instance suivante…");
            } catch (\Illuminate\Http\Client\ConnectionException) {
                $this->warn("Overpass {$endpoint} injoignable, instance suivante…");
            }
        }

        return null;
    }

    /**
     * « 7 Rue Santeuil » quand OSM connaît l'adresse, « Nantes » sinon
     * (l'essentiel des bars OSM n'a que des coordonnées).
     *
     * @param  array<string, string>  $tags
     */
    private function addressLine(array $tags): string
    {
        $street = $tags['addr:street'] ?? null;

        if (! $street) {
            return 'Nantes';
        }

        return trim(($tags['addr:housenumber'] ?? '').' '.$street);
    }

    /**
     * Réduit un code postal arbitraire à 5 chiffres max, repli sur 44000.
     */
    private function normalizePostalCode(?string $code): string
    {
        $digits = preg_replace('/\D/', '', (string) $code) ?: '44000';

        return substr($digits, 0, 5);
    }

    /**
     * Ambiance déduite du type OSM, affinée par les mots-clés du nom :
     * une « cave à vin » taguée bar relève plus de l'afterwork que du chill.
     */
    private function moodFor(string $name, string $amenity): string
    {
        $haystack = Str::lower(Str::ascii($name));

        $map = [
            'festif' => ['club', 'dancing', 'discotheque', 'disco'],
            'afterwork' => ['cave', 'vin', 'wine', 'cocktail', 'rooftop', 'comptoir'],
            'decouverte' => ['cafe', 'kiosque', 'peniche'],
        ];

        foreach ($map as $mood => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return $mood;
                }
            }
        }

        return match ($amenity) {
            'nightclub' => 'festif',
            'pub' => 'afterwork',
            default => 'chill',
        };
    }
}
