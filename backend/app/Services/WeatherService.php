<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WeatherService
{
    /**
     * Météo actuelle à Nantes, mappée depuis Open-Meteo (API gratuite, sans clé).
     *
     * Mise en cache 10 min (cache-aside Redis en prod) pour éviter de
     * surcharger l'API externe — la météo n'évolue pas à la minute.
     *
     * @return array{temp: float, feels_like: float, condition: string, icon: string, wind: float, humidity: int}
     */
    public function current(): array
    {
        return Cache::remember('cache:weather:nantes', now()->addMinutes(10), function (): array {
            $data = Http::get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => config('services.open_meteo.latitude'),
                'longitude' => config('services.open_meteo.longitude'),
                'current' => 'temperature_2m,apparent_temperature,relative_humidity_2m,weather_code,wind_speed_10m,is_day',
                'wind_speed_unit' => 'kmh',
                'timezone' => 'Europe/Paris',
            ])->throw()->json('current');

            [$condition, $icon] = $this->describe(
                (int) ($data['weather_code'] ?? 0),
                (int) ($data['is_day'] ?? 1) === 1,
            );

            return [
                'temp' => (float) ($data['temperature_2m'] ?? 0),
                'feels_like' => (float) ($data['apparent_temperature'] ?? 0),
                'condition' => $condition,
                'icon' => $icon,
                'wind' => (float) ($data['wind_speed_10m'] ?? 0),
                'humidity' => (int) ($data['relative_humidity_2m'] ?? 0),
            ];
        });
    }

    /**
     * Traduit un code météo WMO en libellé FR + code d'icône (compatible
     * openweathermap.org/img/wn, que le front utilise déjà).
     *
     * @return array{0: string, 1: string}
     */
    private function describe(int $code, bool $isDay): array
    {
        $suffix = $isDay ? 'd' : 'n';

        $map = [
            0 => ['Ciel dégagé', '01'],
            1 => ['Plutôt dégagé', '02'],
            2 => ['Partiellement nuageux', '03'],
            3 => ['Couvert', '04'],
            45 => ['Brouillard', '50'],
            48 => ['Brouillard givrant', '50'],
            51 => ['Bruine légère', '09'],
            53 => ['Bruine', '09'],
            55 => ['Bruine dense', '09'],
            56 => ['Bruine verglaçante', '09'],
            57 => ['Bruine verglaçante', '09'],
            61 => ['Pluie faible', '10'],
            63 => ['Pluie', '10'],
            65 => ['Forte pluie', '10'],
            66 => ['Pluie verglaçante', '10'],
            67 => ['Pluie verglaçante', '10'],
            71 => ['Neige faible', '13'],
            73 => ['Neige', '13'],
            75 => ['Forte neige', '13'],
            77 => ['Grains de neige', '13'],
            80 => ['Averses', '09'],
            81 => ['Averses', '09'],
            82 => ['Fortes averses', '09'],
            85 => ['Averses de neige', '13'],
            86 => ['Averses de neige', '13'],
            95 => ['Orage', '11'],
            96 => ['Orage et grêle', '11'],
            99 => ['Orage et grêle', '11'],
        ];

        [$condition, $iconBase] = $map[$code] ?? ['Indisponible', '01'];

        return [$condition, $iconBase.$suffix];
    }
}
