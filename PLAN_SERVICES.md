# NOCTAMBULE — Plan Services & Logique Métier

> Ce document est destiné au développeur en charge des **services, jobs et cache Redis**.
> Stack : **PHP 8.3 + Laravel 12**.
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
6. [VenueService + Affluence + Trending Redis](#6-venueservice)
7. [PlaceService (Foursquare — complémentaire)](#7-placeservice)
8. [TransportService](#8-transportservice)
9. [AIService — multi-provider](#9-aiservice)
10. [MailService](#10-mailservice)
11. [BadgeService — gamification](#11-badgeservice)
12. [SoireeService — Orchestrateur](#12-soireeservice)
13. [Jobs Horizon](#13-jobs-horizon)
14. [Scheduler](#14-scheduler)
15. [Binding dans AppServiceProvider](#15-binding-appserviceprovider)
16. [Checklist](#16-checklist)

---

## 1. Vue d'ensemble

Tu implémentes la couche **Services** : logique métier et intégrations avec les APIs externes.
Les controllers (écrits par l'autre dev) t'injectent leurs dépendances via le container IoC de Laravel.

```
Controller (autre dev)
     │  injection automatique Laravel
     ▼
┌──────────────────────────────────────────────┐
│           Ta partie                          │
│                                              │
│  SoireeService  ←── orchestrateur            │
│    │                                         │
│    ├── WeatherService     → OpenWeatherMap   │
│    ├── EventService       → OpenAgenda       │
│    ├── VenueService       → BDD locale       │
│    ├── PlaceService       → Foursquare       │
│    ├── TransportService   → Navitia / TAN    │
│    ├── AIService          → Mistral|Gemma|Ollama │
│    ├── MailService        → Mailjet          │
│    └── BadgeService       → BDD locale       │
│                                              │
│  Jobs Horizon                                │
│    ├── GenerateAINarrativeJob (queue:ai)     │
│    ├── SendSoireeEmailJob    (queue:notif)   │
│    ├── CheckBadgesJob        (queue:default) │
│    └── PrefetchWeatherJob    (scheduled)     │
│                                              │
│  Redis patterns                              │
│    ├── cache-aside (Cache::remember)         │
│    ├── crowd estimation                      │
│    └── trending sorted set                   │
└──────────────────────────────────────────────┘
```

---

## 2. Interface avec le backend

### Ce que l'autre dev te fournit

- Les **modèles** : `User`, `Venue`, `Event`, `Soiree`, `Favorite`, `Review`, `Badge`, `UserBadge`
- Les **clés de config** dans `config/services.php` (clés API + bloc `ai`)
- **Horizon installé** et configuré (`config/horizon.php`)
- Les **controllers** avec les méthodes vides qui t'attendent

### Ce que tu lui fournis

Les **signatures publiques** de tes services, qu'il injecte dans ses controllers :

```php
WeatherService::getCurrent(): array
EventService::getByMood(string $mood, string $date): array
VenueService::list(array $filters = []): array
VenueService::getBySlug(string $slug): array
VenueService::getCrowd(string $slug): array              // ['percent' => 87, 'label' => 'Bondé']
PlaceService::getNearby(float $lat, float $lng, string $category): array
TransportService::getJourney(string $from, string $to, string $datetime): array
TransportService::getNextPassages(string $stopId): array
AIService::generateNarrative(array $context): string
BadgeService::forUser(User $user): array                 // [unlocked, locked]
SoireeService::generate(array $params, ?User $user): array
```

Et les **constructeurs des jobs**, pour qu'il puisse les dispatcher :

```php
GenerateAINarrativeJob::__construct(Soiree $soiree)
SendSoireeEmailJob::__construct(Soiree $soiree, array $recipients)
CheckBadgesJob::__construct(User $user)
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
Cache::remember("cache:events:{$date}:{$mood}", now()->addMinutes(30), fn() => /* ... */);

// Invalider manuellement
Cache::forget('cache:weather:nantes');

// Vérifier
Cache::has('cache:weather:nantes');
```

### Tableau des clés Redis

| Service | Clé | TTL |
|---|---|---|
| WeatherService | `cache:weather:nantes` | 10 min |
| EventService | `cache:events:{date}:{mood}` | 30 min |
| VenueService | `cache:venue:crowd:{slug}` | 5 min |
| VenueService (trending) | `trending:spots` | pas de TTL |
| PlaceService (Foursquare) | `cache:places:{cat}:{zone}` | 1 h |
| TransportService | `cache:tan:{stopId}` | 60 s |
| TransportService | `cache:transport:{from}:{to}:{dt}` | 5 min |
| AIService | `cache:ai:{md5(inputs)}` | 1 h |

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
                'condition'   => $data['weather'][0]['description'],
                'icon'        => $data['weather'][0]['icon'],
                'wind'        => $data['wind']['speed'],
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
    // Mapping mood → mots-clés de recherche
    private array $moodKeywords = [
        'festif'     => 'concert,festival,fete,club',
        'chill'      => 'cocktail,bar,jazz,acoustique',
        'decouverte' => 'exposition,confidentiel,electro',
        'afterwork'  => 'afterwork,bar,quiz',
    ];

    public function getByMood(string $mood, string $date): array
    {
        $cacheKey = "cache:events:{$date}:{$mood}";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($mood, $date) {
            $response = Http::get('https://api.openagenda.com/v2/events', [
                'key'             => config('services.openagenda.key'),
                'size'            => 5,
                'sort'            => 'timings.begin',
                'timings[gte]'    => $date,
                'keyword'         => $this->moodKeywords[$mood] ?? '',
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

## 6. VenueService

> Service principal pour les venues. La source de vérité est la **BDD locale**
> (les 6 venues seedés). Foursquare est utilisé séparément (cf. `PlaceService`).

```php
// app/Services/VenueService.php
class VenueService
{
    public function list(array $filters = []): array
    {
        return Venue::query()
            ->when($filters['mood']     ?? null, fn($q, $v) => $q->where('mood', $v))
            ->when($filters['district'] ?? null, fn($q, $v) => $q->where('district', $v))
            ->when($filters['type']     ?? null, fn($q, $v) => $q->where('type', $v))
            ->orderBy('name')
            ->get()
            ->map(fn(Venue $v) => $this->present($v))
            ->all();
    }

    public function getBySlug(string $slug): array
    {
        $venue = Venue::with(['tonight', 'reviews'])->where('slug', $slug)->firstOrFail();

        // Track pour le trending sorted set
        Redis::zincrby('trending:spots', 1, "{$venue->id}:{$venue->name}");

        return [
            ...$this->present($venue),
            'tonight' => $venue->tonight ? [
                'id'        => $venue->tonight->id,
                'title'     => $venue->tonight->title,
                'starts_at' => $venue->tonight->starts_at,
                'ends_at'   => $venue->tonight->ends_at,
            ] : null,
            'reviews_count' => $venue->reviews->count(),
            'rating'        => round($venue->reviews->avg('rating') ?? 0, 1),
            'crowd'         => $this->getCrowd($slug),
        ];
    }

    public function getCrowd(string $slug): array
    {
        return Cache::remember("cache:venue:crowd:{$slug}", now()->addMinutes(5), function () use ($slug) {
            $venue = Venue::where('slug', $slug)->firstOrFail();
            return $this->estimateCrowd($venue);
        });
    }

    /**
     * Affluence estimée — déterministe pour la démo.
     * Combine : heure du jour + jour de la semaine + popularité (rating + tags).
     */
    private function estimateCrowd(Venue $venue): array
    {
        $hour    = now()->hour;
        $weekend = in_array(now()->dayOfWeek, [5, 6]); // ven/sam

        // Courbe d'affluence par type
        $base = match ($venue->type) {
            'club'  => $hour >= 23 || $hour < 5 ? 80 : ($hour >= 21 ? 50 : 15),
            'bar'   => $hour >= 19 && $hour < 2 ? 65 : 25,
            'salle' => $hour >= 20 && $hour < 1 ? 70 : 20,
            'pub'   => $hour >= 18 && $hour < 1 ? 55 : 30,
        };

        $bonus = $weekend ? 15 : 0;
        $rating = round(($venue->reviews()->avg('rating') ?? 4.5) * 4);   // 0–20
        $percent = min(99, $base + $bonus + ($rating - 18));

        $label = match (true) {
            $percent >= 85 => 'Bondé',
            $percent >= 65 => 'Affluence forte',
            $percent >= 45 => 'Animé',
            default        => 'Confortable',
        };

        return ['percent' => (int) $percent, 'label' => $label];
    }

    public function getTopSpots(int $limit = 10): array
    {
        $raw = Redis::zrevrange('trending:spots', 0, $limit - 1, 'WITHSCORES');

        $result = [];
        foreach (array_chunk($raw, 2) as [$key, $score]) {
            [$id, $name] = explode(':', $key, 2);
            $result[] = ['id' => $id, 'name' => $name, 'views' => (int) $score];
        }

        return $result;
    }

    private function present(Venue $v): array
    {
        return [
            'id'             => $v->id,
            'slug'           => $v->slug,
            'name'           => $v->name,
            'type'           => $v->type,
            'district'       => $v->district,
            'mood'           => $v->mood,
            'music'          => $v->music,
            'price'          => $v->price,
            'cover'          => $v->cover,
            'time_open'      => $v->time_open,
            'lat'            => (float) $v->lat,
            'lng'            => (float) $v->lng,
            'transport_hint' => $v->transport_hint,
            'photo_url'      => $v->photo_url,
            'tags'           => $v->tags,
        ];
    }
}
```

---

## 7. PlaceService

**API** : Foursquare Places — `GET /v3/places/search`
**Clé** : `config('services.foursquare.key')`

> Foursquare est utilisé en **complément** du catalogue venues local : par exemple,
> trouver un café d'after à proximité d'un venue, ou suggérer des lieux pour
> élargir la balade. Ce n'est **pas** la source primaire des venues.

```php
// app/Services/PlaceService.php
class PlaceService
{
    public function getNearby(float $lat, float $lng, string $category = '13003'): array
    {
        $cacheKey = "cache:places:{$category}:{$lat},{$lng}";

        return Cache::remember($cacheKey, now()->addHour(), function () use ($lat, $lng, $category) {
            $items = Http::withToken(config('services.foursquare.key'))
                ->get('https://api.foursquare.com/v3/places/search', [
                    'll'         => "{$lat},{$lng}",
                    'radius'     => 500,
                    'categories' => $category,
                    'limit'      => 5,
                    'fields'     => 'name,location,rating,categories,hours,photos,geocodes',
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

---

## 8. TransportService

**API** : Navitia (itinéraires) + TAN OpenData (temps réel par arrêt)

```php
// app/Services/TransportService.php
class TransportService
{
    /**
     * Itinéraire complet entre deux points.
     * `from`/`to` au format "lat;lng" (ex: "47.2184;-1.5536").
     */
    public function getJourney(string $from, string $to, string $datetime): array
    {
        $cacheKey = "cache:transport:{$from}:{$to}:{$datetime}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($from, $to, $datetime) {
            $data = Http::withBasicAuth(config('services.navitia.key'), '')
                ->get('https://api.navitia.io/v1/coverage/fr-nw/journeys', [
                    'from'     => $from,
                    'to'       => $to,
                    'datetime' => $datetime,
                    'count'    => 3,
                ])->throw()->json();

            return collect($data['journeys'] ?? [])->map(fn($j) => [
                'duration'  => $j['duration'],
                'departure' => $j['departure_date_time'],
                'arrival'   => $j['arrival_date_time'],
                'sections'  => collect($j['sections'])->map(fn($s) => [
                    'mode' => $s['display_informations']['physical_mode'] ?? $s['type'],
                    'line' => $s['display_informations']['label'] ?? null,
                    'from' => $s['from']['name'] ?? null,
                    'to'   => $s['to']['name'] ?? null,
                ])->all(),
            ])->all();
        });
    }

    /**
     * Prochains passages à un arrêt TAN — alimenté par l'API publique TAN.
     * Plus simple/rapide que Navitia pour le widget "next tram" de la maquette.
     */
    public function getNextPassages(string $stopId): array
    {
        return Cache::remember("cache:tan:{$stopId}", now()->addSeconds(60), function () use ($stopId) {
            $data = Http::get("https://open.tan.fr/ewp/tempsattente.json/{$stopId}")
                ->throw()->json();

            return collect($data ?? [])->map(fn($p) => [
                'line'      => $p['ligne']['numLigne'] ?? null,
                'direction' => $p['terminus'] ?? null,
                'minutes'   => $p['temps'] ?? null,    // "Proche", "5 mn", etc.
            ])->all();
        });
    }
}
```

---

## 9. AIService

> L'IA génère une narration courte (3–4 phrases) pour une soirée. Elle est appelée
> **toujours depuis un Job Horizon** (non bloquant), jamais directement dans un controller.
>
> Le provider est configurable via `AI_PROVIDER` (`mistral` | `gemma` | `ollama`).
> L'objectif est d'avoir un modèle **léger** : Mistral small, Gemma 3 4B, ou Gemma 2 2B
> en local sur le VPS via Ollama.

```php
// app/Services/AIService.php
class AIService
{
    public function generateNarrative(array $context): string
    {
        $cacheKey = 'cache:ai:' . md5(json_encode($context));

        return Cache::remember($cacheKey, now()->addHour(), function () use ($context) {
            $prompt = $this->buildPrompt($context);

            return match (config('services.ai.provider')) {
                'mistral' => $this->callMistral($prompt),
                'gemma'   => $this->callGemma($prompt),
                'ollama'  => $this->callOllama($prompt),
                default   => throw new \RuntimeException('AI_PROVIDER inconnu'),
            };
        });
    }

    private function buildPrompt(array $ctx): string
    {
        return <<<PROMPT
        Tu es un guide nantais qui présente brièvement la soirée idéale.
        Génère 3 phrases courtes, vivantes, sans emoji.

        Humeur de la soirée : {$ctx['mood']}
        Météo nantaise : {$ctx['weather']['condition']}, {$ctx['weather']['temp']}°C
        Venue : {$ctx['venue']['name']} ({$ctx['venue']['type']}, {$ctx['venue']['district']})
        Musique : {$ctx['venue']['music']}
        Ce soir : {$ctx['event']['title']}

        Donne envie d'y aller ce soir. Pas plus de 3 phrases.
        PROMPT;
    }

    private function callMistral(string $prompt): string
    {
        $cfg = config('services.ai.mistral');

        $response = Http::withToken($cfg['key'])
            ->post('https://api.mistral.ai/v1/chat/completions', [
                'model'      => $cfg['model'],
                'messages'   => [['role' => 'user', 'content' => $prompt]],
                'max_tokens' => 200,
            ])->throw()->json();

        return trim($response['choices'][0]['message']['content']);
    }

    private function callGemma(string $prompt): string
    {
        $cfg = config('services.ai.gemma');

        $response = Http::post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$cfg['model']}:generateContent?key={$cfg['key']}",
            [
                'contents' => [[
                    'role'  => 'user',
                    'parts' => [['text' => $prompt]],
                ]],
                'generationConfig' => ['maxOutputTokens' => 200],
            ]
        )->throw()->json();

        return trim($response['candidates'][0]['content']['parts'][0]['text']);
    }

    private function callOllama(string $prompt): string
    {
        $cfg = config('services.ai.ollama');

        $response = Http::timeout(120)->post("{$cfg['base_url']}/api/generate", [
            'model'  => $cfg['model'],
            'prompt' => $prompt,
            'stream' => false,
            'options' => ['num_predict' => 200],
        ])->throw()->json();

        return trim($response['response']);
    }
}
```

> **Choix du provider** :
> - `mistral` : défaut, free tier, latence ~1–2 s
> - `gemma` : si tu préfères Gemma sans hébergement (Google AI Studio, free tier 14 RPM sur Gemma 3)
> - `ollama` : self-hosted sur le VPS — 0 coût API, latence locale ~1–4 s sur Gemma 2 2B (besoin ~3 Go RAM)
>
> Le service Ollama tourne dans un container Coolify dédié (cf. `PLAN_DEPLOIEMENT.md §5`).

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
        return new Envelope(subject: "Ta nuit nantaise t'attend — NOCTAMBULE");
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
<body style="font-family: -apple-system, sans-serif; background: #050409; color: #FFF1FA;">
    <h1 style="color: #FF2D92;">NOCTAMBULE</h1>

    <p>{{ $soiree->ai_narrative }}</p>

    <h2 style="color: #A855F7;">Ce soir</h2>
    <p><strong>{{ $soiree->venue->name }}</strong> — {{ $soiree->venue->district }}</p>
    @if($soiree->event)
    <p>{{ $soiree->event->title }}</p>
    @endif

    <h3>Météo</h3>
    <p>{{ $soiree->weather_snapshot['condition'] }} — {{ $soiree->weather_snapshot['temp'] }}°C</p>

    @if(!empty($soiree->tan_snapshot))
    <h3>Transport</h3>
    <p>{{ $soiree->venue->transport_hint }} — prochain passage dans {{ $soiree->tan_snapshot[0]['minutes'] ?? '?' }}</p>
    @endif
</body>
</html>
```

---

## 11. BadgeService

> Service de gamification. À chaque soirée terminée, on évalue les critères et on
> attribue les badges débloqués. Lecture côté `/api/v1/user/badges`.

```php
// app/Services/BadgeService.php
class BadgeService
{
    /**
     * Renvoie la liste de tous les badges, marqués comme unlocked ou non pour ce user.
     */
    public function forUser(User $user): array
    {
        $unlocked = $user->badges()->pluck('badges.id')->all();

        return Badge::all()->map(fn(Badge $b) => [
            'id'          => $b->id,
            'label'       => $b->label,
            'description' => $b->description,
            'icon'        => $b->icon,
            'unlocked'    => in_array($b->id, $unlocked),
            'unlocked_at' => $user->badges->firstWhere('id', $b->id)?->pivot->unlocked_at,
        ])->all();
    }

    /**
     * Évalue tous les badges et attribue ceux qui sont nouvellement débloqués.
     * Appelé après chaque soirée via CheckBadgesJob.
     */
    public function evaluate(User $user): array
    {
        $newlyUnlocked = [];

        foreach (Badge::all() as $badge) {
            if ($user->badges->contains($badge->id)) {
                continue;   // déjà unlocked
            }

            if ($this->meetsCriteria($user, $badge->criteria)) {
                $user->badges()->attach($badge->id, ['unlocked_at' => now()]);
                $newlyUnlocked[] = $badge->id;
            }
        }

        return $newlyUnlocked;
    }

    private function meetsCriteria(User $user, array $criteria): bool
    {
        $soirees = $user->soirees()->with('venue')->get();

        return match ($criteria['type']) {
            // 10 sorties après 1h du matin
            'late_nights' => $soirees->filter(
                fn($s) => $s->created_at->hour >= 1 && $s->created_at->hour < 6
            )->count() >= $criteria['min'],

            // 5 quartiers différents visités
            'districts' => $soirees->pluck('venue.district')->unique()->count() >= $criteria['min'],

            // 3 genres musicaux différents
            'music_genres' => $soirees->pluck('venue.music')->filter()->unique()->count() >= $criteria['min'],

            // 5 visites au même venue
            'same_venue' => $soirees->groupBy('venue_id')->map->count()->max() >= $criteria['min'],

            default => false,
        };
    }
}
```

---

## 12. SoireeService

L'orchestrateur principal. Il choisit un venue selon le mood, agrège la météo et
le TAN, sauvegarde, puis dispatche les jobs (narrative IA + check badges).

```php
// app/Services/SoireeService.php
class SoireeService
{
    public function __construct(
        private readonly WeatherService   $weather,
        private readonly EventService     $events,
        private readonly VenueService     $venues,
        private readonly TransportService $transport,
    ) {}

    public function generate(array $params, ?User $user): array
    {
        $mood     = $params['mood'];
        $district = $params['district'] ?? null;
        $date     = $params['date'] ?? now()->format('Y-m-d');

        // 1) Sélection d'un venue local pour le mood (+ filtre district éventuel)
        $venue = Venue::query()
            ->where('mood', $mood)
            ->when($district, fn($q) => $q->where('district', $district))
            ->with('tonight')
            ->inRandomOrder()
            ->first()
            ?? Venue::where('mood', $mood)->with('tonight')->firstOrFail();

        // 2) Données fraîches en parallèle (toutes cachées si déjà appelées)
        $weather = $this->weather->getCurrent();
        $events  = $this->events->getByMood($mood, $date);

        // 3) TAN — si on a un stopId associé au venue (sinon on skip)
        $tan = []; // peut être enrichi via venue->stop_id si modélisé plus tard

        $venueData = $this->venues->getBySlug($venue->slug);

        $payload = [
            'mood'      => $mood,
            'venue'     => $venueData,
            'event'     => $venue->tonight,
            'weather'   => $weather,
            'tan'       => $tan,
            'events'    => $events,    // suggestions OpenAgenda complémentaires
            'narrative' => null,        // rempli plus tard par GenerateAINarrativeJob
        ];

        // 4) Sauvegarde + jobs si user connecté
        if ($user) {
            $soiree = $user->soirees()->create([
                'id'               => Str::uuid(),
                'venue_id'         => $venue->id,
                'event_id'         => $venue->tonight?->id,
                'mood'             => $mood,
                'weather_snapshot' => $weather,
                'tan_snapshot'     => $tan,
            ]);

            GenerateAINarrativeJob::dispatch($soiree);
            CheckBadgesJob::dispatch($user)->delay(now()->addSeconds(2));

            $payload['soiree_id'] = $soiree->id;
        }

        return $payload;
    }
}
```

---

## 13. Jobs Horizon

### GenerateAINarrativeJob

Appelle l'IA et écrit la narration dans la soirée sauvegardée.

```php
// app/Jobs/GenerateAINarrativeJob.php
class GenerateAINarrativeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue   = 'ai';
    public int    $tries   = 2;
    public int    $timeout = 120;

    public function __construct(public readonly Soiree $soiree) {}

    public function handle(AIService $ai): void
    {
        $this->soiree->loadMissing(['venue', 'event']);

        $narrative = $ai->generateNarrative([
            'mood'    => $this->soiree->mood,
            'weather' => $this->soiree->weather_snapshot,
            'venue'   => [
                'name'     => $this->soiree->venue->name,
                'type'     => $this->soiree->venue->type,
                'district' => $this->soiree->venue->district,
                'music'    => $this->soiree->venue->music,
            ],
            'event'   => [
                'title' => $this->soiree->event?->title ?? '',
            ],
        ]);

        $this->soiree->update(['ai_narrative' => $narrative]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error("GenerateAINarrativeJob failed for soiree {$this->soiree->id}: {$e->getMessage()}");
    }
}
```

### SendSoireeEmailJob

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

### CheckBadgesJob

```php
// app/Jobs/CheckBadgesJob.php
class CheckBadgesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'default';
    public int    $tries = 1;

    public function __construct(public readonly User $user) {}

    public function handle(BadgeService $badges): void
    {
        $newlyUnlocked = $badges->evaluate($this->user);

        if (!empty($newlyUnlocked)) {
            Log::info("User {$this->user->id} unlocked badges: " . implode(', ', $newlyUnlocked));
        }
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
        $weather->getCurrent();
    }
}
```

---

## 14. Scheduler

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::job(new PrefetchWeatherJob)->everyTenMinutes();
```

Le container `scheduler` (cf. `PLAN_BACKEND.md §10` pour le dev local et
`PLAN_DEPLOIEMENT.md §5` pour la prod sur Coolify) tourne `php artisan schedule:work`.

---

## 15. Binding dans AppServiceProvider

```php
// app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->singleton(WeatherService::class);
    $this->app->singleton(EventService::class);
    $this->app->singleton(VenueService::class);
    $this->app->singleton(PlaceService::class);
    $this->app->singleton(TransportService::class);
    $this->app->singleton(AIService::class);
    $this->app->singleton(MailService::class);
    $this->app->singleton(BadgeService::class);
    $this->app->singleton(SoireeService::class);
}
```

> `singleton` = une seule instance par requête HTTP → économise la mémoire.

---

## 16. Checklist

### Phase 1 — Services BDD locale (sans API externes)
- [ ] `VenueService::list()` + filtres
- [ ] `VenueService::getBySlug()` + tonight + reviews + crowd + tracking trending
- [ ] `VenueService::estimateCrowd()` + cache 5 min
- [ ] `VenueService::getTopSpots()` (Redis sorted set)
- [ ] `BadgeService::forUser()` + `evaluate()` + `meetsCriteria()`

### Phase 2 — Services APIs externes
- [ ] `WeatherService::getCurrent()` + cache 10 min
- [ ] `EventService::getByMood()` + cache 30 min
- [ ] `PlaceService::getNearby()` + cache 1h (Foursquare)
- [ ] `TransportService::getJourney()` + cache 5 min
- [ ] `TransportService::getNextPassages()` + cache 60 s

### Phase 3 — IA multi-provider
- [ ] `AIService::callMistral()` (provider défaut)
- [ ] `AIService::callGemma()` (Google AI Studio)
- [ ] `AIService::callOllama()` (self-hosted, optionnel)
- [ ] Switch via `config('services.ai.provider')`
- [ ] Cache 1h par hash des inputs

### Phase 4 — Mail & Mailable
- [ ] `MailService::sendSoiree()`
- [ ] `SoireeMail` Mailable + template Blade `emails/soiree.blade.php`

### Phase 5 — Jobs Horizon
- [ ] `GenerateAINarrativeJob` (queue `ai`, timeout 120s)
- [ ] `SendSoireeEmailJob` (queue `notifications`)
- [ ] `CheckBadgesJob` (queue `default`)
- [ ] `PrefetchWeatherJob` (scheduled)
- [ ] Tester depuis Tinker : `GenerateAINarrativeJob::dispatch($soiree)`
- [ ] Vérifier les jobs dans le dashboard `/horizon`

### Phase 6 — SoireeService & intégration
- [ ] `SoireeService::generate()` orchestrant tous les services
- [ ] Binding des services dans `AppServiceProvider`
- [ ] Brancher `SoireeService` dans `SoireeController`
- [ ] Brancher `VenueService` dans `VenueController`
- [ ] Brancher `BadgeService` dans `BadgeController`
- [ ] Scheduler `PrefetchWeatherJob` toutes les 10 min
- [ ] Tests end-to-end : générer une soirée complète → narrative IA arrive après ~2 s → badge éventuellement débloqué

---

*Dernière mise à jour : 2026-04-29*
