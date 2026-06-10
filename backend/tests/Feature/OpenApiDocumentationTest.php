<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OpenApiDocumentationTest extends TestCase
{
    /**
     * Chaque opération exposée par routes/api.php doit figurer dans la spec.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const EXPECTED_OPERATIONS = [
        ['get', '/api/v1/weather'],
        ['get', '/api/v1/events'],
        ['get', '/api/v1/venues'],
        ['get', '/api/v1/venues/{venue}'],
        ['get', '/api/v1/venues/{venue}/transport'],
        ['get', '/api/v1/venues/{venue}/reviews'],
        ['post', '/api/v1/venues/{venue}/reviews'],
        ['post', '/api/v1/soiree/generate'],
        ['post', '/api/v1/soiree/share'],
        ['post', '/api/v1/auth/register'],
        ['post', '/api/v1/auth/login'],
        ['get', '/api/v1/auth/me'],
        ['post', '/api/v1/auth/logout'],
        ['get', '/api/v1/favorites'],
        ['post', '/api/v1/venues/{venue}/favorite'],
        ['delete', '/api/v1/venues/{venue}/favorite'],
        ['get', '/api/v1/badges'],
    ];

    public function test_la_spec_openapi_se_genere_et_documente_tous_les_endpoints(): void
    {
        $this->artisan('l5-swagger:generate')->assertSuccessful();

        $spec = json_decode(File::get(storage_path('api-docs/api-docs.json')), true);

        $this->assertSame('NOCTAMBULE API', $spec['info']['title']);
        $this->assertSame('http', $spec['components']['securitySchemes']['sanctum']['type']);
        $this->assertSame('bearer', $spec['components']['securitySchemes']['sanctum']['scheme']);

        foreach (self::EXPECTED_OPERATIONS as [$method, $path]) {
            $this->assertArrayHasKey($path, $spec['paths'], "Chemin absent de la spec : {$path}");
            $this->assertArrayHasKey($method, $spec['paths'][$path], "Opération absente : {$method} {$path}");
        }
    }

    public function test_les_routes_protegees_declarent_la_securite_sanctum(): void
    {
        $this->artisan('l5-swagger:generate')->assertSuccessful();

        $spec = json_decode(File::get(storage_path('api-docs/api-docs.json')), true);

        foreach ([
            ['get', '/api/v1/auth/me'],
            ['post', '/api/v1/auth/logout'],
            ['get', '/api/v1/favorites'],
            ['post', '/api/v1/venues/{venue}/favorite'],
            ['delete', '/api/v1/venues/{venue}/favorite'],
            ['post', '/api/v1/venues/{venue}/reviews'],
            ['get', '/api/v1/badges'],
        ] as [$method, $path]) {
            $this->assertSame(
                [['sanctum' => []]],
                $spec['paths'][$path][$method]['security'] ?? null,
                "Sécurité sanctum manquante : {$method} {$path}",
            );
        }
    }

    public function test_swagger_ui_est_accessible(): void
    {
        $this->artisan('l5-swagger:generate')->assertSuccessful();

        $this->get('/api/documentation')
            ->assertOk()
            ->assertSee('NOCTAMBULE API');
    }
}
