# NOCTAMBULE — Plan Backend Laravel (Fondations)

> Ce document est destiné au développeur en charge des **bases du backend**.
> Stack : **PHP 8.3 + Laravel 12**, **PostgreSQL**, **Redis**.
>
> Les services métier, jobs Horizon et logique Redis avancée sont gérés par un autre
> développeur (voir `PLAN_SERVICES.md`). Ton rôle : fournir une base solide sur laquelle
> il pourra brancher ses services.
>
> Le déploiement (Coolify + VPS) est documenté à part dans `PLAN_DEPLOIEMENT.md`.

---

## Table des matières

1. [Vue d'ensemble](#1-vue-densemble)
2. [Packages à installer](#2-packages-à-installer)
3. [Structure du projet](#3-structure-du-projet)
4. [Configuration & environnement](#4-configuration--environnement)
5. [Base de données — Migrations & Modèles](#5-base-de-données)
6. [Authentification — Sanctum + Socialite](#6-authentification)
7. [Routes API & Controllers](#7-routes-api--controllers)
8. [Sécurité & validation](#8-sécurité--validation)
9. [Horizon — Installation & configuration](#9-horizon)
10. [Docker (dev local)](#10-docker)
11. [Documentation OpenAPI (L5-Swagger)](#11-documentation-openapi)
12. [Checklist](#12-checklist)

---

## 1. Vue d'ensemble

Le backend expose une **API REST JSON** consommée par le frontend Vue 3.
Il délègue la logique métier à une couche de services (gérée séparément).

```
Frontend Vue 3
     │
     │ HTTP/JSON  Bearer token (Sanctum)
     ▼
┌────────────────────────────────────────┐
│         Laravel 12 — API              │
│                                        │
│  Routes → Controllers → Services      │  ← Services injectés (autre dev)
│                                        │
│  Sanctum (auth classique)             │
│  Socialite (OAuth Google / GitHub)    │
└──────┬──────────────────┬─────────────┘
       │                  │
  PostgreSQL           Redis
  (Eloquent)      (cache + queues Horizon)
```

**Ce que tu fais :**
- Setup du projet Laravel 12 et Docker dev local
- Migrations et modèles Eloquent (8 tables)
- Seeders (venues + badges + events)
- Auth (Sanctum + Socialite)
- Routes et controllers (squelettes qui appellent les services)
- Sécurité, validation, CORS
- Installation et configuration de Horizon (les jobs eux-mêmes sont écrits par l'autre dev)
- Base L5-Swagger

---

## 2. Packages à installer

```bash
# Auth
composer require laravel/sanctum
composer require laravel/socialite

# Redis & Queues
composer require predis/predis
composer require laravel/horizon

# Documentation API
composer require darkaonline/l5-swagger

# Mail
composer require symfony/mailjet-mailer
```

### Publication des assets

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan vendor:publish --provider="Laravel\Horizon\HorizonServiceProvider"
php artisan vendor:publish --provider="L5Swagger\L5SwaggerServiceProvider"
php artisan migrate
php artisan horizon:install
```

---

## 3. Structure du projet

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   ├── AuthController.php          # register, login, logout, me
│   │   │   │   └── SocialiteController.php     # redirect, callback OAuth
│   │   │   ├── SoireeController.php            # generate, show, share, review
│   │   │   ├── WeatherController.php
│   │   │   ├── EventController.php
│   │   │   ├── VenueController.php             # list, show, crowd
│   │   │   ├── PlaceController.php             # Foursquare (lieux complémentaires)
│   │   │   ├── TransportController.php         # journey + stop
│   │   │   ├── BadgeController.php             # liste badges user
│   │   │   ├── UserController.php              # favoris, historique soirees
│   │   │   └── StatsController.php             # trending
│   │   ├── Middleware/
│   │   │   └── SecurityHeaders.php
│   │   └── Requests/
│   │       ├── Auth/
│   │       │   ├── RegisterRequest.php
│   │       │   └── LoginRequest.php
│   │       ├── GenerateSoireeRequest.php
│   │       ├── ShareSoireeRequest.php
│   │       └── StoreFavoriteRequest.php
│   ├── Jobs/                                   # implémentés par l'autre dev
│   ├── Models/
│   │   ├── User.php
│   │   ├── Venue.php
│   │   ├── Event.php
│   │   ├── Soiree.php
│   │   ├── Favorite.php
│   │   ├── Review.php
│   │   ├── Badge.php
│   │   └── UserBadge.php
│   ├── Services/                               # implémentés par l'autre dev
│   └── Providers/
│       └── AppServiceProvider.php             # rate limiters + bindings services
├── database/
│   ├── migrations/
│   └── seeders/
│       ├── VenueSeeder.php                     # 6 venues de la maquette
│       ├── BadgeSeeder.php                     # 4 badges
│       └── EventSeeder.php                     # 1 event tonight par venue
├── routes/
│   └── api.php
├── config/
│   ├── horizon.php
│   ├── services.php
│   └── cors.php
└── storage/api-docs/
```

---

## 4. Configuration & environnement

### `.env`

```dotenv
# App
APP_NAME=NOCTAMBULE
APP_ENV=local
APP_URL=http://localhost:8000

# Base de données
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=noctambule
DB_USERNAME=noctambule
DB_PASSWORD=secret

# Redis
REDIS_HOST=redis
REDIS_PORT=6379
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost:5173
FRONTEND_URL=http://localhost:5173

# Socialite — Google
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost:8000/api/v1/auth/google/callback

# Socialite — GitHub
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_REDIRECT_URI=http://localhost:8000/api/v1/auth/github/callback

# APIs externes
OPENWEATHER_API_KEY=
OPENAGENDA_API_KEY=
FOURSQUARE_API_KEY=
NAVITIA_API_KEY=

# IA — provider configurable (mistral | gemma | ollama)
AI_PROVIDER=mistral
MISTRAL_API_KEY=
MISTRAL_MODEL=mistral-small-latest

# Si AI_PROVIDER=gemma (Google AI Studio)
GOOGLE_AI_API_KEY=
GEMMA_MODEL=gemma-3-4b-it

# Si AI_PROVIDER=ollama (self-hosted, en option sur le VPS)
OLLAMA_BASE_URL=http://ollama:11434
OLLAMA_MODEL=gemma2:2b

# Mail (Mailjet)
MAIL_MAILER=mailjet
MAILJET_APIKEY=
MAILJET_APISECRET=
MAIL_FROM_ADDRESS=noreply@noctambule.fr
MAIL_FROM_NAME=NOCTAMBULE

# Horizon
HORIZON_DOMAIN=
```

### `config/services.php`

```php
return [
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),
    ],
    'github' => [
        'client_id'     => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect'      => env('GITHUB_REDIRECT_URI'),
    ],
    'openweather' => ['key' => env('OPENWEATHER_API_KEY')],
    'openagenda'  => ['key' => env('OPENAGENDA_API_KEY')],
    'foursquare'  => ['key' => env('FOURSQUARE_API_KEY')],
    'navitia'     => ['key' => env('NAVITIA_API_KEY')],

    // IA — couche d'abstraction, le service AIService lit `ai.provider`
    'ai' => [
        'provider' => env('AI_PROVIDER', 'mistral'),
        'mistral' => [
            'key'   => env('MISTRAL_API_KEY'),
            'model' => env('MISTRAL_MODEL', 'mistral-small-latest'),
        ],
        'gemma' => [
            'key'   => env('GOOGLE_AI_API_KEY'),
            'model' => env('GEMMA_MODEL', 'gemma-3-4b-it'),
        ],
        'ollama' => [
            'base_url' => env('OLLAMA_BASE_URL', 'http://ollama:11434'),
            'model'    => env('OLLAMA_MODEL', 'gemma2:2b'),
        ],
    ],
];
```

---

## 5. Base de données

### Migrations

```php
// create_users_table
Schema::create('users', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('email')->unique();
    $table->string('username');
    $table->string('password')->nullable();      // nullable pour les users OAuth
    $table->string('google_id')->nullable()->unique();
    $table->string('github_id')->nullable()->unique();
    $table->string('avatar')->nullable();
    $table->timestamps();
});

// create_venues_table
Schema::create('venues', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('slug')->unique();             // 'macadam', 'altercafe'…
    $table->string('name');
    $table->enum('type', ['club', 'bar', 'salle', 'pub']);
    $table->string('district');                   // 'Île de Nantes', 'Bouffay', 'Centre'
    $table->enum('mood', ['festif', 'chill', 'decouverte', 'afterwork']);
    $table->string('music')->nullable();          // 'Techno', 'Indie / Pop'
    $table->string('price')->nullable();          // '€', '€€', '€€€'
    $table->string('cover')->nullable();          // '12€', 'Gratuit'
    $table->string('time_open')->nullable();      // '23:00 — 06:00'
    $table->decimal('lat', 10, 7);
    $table->decimal('lng', 10, 7);
    $table->string('transport_hint')->nullable();// 'Tram 1', 'Busway 5', 'À pied'
    $table->string('photo_url')->nullable();
    $table->jsonb('tags')->default('[]');
    $table->timestamps();
});

// create_events_table
Schema::create('events', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('venue_id')->constrained()->cascadeOnDelete();
    $table->string('title');                      // 'DJ ROMA — Set techno mélodique'
    $table->dateTime('starts_at');
    $table->dateTime('ends_at')->nullable();
    $table->string('source')->default('local');   // 'local' | 'openagenda'
    $table->string('external_id')->nullable();    // si OpenAgenda
    $table->text('description')->nullable();
    $table->string('image_url')->nullable();
    $table->timestamps();

    $table->index(['venue_id', 'starts_at']);
});

// create_soirees_table
Schema::create('soirees', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('venue_id')->constrained();
    $table->foreignUuid('event_id')->nullable()->constrained();
    $table->enum('mood', ['festif', 'chill', 'decouverte', 'afterwork']);
    $table->jsonb('weather_snapshot')->nullable();
    $table->jsonb('tan_snapshot')->nullable();
    $table->text('ai_narrative')->nullable();
    $table->jsonb('shared_with')->default('[]');
    $table->timestamps();
});

// create_favorites_table  (polymorphe — venues ou events)
Schema::create('favorites', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
    $table->uuidMorphs('favoritable');            // favoritable_id + favoritable_type
    $table->timestamps();
    $table->unique(['user_id', 'favoritable_id', 'favoritable_type']);
});

// create_reviews_table
Schema::create('reviews', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('venue_id')->constrained()->cascadeOnDelete();
    $table->unsignedTinyInteger('rating');        // 1–5
    $table->text('comment')->nullable();
    $table->timestamps();
    $table->unique(['user_id', 'venue_id']);
});

// create_badges_table  (définitions seedées)
Schema::create('badges', function (Blueprint $table) {
    $table->string('id')->primary();              // 'noctambule', 'explorateur', 'melomane', 'fidele'
    $table->string('label');
    $table->text('description');
    $table->string('icon');                       // '◉', '◇', '♪', '✦'
    $table->jsonb('criteria');                    // règles d'unlock pour BadgeService
    $table->timestamps();
});

// create_user_badges_table
Schema::create('user_badges', function (Blueprint $table) {
    $table->id();
    $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
    $table->string('badge_id');
    $table->foreign('badge_id')->references('id')->on('badges')->cascadeOnDelete();
    $table->timestamp('unlocked_at')->useCurrent();
    $table->unique(['user_id', 'badge_id']);
});
```

### Modèles Eloquent

```php
// User.php
class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'email', 'username', 'password',
        'google_id', 'github_id', 'avatar',
    ];

    protected $hidden = ['password'];

    public function soirees(): HasMany   { return $this->hasMany(Soiree::class); }
    public function favorites(): HasMany { return $this->hasMany(Favorite::class); }
    public function reviews(): HasMany   { return $this->hasMany(Review::class); }
    public function badges(): BelongsToMany {
        return $this->belongsToMany(Badge::class, 'user_badges')->withTimestamps();
    }
}

// Venue.php
class Venue extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'tags' => 'array',
        'lat'  => 'float',
        'lng'  => 'float',
    ];

    public function events(): HasMany   { return $this->hasMany(Event::class); }
    public function reviews(): HasMany  { return $this->hasMany(Review::class); }
    public function tonight(): HasOne   {
        return $this->hasOne(Event::class)->whereDate('starts_at', today())->latestOfMany();
    }
    public function favorites(): MorphMany { return $this->morphMany(Favorite::class, 'favoritable'); }
}

// Event.php
class Event extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    public function venue(): BelongsTo     { return $this->belongsTo(Venue::class); }
    public function favorites(): MorphMany { return $this->morphMany(Favorite::class, 'favoritable'); }
}

// Soiree.php
class Soiree extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'weather_snapshot' => 'array',
        'tan_snapshot'     => 'array',
        'shared_with'      => 'array',
    ];

    public function user(): BelongsTo  { return $this->belongsTo(User::class); }
    public function venue(): BelongsTo { return $this->belongsTo(Venue::class); }
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
}

// Favorite.php
class Favorite extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'user_id', 'favoritable_id', 'favoritable_type'];

    public function user(): BelongsTo            { return $this->belongsTo(User::class); }
    public function favoritable(): MorphTo       { return $this->morphTo(); }
}

// Review.php
class Review extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'user_id', 'venue_id', 'rating', 'comment'];

    public function user(): BelongsTo  { return $this->belongsTo(User::class); }
    public function venue(): BelongsTo { return $this->belongsTo(Venue::class); }
}

// Badge.php
class Badge extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = ['criteria' => 'array'];

    public function users(): BelongsToMany {
        return $this->belongsToMany(User::class, 'user_badges')->withTimestamps();
    }
}
```

### Seeders

```php
// database/seeders/VenueSeeder.php — exemple Macadam
DB::table('venues')->insert([
    [
        'id'              => Str::uuid(),
        'slug'            => 'macadam',
        'name'            => 'Macadam',
        'type'            => 'club',
        'district'        => 'Île de Nantes',
        'mood'            => 'festif',
        'music'           => 'Techno',
        'price'           => '€€',
        'cover'           => '12€',
        'time_open'       => '23:00 — 06:00',
        'lat'             => 47.205,
        'lng'             => -1.563,
        'transport_hint'  => 'Tram 1',
        'photo_url'       => 'https://images.unsplash.com/photo-1545128485-c400e7702796?w=800&q=80',
        'tags'            => json_encode(['Techno', 'Dancefloor', 'Late night']),
        'created_at'      => now(),
        'updated_at'      => now(),
    ],
    // … L'Alter'Café, Le Lieu Unique, Le Cluricaune, Warehouse, Le Ferrailleur
]);

// database/seeders/BadgeSeeder.php
DB::table('badges')->insert([
    ['id' => 'noctambule',  'label' => 'Noctambule',  'description' => '10 sorties après 1h',  'icon' => '◉', 'criteria' => json_encode(['type' => 'late_nights', 'min' => 10])],
    ['id' => 'explorateur', 'label' => 'Explorateur', 'description' => '5 quartiers visités',  'icon' => '◇', 'criteria' => json_encode(['type' => 'districts',   'min' => 5])],
    ['id' => 'melomane',    'label' => 'Mélomane',    'description' => '3 genres différents',  'icon' => '♪', 'criteria' => json_encode(['type' => 'music_genres','min' => 3])],
    ['id' => 'fidele',      'label' => 'Fidèle',      'description' => '5 visites au même lieu','icon' => '✦', 'criteria' => json_encode(['type' => 'same_venue', 'min' => 5])],
]);

// database/seeders/EventSeeder.php — 1 event "tonight" par venue
foreach (Venue::all() as $venue) {
    Event::create([
        'id'         => Str::uuid(),
        'venue_id'   => $venue->id,
        'title'      => match ($venue->slug) {
            'macadam'      => 'DJ ROMA — Set techno mélodique',
            'altercafe'    => 'Open mic & cocktails maison',
            'lieuunique'   => 'Soirée Électro Curiosa',
            'lecluricaune' => 'Quiz musical irlandais',
            'warehouse'    => 'Disco Inferno feat. Léonie',
            'ferrailleur'  => 'Concert : The Dust Coda + Jaune',
        },
        'starts_at'  => now()->setTime(21, 0),
        'ends_at'    => now()->addDay()->setTime(2, 0),
        'source'     => 'local',
    ]);
}
```

---

## 6. Authentification

### 6.1 Sanctum — Auth classique

```php
// AuthController.php
class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'id'       => Str::uuid(),
            'email'    => $request->email,
            'username' => $request->username,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('web')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['error' => 'Identifiants invalides'], 401);
        }

        $user  = Auth::user();
        $token = $user->createToken('web')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}
```

### 6.2 Socialite — OAuth Google & GitHub (stateless)

> **Pourquoi stateless ?** Le backend est une API pure, sans session cookie.
> Socialite doit fonctionner sans état, et retourner un token Sanctum au lieu d'une session.

Flux :
```
1. Frontend → GET /api/v1/auth/{provider}/redirect
2. Laravel retourne l'URL OAuth du provider
3. Utilisateur autorise dans un popup
4. Provider → GET /api/v1/auth/{provider}/callback
5. Laravel crée/retrouve le user → retourne token Sanctum
6. Frontend stocke le token (localStorage / Pinia)
```

```php
// SocialiteController.php
class SocialiteController extends Controller
{
    public function redirect(string $provider): JsonResponse
    {
        $this->validateProvider($provider);

        $url = Socialite::driver($provider)->stateless()->redirect()->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    public function callback(string $provider): JsonResponse
    {
        $this->validateProvider($provider);

        $socialUser = Socialite::driver($provider)->stateless()->user();

        $user = User::updateOrCreate(
            ["{$provider}_id" => $socialUser->getId()],
            [
                'id'       => Str::uuid(),
                'email'    => $socialUser->getEmail(),
                'username' => $socialUser->getName() ?? $socialUser->getNickname(),
                'avatar'   => $socialUser->getAvatar(),
                'password' => null,
            ]
        );

        $token = $user->createToken($provider)->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user]);
    }

    private function validateProvider(string $provider): void
    {
        abort_unless(in_array($provider, ['google', 'github']), 404, 'Provider inconnu');
    }
}
```

---

## 7. Routes API & Controllers

### `routes/api.php`

```php
Route::prefix('v1')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login',    [AuthController::class, 'login']);
        Route::get('{provider}/redirect',  [SocialiteController::class, 'redirect']);
        Route::get('{provider}/callback',  [SocialiteController::class, 'callback']);
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me',      [AuthController::class, 'me']);
        });
    });

    // Données temps réel (services gérés par l'autre dev)
    Route::middleware('throttle:api')->group(function () {
        Route::get('weather',                  [WeatherController::class, 'index']);
        Route::get('events',                   [EventController::class, 'index']);
        Route::get('places',                   [PlaceController::class, 'index']);     // Foursquare
        Route::get('transport',                [TransportController::class, 'journey']);
        Route::get('transport/stop/{stopId}',  [TransportController::class, 'stop']);
    });

    // Venues (lecture seule publique)
    Route::get('venues',              [VenueController::class, 'index']);
    Route::get('venues/{slug}',       [VenueController::class, 'show']);
    Route::get('venues/{slug}/crowd', [VenueController::class, 'crowd']);

    // Soirées
    Route::prefix('soiree')->group(function () {
        Route::post('generate', [SoireeController::class, 'generate']);
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('{id}',         [SoireeController::class, 'show']);
            Route::post('{id}/share',  [SoireeController::class, 'share'])
                 ->middleware('throttle:mail');
            Route::post('{id}/review', [SoireeController::class, 'review']);
        });
    });

    // Utilisateur
    Route::middleware('auth:sanctum')->prefix('user')->group(function () {
        Route::get('soirees',              [UserController::class, 'soirees']);
        Route::get('favorites',            [UserController::class, 'favorites']);
        Route::post('favorites',           [UserController::class, 'storeFavorite']);
        Route::delete('favorites/{id}',    [UserController::class, 'destroyFavorite']);
        Route::get('badges',               [BadgeController::class, 'index']);
    });

    // Stats publiques
    Route::get('stats/trending', [StatsController::class, 'trending']);
});
```

### Squelette type d'un controller

Les controllers sont **fins** : ils valident, appellent le service injecté, retournent la réponse.

```php
// SoireeController.php
class SoireeController extends Controller
{
    public function __construct(private readonly SoireeService $soireeService) {}

    public function generate(GenerateSoireeRequest $request): JsonResponse
    {
        $soiree = $this->soireeService->generate(
            $request->validated(),
            $request->user()   // null si non connecté
        );

        return response()->json($soiree);
    }

    public function show(string $id): JsonResponse
    {
        $soiree = Soiree::with(['venue', 'event'])->findOrFail($id);

        return response()->json($soiree);
    }

    public function share(ShareSoireeRequest $request, string $id): JsonResponse
    {
        $soiree = Soiree::findOrFail($id);

        SendSoireeEmailJob::dispatch($soiree, $request->validated('recipients'));

        return response()->json(['message' => 'Email envoyé']);
    }

    public function review(Request $request, string $id): JsonResponse
    {
        $soiree = Soiree::findOrFail($id);

        $review = Review::updateOrCreate(
            ['user_id' => $request->user()->id, 'venue_id' => $soiree->venue_id],
            $request->validate([
                'rating'  => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:500',
            ])
        );

        return response()->json($review, 201);
    }
}
```

```php
// VenueController.php
class VenueController extends Controller
{
    public function __construct(private readonly VenueService $venues) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->venues->list($request->only('mood', 'district', 'type'))
        );
    }

    public function show(string $slug): JsonResponse
    {
        return response()->json($this->venues->getBySlug($slug));
    }

    public function crowd(string $slug): JsonResponse
    {
        return response()->json($this->venues->getCrowd($slug));
    }
}
```

```php
// BadgeController.php
class BadgeController extends Controller
{
    public function __construct(private readonly BadgeService $badges) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->badges->forUser($request->user()));
    }
}
```

---

## 8. Sécurité & validation

### Form Requests

```php
// RegisterRequest.php
class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email'    => ['required', 'email', 'unique:users,email'],
            'username' => ['required', 'string', 'min:2', 'max:50'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}

// GenerateSoireeRequest.php
class GenerateSoireeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'mood'     => ['required', 'in:festif,chill,decouverte,afterwork'],
            'district' => ['sometimes', 'string', 'max:100'],
            'date'     => ['sometimes', 'date_format:Y-m-d'],
        ];
    }
}

// ShareSoireeRequest.php
class ShareSoireeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'recipients'   => ['required', 'array', 'min:1', 'max:5'],
            'recipients.*' => ['email'],
        ];
    }
}

// StoreFavoriteRequest.php
class StoreFavoriteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type'        => ['required', 'in:venue,event'],
            'id'          => ['required', 'uuid'],
        ];
    }
}
```

### Middleware SecurityHeaders

```php
// app/Http/Middleware/SecurityHeaders.php
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
```

Enregistrer dans `bootstrap/app.php` :
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(SecurityHeaders::class);
})
```

### CORS — `config/cors.php`

```php
return [
    'paths'               => ['api/*'],
    'allowed_origins'     => [env('FRONTEND_URL', 'http://localhost:5173')],
    'allowed_methods'     => ['GET', 'POST', 'DELETE', 'OPTIONS'],
    'allowed_headers'     => ['Content-Type', 'Authorization', 'Accept'],
    'supports_credentials' => true,
];
```

### Rate limiters — `AppServiceProvider::boot()`

```php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->ip());
});

RateLimiter::for('mail', function (Request $request) {
    return Limit::perDay(5)->by($request->user()?->id ?? $request->ip());
});
```

---

## 9. Horizon

Tu installes et configures Horizon. Les jobs eux-mêmes sont écrits par l'autre développeur,
mais la config des supervisors est ici.

### Installation

```bash
composer require laravel/horizon
php artisan horizon:install
php artisan vendor:publish --provider="Laravel\Horizon\HorizonServiceProvider"
```

### `config/horizon.php`

```php
'environments' => [
    'local' => [
        'supervisor-1' => [
            'connection'   => 'redis',
            'queue'        => ['default', 'notifications', 'ai'],
            'balance'      => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 3,
            'tries'        => 3,
            'timeout'      => 60,
        ],
    ],
    'production' => [
        'supervisor-1' => [
            'queue'           => ['default', 'notifications'],
            'balance'         => 'auto',
            'minProcesses'    => 1,
            'maxProcesses'    => 10,
            'balanceCooldown' => 3,
            'tries'           => 3,
            'timeout'         => 60,
        ],
        'supervisor-ai' => [
            'queue'        => ['ai'],
            'balance'      => 'simple',
            'maxProcesses' => 2,
            'tries'        => 2,
            'timeout'      => 120,   // l'IA peut prendre jusqu'à 2 min
        ],
    ],
],
```

### Commandes Horizon

```bash
php artisan horizon           # démarrer les workers
php artisan horizon:status    # vérifier l'état
php artisan horizon:pause     # mettre en pause
php artisan horizon:continue  # reprendre
```

Dashboard accessible sur `http://localhost:8000/horizon`.

---

## 10. Docker (dev local)

> Le Dockerfile production et la config Coolify sont dans `PLAN_DEPLOIEMENT.md`.
> Ici on documente uniquement le `docker-compose.yml` pour le développement local.

### `docker-compose.yml`

```yaml
services:
  app:
    build:
      context: .
      dockerfile: docker/Dockerfile.dev
    volumes:
      - ./:/var/www/html
    depends_on:
      - postgres
      - redis

  nginx:
    image: nginx:alpine
    ports:
      - "8000:80"
    volumes:
      - ./:/var/www/html
      - ./docker/nginx.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app

  horizon:
    build:
      context: .
      dockerfile: docker/Dockerfile.dev
    command: php artisan horizon
    volumes:
      - ./:/var/www/html
    depends_on:
      - redis
      - postgres

  scheduler:
    build:
      context: .
      dockerfile: docker/Dockerfile.dev
    command: php artisan schedule:work
    volumes:
      - ./:/var/www/html
    depends_on:
      - redis
      - postgres

  postgres:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: noctambule
      POSTGRES_USER: noctambule
      POSTGRES_PASSWORD: secret
    volumes:
      - pgdata:/var/lib/postgresql/data
    ports:
      - "5432:5432"

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"

volumes:
  pgdata:
```

### `docker/Dockerfile.dev`

```dockerfile
FROM php:8.3-fpm-alpine

RUN apk add --no-cache postgresql-dev git unzip \
    && docker-php-ext-install pdo_pgsql pcntl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
```

> Pas de `composer install` dans le Dockerfile dev — le code est monté en volume,
> et tu lances `composer install` depuis le container avec `docker compose exec app composer install`.

---

## 11. Documentation OpenAPI (L5-Swagger)

### Setup

```bash
composer require darkaonline/l5-swagger
php artisan vendor:publish --provider="L5Swagger\L5SwaggerServiceProvider"
```

### Base dans `config/l5-swagger.php`

```php
'defaults' => [
    'routes' => [
        'api' => '/api/documentation',
    ],
    'info' => [
        'title'       => 'NOCTAMBULE API',
        'description' => 'API de la plateforme événementielle nocturne nantaise',
        'version'     => '1.0.0',
    ],
],
```

### Annotation OpenAPI de base (à mettre dans `app/Http/Controllers/Controller.php`)

```php
/**
 * @OA\Info(
 *     title="NOCTAMBULE API",
 *     version="1.0.0",
 *     description="API de la plateforme événementielle nocturne nantaise"
 * )
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer"
 * )
 */
abstract class Controller {}
```

### Annotation type sur AuthController

```php
/**
 * @OA\Post(
 *     path="/api/v1/auth/login",
 *     summary="Connexion utilisateur",
 *     tags={"Auth"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"email","password"},
 *             @OA\Property(property="email",    type="string", format="email"),
 *             @OA\Property(property="password", type="string", format="password")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Token Sanctum retourné"),
 *     @OA\Response(response=401, description="Identifiants invalides"),
 *     @OA\Response(response=422, description="Validation échouée")
 * )
 */
public function login(LoginRequest $request): JsonResponse { ... }
```

```bash
php artisan l5-swagger:generate   # régénérer la spec
```

---

## 12. Checklist

### Phase 1 — Setup & BDD
- [ ] Créer le projet Laravel 12 (`composer create-project laravel/laravel backend`)
- [ ] Configurer `.env` (PostgreSQL, Redis, drivers)
- [ ] Publier et configurer Sanctum
- [ ] Écrire les 8 migrations (users, venues, events, soirees, favorites, reviews, badges, user_badges)
- [ ] Écrire les 8 modèles Eloquent avec relations
- [ ] Écrire les 3 seeders (VenueSeeder, BadgeSeeder, EventSeeder)
- [ ] Vérifier `php artisan migrate --seed`

### Phase 2 — Auth
- [ ] `AuthController` (register, login, logout, me) + Form Requests
- [ ] `SocialiteController` (redirect + callback stateless)
- [ ] Configurer Google et GitHub dans `config/services.php` + `.env`
- [ ] Tester register/login avec Postman ou HTTPie

### Phase 3 — Routes & Controllers squelettes
- [ ] Écrire `routes/api.php` complet
- [ ] Créer tous les controllers avec méthodes vides (retournent `[]` pour l'instant)
- [ ] Form Requests (GenerateSoireeRequest, ShareSoireeRequest, StoreFavoriteRequest)
- [ ] Middleware `SecurityHeaders` + enregistrement
- [ ] CORS configuré
- [ ] Rate limiters dans `AppServiceProvider`

### Phase 4 — Horizon & Docker dev
- [ ] Installer et configurer Horizon (`config/horizon.php`)
- [ ] Vérifier dashboard `/horizon` accessible
- [ ] `docker-compose.yml` fonctionnel (app + nginx + horizon + scheduler + postgres + redis)
- [ ] Tester `docker compose up` depuis zéro

### Phase 5 — Documentation
- [ ] L5-Swagger installé et accessible sur `/api/documentation`
- [ ] Annotation de base dans `Controller.php`
- [ ] Annotations sur les endpoints Auth
- [ ] `.env.example` à jour (toutes les clés, sans valeurs)

---

*Dernière mise à jour : 2026-04-29*
