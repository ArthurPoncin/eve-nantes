# NantesVibes — Plan Services & Logique Métier

> Ce document est destiné au développeur en charge des **services, jobs et cache Redis**.
> Stack : **PHP 8.3 + Laravel 11**.
>
> Prérequis : le backend de base (migrations, modèles, auth, routes, Horizon installé)
> doit être fourni par l'autre développeur backend (voir `PLAN_BACKEND.md`).

---

## Table des matières

1. [Vue d'ensemble de ta partie](#1-vue-densemble)
2. [Interface avec le backend](#2-interface-avec-le-backend)
3. [Pattern commun — Cache::remember](#3-pattern-commun)
4. [WeatherService](#4-weatherservice)
5. [EventService](#5-eventservice)
6. [PlaceService + Trending Redis](#6-placeservice)
7. [SpotifyService + Token OAuth2](#7-spotifyservice)
8. [TransportService](#8-transportservice)
9. [AIService (Mistral)](#9-aiservice)
10. [MailService](#10-mailservice)
11. [SoireeService — Orchestrateur](#11-soireeservice)
12. [Jobs Horizon](#12-jobs-horizon)
13. [Scheduler](#13-scheduler)
14. [Binding dans AppServiceProvider](#14-binding-appserviceprovider)
15. [Checklist](#15-checklist)

---

## 1. Vue d'ensemble

Tu implémentes la couche **Services** : toute la logique métier et les intégrations avec
les 7 APIs externes. Les controllers (écrits par l'autre dev) t'injectent leurs dépendances
via le container IoC de Laravel.

```
Controller (autre dev)
     │  injection automatique Laravel
     ▼
┌──────────────────────────────────────────┐
│           Ta partie                      │
│                                          │
│  SoireeService  ←── orchestrateur        │
│    │                                     │
│    ├── WeatherService   → OpenWeatherMap │
│    ├── EventService     → OpenAgenda     │
│    ├── PlaceService     → Foursquare     │
│    ├── SpotifyService   → Spotify API    │
│    ├── TransportService → Navitia        │
│    ├── AIService        → Mistral AI     │
│    └── MailService      → Mailjet        │
│                                          │
│  Jobs Horizon                            │
│    ├── GenerateAINarrativeJob  (queue:ai)│
│    ├── SendSoireeEmailJob  (queue:notif) │
│    └── PrefetchWeatherJob  (scheduled)   │
│                                          │
│  Redis patterns                          │
│    ├── cache-aside (Cache::remember)     │
│    ├── token Spotify                     │
│    └── trending sorted set              │
└──────────────────────────────────────────┘
```

---

## 2. Interface avec le backend

### Ce que l'autre dev te fournit

- Les **modèles** : `User`, `Soiree`, `Favorite`, `Review`
- Les **clés de config** dans `config/services.php` (clés API)
- **Horizon installé** et configuré (`config/horizon.php`)
- Les **controllers** avec les méthodes vides qui t'attendent

### Ce que tu lui fournis

Les **signatures publiques** de tes services, qu'il injecte dans ses controllers :

```php
// À communiquer à l'autre dev pour qu'il câble les controllers

WeatherService::getCurrent(): array
EventService::getByMood(string $humeur, string $date): array
PlaceService::getByMood(string $humeur): array
SpotifyService::getPlaylistByMood(string $humeur): array
TransportService::getJourney(string $from, string $to, string $datetime): array
SoireeService::generate(array $params, ?User $user): array
```

Et les **constructeurs des jobs**, pour qu'il puisse les dispatcher :

```php
GenerateAINarrativeJob::__construct(Soiree $soiree)
SendSoireeEmailJob::__construct(Soiree $soiree, array $recipients)
```

---

## 3. Pattern commun

Tous les services utilisent le même pattern **cache-aside** avec la facade `Cache` de Laravel.

```php
// Principe : si la clé existe en Redis → retourne directement
//            sinon → appelle l'API → stocke en Redis avec TTL → retourne
Cache::remember(string $key, $ttl, Closure $callback): mixed

// Exemples
Cache::remember('cache:weather:nantes', now()->addMinutes(10), fn() => /* appel API */);
Cache::remember("cache:events:{$date}:{$humeur}", now()->addMinutes(30), fn() => /* ... */);

// Invalider manuellement
Cache::forget('cache:weather:nantes');

// Vérifier
Cache::has('cache:weather:nantes');
```

### Tableau des clés Redis

| Service | Clé | TTL |
|---|---|---|
| WeatherService | `cache:weather:nantes` | 10 min |
| EventService | `cache:events:{date}:{humeur}` | 30 min |
| PlaceService | `cache:places:{cat}:{zone}` | 1h |
| SpotifyService | `cache:spotify:{humeur}` | 2h |
| SpotifyService (token) | `token:spotify` | 3500s |
| TransportService | `cache:transport:{from}:{to}:{dt}` | 5 min |
| AIService | `cache:ai:{md5(inputs)}` | 1h |
| trending | `trending:spots` | pas de TTL |

---

## 4. WeatherService

**API** : OpenWeatherMap — `GET /data/2.5/weather`
**Clé** : `config('services.openweather.key')`

```php
// app/Services/WeatherService.php
class WeatherService
{
    public function getCurrent(): array
    {
        return Cache::remember('cache:weather:nantes', now()->addMinutes(10), function () {
            $data = Http::get('https://api.openweathermap.org/data/2.5/weather', [
                'q'     => 'Nantes,FR',
                'appid' => config('services.openweather.key'),
                'units' => 'metric',
                'lang'  => 'fr',
            ])->throw()->json();

            return [
                'temp'        => $data['main']['temp'],
                'feels_like'  => $data['main']['feels_like'],
                'description' => $data['weather'][0]['description'],
                'icon'        => $data['weather'][0]['icon'],
                'wind_speed'  => $data['wind']['speed'],
                'humidity'    => $data['main']['humidity'],
            ];
        });
    }
}
```

---

## 5. EventService

**API** : OpenAgenda — `GET /v2/events`
**Clé** : `config('services.openagenda.key')`

```php
// app/Services/EventService.php
class EventService
{
    // Mapping humeur → mots-clés de recherche
    private array $moodKeywords = [
        'chill'      => 'exposition,cinema,musee',
        'festif'     => 'concert,festival,fete',
        'romantique' => 'theatre,diner,spectacle',
        'culturel'   => 'conference,atelier,exposition',
    ];

    public function getByMood(string $humeur, string $date): array
    {
        $cacheKey = "cache:events:{$date}:{$humeur}";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($humeur, $date) {
            $response = Http::get('https://api.openagenda.com/v2/events', [
                'key'             => config('services.openagenda.key'),
                'size'            => 5,
                'sort'            => 'timings.begin',
                'timings[gte]'    => $date,
                'keyword'         => $this->moodKeywords[$humeur] ?? '',
                'location[city]'  => 'Nantes',
            ])->throw()->json();

            return collect($response['events'] ?? [])->map(fn($e) => [
                'id'          => $e['uid'],
                'title'       => $e['title']['fr'] ?? $e['title'],
                'description' => strip_tags($e['description']['fr'] ?? ''),
                'date'        => $e['nextTiming']['begin'] ?? null,
                'location'    => $e['location']['name'] ?? null,
                'image'       => $e['image']['base'] ?? null,
                'link'        => $e['canonicalUrl'] ?? null,
            ])->all();
        });
    }
}
```

---

## 6. PlaceService

**API** : Foursquare Places — `GET /v3/places/search`
**Clé** : `config('services.foursquare.key')`

```php
// app/Services/PlaceService.php
class PlaceService
{
    // Foursquare category IDs
    private array $moodCategories = [
        'chill'      => '13065',   // Café
        'festif'     => '13003',   // Bar
        'romantique' => '13064',   // Restaurant gastronomique
        'culturel'   => '10000',   // Arts & Entertainment
    ];

    public function getByMood(string $humeur, string $zone = 'Nantes'): array
    {
        $cat      = $this->moodCategories[$humeur] ?? '13003';
        $cacheKey = "cache:places:{$cat}:{$zone}";

        return Cache::remember($cacheKey, now()->addHour(), function () use ($cat, $zone) {
            $items = Http::withToken(config('services.foursquare.key'))
                ->get('https://api.foursquare.com/v3/places/search', [
                    'near'       => $zone . ',FR',
                    'categories' => $cat,
                    'limit'      => 5,
                    'fields'     => 'name,location,rating,categories,hours,photos',
                ])->throw()->json('results');

            return collect($items)->map(fn($p) => [
                'id'       => $p['fsq_id'],
                'name'     => $p['name'],
                'address'  => $p['location']['formatted_address'] ?? null,
                'rating'   => $p['rating'] ?? null,
                'category' => $p['categories'][0]['name'] ?? null,
                'lat'      => $p['geocodes']['main']['latitude'] ?? null,
                'lng'      => $p['geocodes']['main']['longitude'] ?? null,
            ])->all();
        });
    }
}
```

### Trending Sorted Set

À chaque fois qu'un lieu est affiché, incrémente son score dans Redis :

```php
// Appeler dans PlaceService::getByMood() ou depuis le controller
public function trackView(string $placeId, string $placeName): void
{
    Redis::zincrby('trending:spots', 1, "{$placeId}:{$placeName}");
}

// Récupérer le top 10 (utilisé dans StatsController)
public function getTopSpots(int $limit = 10): array
{
    $raw = Redis::zrevrange('trending:spots', 0, $limit - 1, 'WITHSCORES');

    // $raw = ['id1:nom1', score1, 'id2:nom2', score2, ...]
    $result = [];
    foreach (array_chunk($raw, 2) as [$key, $score]) {
        [$id, $name] = explode(':', $key, 2);
        $result[] = ['id' => $id, 'name' => $name, 'views' => (int) $score];
    }

    return $result;
}
```

---

## 7. SpotifyService

**API** : Spotify Web API (OAuth2 Client Credentials)
**Clés** : `config('services.spotify.client_id')` / `client_secret`

Le token Spotify est stocké dans Redis avec TTL calqué sur `expires_in`.

```php
// app/Services/SpotifyService.php
class SpotifyService
{
    private array $moodQueries = [
        'chill'      => 'lofi chill',
        'festif'     => 'party hits',
        'romantique' => 'romantic jazz',
        'culturel'   => 'classical focus',
    ];

    private function getAccessToken(): string
    {
        // Cache::remember évite un appel Spotify à chaque requête
        return Cache::remember('token:spotify', now()->addSeconds(3500), function () {
            $response = Http::asForm()
                ->withBasicAuth(
                    config('services.spotify.client_id'),
                    config('services.spotify.client_secret')
                )
                ->post('https://accounts.spotify.com/api/token', [
                    'grant_type' => 'client_credentials',
                ])->throw()->json();

            return $response['access_token'];
        });
    }

    public function getPlaylistByMood(string $humeur): array
    {
        return Cache::remember("cache:spotify:{$humeur}", now()->addHours(2), function () use ($humeur) {
            $query = $this->moodQueries[$humeur] ?? $humeur;

            $items = Http::withToken($this->getAccessToken())
                ->get('https://api.spotify.com/v1/search', [
                    'q'      => $query,
                    'type'   => 'playlist',
                    'market' => 'FR',
                    'limit'  => 3,
                ])->throw()->json('playlists.items');

            return collect($items)->map(fn($p) => [
                'id'          => $p['id'],
                'name'        => $p['name'],
                'description' => $p['description'],
                'image'       => $p['images'][0]['url'] ?? null,
                'url'         => $p['external_urls']['spotify'],
                'tracks'      => $p['tracks']['total'],
            ])->all();
        });
    }
}
```

---

## 8. TransportService

**API** : Navitia — `GET /v1/coverage/fr-nw/journeys`
**Clé** : `config('services.navitia.key')`

```php
// app/Services/TransportService.php
class TransportService
{
    public function getJourney(string $from, string $to, string $datetime): array
    {
        // from/to = "lat;lng" (ex: "47.2184;-1.5536")
        $cacheKey = "cache:transport:{$from}:{$to}:{$datetime}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($from, $to, $datetime) {
            $data = Http::withBasicAuth(config('services.navitia.key'), '')
                ->get('https://api.navitia.io/v1/coverage/fr-nw/journeys', [
                    'from'     => $from,
                    'to'       => $to,
                    'datetime' => $datetime,     // format : YYYYMMDDTHHmmss
                    'count'    => 3,
                ])->throw()->json();

            return collect($data['journeys'] ?? [])->map(fn($j) => [
                'duration'    => $j['duration'],
                'departure'   => $j['departure_date_time'],
                'arrival'     => $j['arrival_date_time'],
                'sections'    => collect($j['sections'])->map(fn($s) => [
                    'mode'  => $s['display_informations']['physical_mode'] ?? $s['type'],
                    'line'  => $s['display_informations']['label'] ?? null,
                    'from'  => $s['from']['name'] ?? null,
                    'to'    => $s['to']['name'] ?? null,
                ])->all(),
            ])->all();
        });
    }
}
```

---

## 9. AIService

**API** : Mistral AI — `POST /v1/chat/completions`
**Modèle** : `mistral-small-latest` (free tier)
**Clé** : `config('services.mistral.key')`

> L'appel Mistral peut prendre 1–3 secondes. Il est donc toujours appelé
> depuis un **Job Horizon** (non bloquant), jamais directement dans un controller.

```php
// app/Services/AIService.php
class AIService
{
    public function generateNarrative(array $context): string
    {
        // Cache basé sur un hash des inputs pour éviter les doubles appels à contexte identique
        $cacheKey = 'cache:ai:' . md5(json_encode($context));

        return Cache::remember($cacheKey, now()->addHour(), function () use ($context) {
            $prompt = $this->buildPrompt($context);

            $response = Http::withToken(config('services.mistral.key'))
                ->post('https://api.mistral.ai/v1/chat/completions', [
                    'model'    => 'mistral-small-latest',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Tu es un guide local nantais enthousiaste et poétique.'],
                        ['role' => 'user',   'content' => $prompt],
                    ],
                    'max_tokens' => 250,
                ])->throw()->json();

            return $response['choices'][0]['message']['content'];
        });
    }

    private function buildPrompt(array $ctx): string
    {
        return <<<PROMPT
        Génère une courte description narrative (3-4 phrases) pour cette soirée à Nantes :
        - Humeur : {$ctx['humeur']}
        - Météo : {$ctx['weather']['description']}, {$ctx['weather']['temp']}°C
        - Événement : {$ctx['event']['title']} à {$ctx['event']['location']}
        - Lieu : {$ctx['place']['name']} ({$ctx['place']['category']})

        Sois poétique, local, et donne envie d'y aller ce soir.
        PROMPT;
    }
}
```

---

## 10. MailService

**API** : Mailjet via Laravel Mail
**Config** : driver `mailjet` dans `.env`

```php
// app/Services/MailService.php
class MailService
{
    /**
     * Envoie la soirée par email.
     * Rate limit : max 5 emails/jour par user (géré dans AppServiceProvider).
     */
    public function sendSoiree(Soiree $soiree, array $recipients): void
    {
        foreach ($recipients as $email) {
            Mail::to($email)->send(new SoireeMail($soiree));
        }
    }
}
```

### Mailable

```php
// app/Mail/SoireeMail.php
class SoireeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Soiree $soiree) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Ta soirée NantesVibes t'attend !");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.soiree');
    }
}
```

### Template Blade — `resources/views/emails/soiree.blade.php`

```html
<!DOCTYPE html>
<html>
<body>
    <h1>Ta soirée à Nantes 🌃</h1>

    <p>{{ $soiree->ai_narrative }}</p>

    <h2>Météo</h2>
    <p>{{ $soiree->weather_data['description'] }} — {{ $soiree->weather_data['temp'] }}°C</p>

    <h2>Au programme</h2>
    <p><strong>Événement :</strong> {{ $soiree->event_data['title'] }}</p>
    <p><strong>Lieu :</strong> {{ $soiree->place_data['name'] }}</p>
    @if($soiree->playlist_url)
    <p><a href="{{ $soiree->playlist_url }}">Écouter la playlist Spotify</a></p>
    @endif
</body>
</html>
```

---

## 11. SoireeService

L'orchestrateur principal. Il appelle tous les services et crée la soirée en BDD.

```php
// app/Services/SoireeService.php
class SoireeService
{
    public function __construct(
        private readonly WeatherService   $weather,
        private readonly EventService     $events,
        private readonly PlaceService     $places,
        private readonly SpotifyService   $spotify,
        private readonly TransportService $transport,
    ) {}

    public function generate(array $params, ?User $user): array
    {
        $humeur = $params['humeur'];
        $budget = $params['budget'];
        $date   = $params['date'] ?? now()->format('Y-m-d');

        // Appels en parallèle (ne bloquent pas les uns les autres si cachés)
        $weather   = $this->weather->getCurrent();
        $events    = $this->events->getByMood($humeur, $date);
        $places    = $this->places->getByMood($humeur);
        $playlists = $this->spotify->getPlaylistByMood($humeur);

        $soiree = [
            'humeur'    => $humeur,
            'budget'    => $budget,
            'weather'   => $weather,
            'events'    => $events,
            'places'    => $places,
            'playlists' => $playlists,
            'narrative' => null,   // rempli de manière async par Horizon
        ];

        // Sauvegarde + dispatch jobs si utilisateur connecté
        if ($user) {
            $model = $user->soirees()->create([
                'id'             => Str::uuid(),
                'humeur'         => $humeur,
                'budget'         => $budget,
                'weather_data'   => $weather,
                'event_data'     => $events[0] ?? [],
                'place_data'     => $places[0] ?? [],
                'transport_data' => [],
                'playlist_url'   => $playlists[0]['url'] ?? null,
            ]);

            // Non bloquant : la narration IA sera écrite en BDD quand Horizon la traite
            GenerateAINarrativeJob::dispatch($model);

            $soiree['soiree_id'] = $model->id;
        }

        return $soiree;
    }
}
```

---

## 12. Jobs Horizon

### GenerateAINarrativeJob

Appelle Mistral AI et écrit la narration dans la soirée sauvegardée.

```php
// app/Jobs/GenerateAINarrativeJob.php
class GenerateAINarrativeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue   = 'ai';
    public int    $tries   = 2;
    public int    $timeout = 120;   // Mistral peut être lent

    public function __construct(public readonly Soiree $soiree) {}

    public function handle(AIService $ai): void
    {
        $narrative = $ai->generateNarrative([
            'humeur'  => $this->soiree->humeur,
            'weather' => $this->soiree->weather_data,
            'event'   => $this->soiree->event_data,
            'place'   => $this->soiree->place_data,
        ]);

        $this->soiree->update(['ai_narrative' => $narrative]);
    }

    public function failed(\Throwable $e): void
    {
        // Non bloquant pour l'user — on log juste l'erreur
        Log::error("GenerateAINarrativeJob failed for soiree {$this->soiree->id}: {$e->getMessage()}");
    }
}
```

### SendSoireeEmailJob

Envoie les emails aux destinataires de manière asynchrone.

```php
// app/Jobs/SendSoireeEmailJob.php
class SendSoireeEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'notifications';
    public int    $tries = 3;

    public function __construct(
        public readonly Soiree $soiree,
        public readonly array  $recipients,
    ) {}

    public function handle(MailService $mail): void
    {
        $mail->sendSoiree($this->soiree, $this->recipients);
    }
}
```

### PrefetchWeatherJob

Réchauffe le cache météo avant qu'il n'expire. Déclenché par le scheduler.

```php
// app/Jobs/PrefetchWeatherJob.php
class PrefetchWeatherJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(WeatherService $weather): void
    {
        Cache::forget('cache:weather:nantes');
        $weather->getCurrent();   // re-remplit le cache
    }
}
```

---

## 13. Scheduler

Le scheduler déclenche les jobs de préchargement automatiquement.

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::job(new PrefetchWeatherJob)->everyTenMinutes();
```

Le container `scheduler` dans Docker tourne `php artisan schedule:work` en continu.
En production, remplacer par une crontab :

```
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

---

## 14. Binding dans AppServiceProvider

Les services sont enregistrés dans le container IoC pour que Laravel les injecte automatiquement.

```php
// app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->singleton(WeatherService::class);
    $this->app->singleton(EventService::class);
    $this->app->singleton(PlaceService::class);
    $this->app->singleton(SpotifyService::class);
    $this->app->singleton(TransportService::class);
    $this->app->singleton(AIService::class);
    $this->app->singleton(MailService::class);
    $this->app->singleton(SoireeService::class);
}
```

> `singleton` = une seule instance par requête HTTP → économise la mémoire et évite
> de recréer les services à chaque injection.

---

## 15. Checklist

### Phase 1 — Services de données (sans dépendances entre eux)
- [ ] `WeatherService::getCurrent()` + cache 10 min
- [ ] `EventService::getByMood()` + cache 30 min
- [ ] `PlaceService::getByMood()` + cache 1h
- [ ] `PlaceService::trackView()` + `getTopSpots()` (Redis sorted set)
- [ ] `TransportService::getJourney()` + cache 5 min

### Phase 2 — Services avec auth externe
- [ ] `SpotifyService::getAccessToken()` → token OAuth2 dans Redis (TTL 3500s)
- [ ] `SpotifyService::getPlaylistByMood()` + cache 2h
- [ ] `AIService::generateNarrative()` + cache 1h par hash

### Phase 3 — Mail & Mailable
- [ ] `MailService::sendSoiree()`
- [ ] `SoireeMail` Mailable + template Blade `emails/soiree.blade.php`

### Phase 4 — Jobs Horizon
- [ ] `GenerateAINarrativeJob` (queue `ai`, timeout 120s)
- [ ] `SendSoireeEmailJob` (queue `notifications`)
- [ ] `PrefetchWeatherJob` (scheduled)
- [ ] Tester depuis Tinker : `GenerateAINarrativeJob::dispatch($soiree)`
- [ ] Vérifier les jobs dans le dashboard `/horizon`

### Phase 5 — SoireeService & intégration
- [ ] `SoireeService::generate()` orchestrant tous les services
- [ ] Binding des services dans `AppServiceProvider`
- [ ] Brancher `SoireeService` dans `SoireeController` (avec l'autre dev)
- [ ] Brancher `PlaceService::getTopSpots()` dans `StatsController`
- [ ] Scheduler `PrefetchWeatherJob` toutes les 10 min
- [ ] Tests end-to-end : générer une soirée complète

---

*Dernière mise à jour : 2026-04-09*
