<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherEndpointTest extends TestCase
{
    public function test_it_returns_current_nantes_weather_mapped_from_openweathermap(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response([
                'main' => ['temp' => 12.5, 'feels_like' => 10.2, 'humidity' => 78],
                'weather' => [['description' => 'nuageux', 'icon' => '04n']],
                'wind' => ['speed' => 3.4],
            ]),
        ]);

        $response = $this->getJson('/api/v1/weather');

        $response->assertOk()->assertExactJson([
            'temp' => 12.5,
            'feels_like' => 10.2,
            'condition' => 'nuageux',
            'icon' => '04n',
            'wind' => 3.4,
            'humidity' => 78,
        ]);
    }

    public function test_it_caches_the_weather_and_calls_openweathermap_only_once(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response([
                'main' => ['temp' => 12.5, 'feels_like' => 10.2, 'humidity' => 78],
                'weather' => [['description' => 'nuageux', 'icon' => '04n']],
                'wind' => ['speed' => 3.4],
            ]),
        ]);

        $this->getJson('/api/v1/weather')->assertOk();
        $this->getJson('/api/v1/weather')->assertOk();

        Http::assertSentCount(1);
    }
}
