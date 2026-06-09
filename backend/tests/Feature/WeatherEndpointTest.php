<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherEndpointTest extends TestCase
{
    /**
     * @return array<string, \Illuminate\Http\Client\Response>
     */
    private function fakeOpenMeteo(): array
    {
        return [
            'api.open-meteo.com/*' => Http::response([
                'current' => [
                    'temperature_2m' => 12.5,
                    'apparent_temperature' => 10.2,
                    'relative_humidity_2m' => 78,
                    'weather_code' => 3,
                    'wind_speed_10m' => 9.4,
                    'is_day' => 1,
                ],
            ]),
        ];
    }

    public function test_it_returns_current_nantes_weather_mapped_from_open_meteo(): void
    {
        Http::fake($this->fakeOpenMeteo());

        $response = $this->getJson('/api/v1/weather');

        $response->assertOk()->assertExactJson([
            'temp' => 12.5,
            'feels_like' => 10.2,
            'condition' => 'Couvert',
            'icon' => '04d',
            'wind' => 9.4,
            'humidity' => 78,
        ]);
    }

    public function test_it_caches_the_weather_and_calls_open_meteo_only_once(): void
    {
        Http::fake($this->fakeOpenMeteo());

        $this->getJson('/api/v1/weather')->assertOk();
        $this->getJson('/api/v1/weather')->assertOk();

        Http::assertSentCount(1);
    }
}
