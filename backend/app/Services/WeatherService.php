<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WeatherService
{
    /**
     * Météo actuelle à Nantes, mappée depuis OpenWeatherMap.
     *
     * Mise en cache 10 min (cache-aside Redis en prod) pour éviter de
     * surcharger l'API externe — la météo n'évolue pas à la minute.
     *
     * @return array{temp: float, feels_like: float, condition: string, icon: string, wind: float, humidity: int}
     */
    public function current(): array
    {
        return Cache::remember('cache:weather:nantes', now()->addMinutes(10), function (): array {
            $data = Http::get('https://api.openweathermap.org/data/2.5/weather', [
                'q' => config('services.openweather.city'),
                'appid' => config('services.openweather.key'),
                'units' => 'metric',
                'lang' => 'fr',
            ])->throw()->json();

            return [
                'temp' => $data['main']['temp'],
                'feels_like' => $data['main']['feels_like'],
                'condition' => $data['weather'][0]['description'],
                'icon' => $data['weather'][0]['icon'],
                'wind' => $data['wind']['speed'],
                'humidity' => $data['main']['humidity'],
            ];
        });
    }
}
