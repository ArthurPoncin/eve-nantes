# NOCTAMBULE — Plan de projet

> **La nuit nantaise, recommandée pour toi.**
>
> Application web qui aide les Nantais à choisir leur sortie du soir
> selon leur humeur, en agrégeant venues locaux, météo, transports TAN
> et une narration IA.

---

## Table des matières

1. [Vue d'ensemble](#1-vue-densemble)
2. [Stack technique](#2-stack-technique)
3. [Architecture globale](#3-architecture-globale)
4. [Intégrations API externes](#4-intégrations-api-externes)
5. [Modèle de données](#5-modèle-de-données)
6. [Design des endpoints](#6-design-des-endpoints)
7. [Stratégie Redis](#7-stratégie-redis)
8. [Sécurité](#8-sécurité)
9. [Documentation à produire](#9-documentation-à-produire)
10. [Plan de développement](#10-plan-de-développement)
11. [Séparation des responsabilités](#11-séparation-des-responsabilités)
12. [Déploiement](#12-déploiement)

---

## 1. Vue d'ensemble

### Concept

L'utilisateur arrive sur NOCTAMBULE, choisit :
- Son **humeur** parmi 4 (festif, chill, découverte, afterwork)
- Optionnel : son **quartier** ou sa **localisation**

L'application lui propose alors une sélection de **venues nocturnes nantais** :
- Clubs, bars, salles de concert, pubs
- Filtrés par humeur (chaque venue a un `mood` principal)
- Avec en temps réel : **affluence** estimée, **météo**, **prochain tram TAN**
- Une **narration IA courte** ("Ce soir au Macadam, …")
- Possibilité de **sauvegarder** la soirée et de la **partager par email**
- Un système de **badges** (gamification)

### Valeur ajoutée

- 100% Nantes, 100% nuit — pas de bruit générique
- Données locales fiables : les venues sont en BDD et seedés (pas de dépendance Foursquare en prod)
- Données temps réel : météo, transports, affluence indicative
- Personnalisation par mood + IA légère (Mistral / Gemma)
- Gamification : badges débloqués au fil des sorties (Noctambule, Explorateur, Mélomane, Fidèle)

### Identité visuelle (issue de la maquette)

- Fond : `#050409` (noir nuit)
- Accents : `#FF2D92` (festif), `#5EEAD4` (chill), `#A855F7` (découverte), `#F5C56B` (afterwork)
- Typographies : Space Grotesk (UI), Instrument Serif italique (titres), JetBrains Mono (mono)
- Deux modes : **Night** (par défaut) et **Sunset** (fin de journée)

### Écrans principaux (5)

1. **Landing** — hero, mood picker rapide, venues featured
2. **Explorer** — carte interactive (Leaflet) + sidebar venues
3. **Mood picker** — sélection détaillée (sliders)
4. **Détail venue** — affluence, météo, TAN, avis, événement du soir
5. **Dashboard** — historique, sauvegardés, badges

---

## 2. Stack technique

### Frontend

| Technologie | Justification |
|---|---|
| **Vue 3 + Vite** | Écosystème naturel avec Laravel, Composition API |
| **TypeScript** | Typage fort |
| **TailwindCSS** | Utility-first, palette custom NOCTAMBULE |
| **Leaflet.js** | Carte interactive OSM, gratuit |
| **Axios** | Client HTTP, intercepteurs Sanctum |
| **Pinia** | State management Vue 3 |
| **Vue Query (TanStack)** | Cache côté client, états loading/error |

### Backend

| Technologie | Justification |
|---|---|
| **PHP 8.3 + Laravel 12** | Framework full-featured, Redis/PostgreSQL natifs |
| **Eloquent ORM** | Migrations, relations, query builder |
| **Laravel Sanctum** | Auth API par tokens, simple pour SPA |
| **Laravel Cache (Predis)** | `Cache::remember()` = cache-aside Redis |
| **Laravel HTTP Client** | Appels APIs externes (météo, TAN, IA, Mailjet) |
| **Laravel Form Requests** | Validation découplée des controllers |
| **Laravel Mail** | Envoi d'emails, driver Mailjet, templates Blade |
| **Laravel Horizon** | Supervisor des queues Redis (jobs IA + email) |
| **L5-Swagger** | Documentation OpenAPI 3.0 depuis annotations PHP |

### Bases de données

| BDD | Rôle |
|---|---|
| **PostgreSQL** | Persistant : users, venues, events, soirées, favoris, reviews, badges, user_badges |
| **Redis** | Sessions, cache APIs externes, rate limiting, trending, file de jobs Horizon |

### IA — provider configurable

| Provider | Quand l'utiliser |
|---|---|
| **Mistral AI** (`mistral-small-latest`) | Défaut, free tier généreux, simple |
| **Google AI Studio** (`gemma-3-*`) | Si on veut Gemma sans hébergement |
| **Ollama self-hosted** (`gemma2:2b` ou `phi3:mini`) | Bonus VPS — 0 coût API, latence locale |

Switch par variable d'environnement `AI_PROVIDER`. Cf. `PLAN_SERVICES.md §9`.

### Infrastructure

| Outil | Rôle |
|---|---|
| **Docker + Docker Compose** | Orchestration locale et build production |
| **Coolify** | PaaS self-hosted sur VPS pour le déploiement |
| **Laravel `.env`** | Gestion des variables d'environnement |

Cf. `PLAN_DEPLOIEMENT.md` pour tout ce qui touche au déploiement.

---

## 3. Architecture globale

```
┌─────────────────────────────────────────────────────────┐
│                        CLIENT                           │
│               Vue 3 + Vite (port 5173)                  │
└───────────────────────┬─────────────────────────────────┘
                        │ HTTP/JSON (Sanctum token header)
┌───────────────────────▼─────────────────────────────────┐
│                    API GATEWAY                          │
│              PHP 8.3 + Laravel 12 (port 8000)           │
│                                                         │
│  ┌─────────────┐  ┌──────────────┐  ┌───────────────┐  │
│  │  Sanctum    │  │  Routes      │  │  Middleware   │  │
│  │  Middleware │  │  /api/v1/... │  │  CORS/Throttle│  │
│  └─────────────┘  └──────────────┘  └───────────────┘  │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │              Services Layer                     │   │
│  │  WeatherService | EventService | VenueService   │   │
│  │  PlaceService   | TransportService | AIService  │   │
│  │  MailService    | BadgeService | SoireeService  │   │
│  └──────────────┬──────────────────────────────────┘   │
└──────────────────┼──────────────────────────────────────┘
                   │
        ┌──────────┴──────────┐
        │                     │
┌───────▼──────┐     ┌────────▼───────┐
│  PostgreSQL  │     │     Redis      │
│  (Eloquent)  │     │   (Predis)     │
│              │     │                │
│ users        │     │ session:{id}   │
│ venues       │     │ cache:weather  │
│ events       │     │ cache:events   │
│ soirees      │     │ cache:tan      │
│ favorites    │     │ cache:ai       │
│ reviews      │     │ trending:spots │
│ badges       │     │ jobs (Horizon) │
│ user_badges  │     │                │
└──────────────┘     └────────────────┘
        │
        │ Appels HTTP sortants
        ▼
┌─────────────────────────────────────────────────┐
│              APIs EXTERNES                      │
│                                                 │
│  OpenWeatherMap  │  OpenAgenda  │  Foursquare   │
│  TAN/Navitia     │  Mistral AI / Gemma / Ollama │
│  Mailjet                                        │
└─────────────────────────────────────────────────┘
```

### Structure des dossiers

```
noctambule/
├── frontend/                  # Vue 3 + Vite
│   ├── src/
│   │   ├── components/        # Composants réutilisables (MoodPicker, VenueCard, MapView…)
│   │   ├── pages/             # Landing, Explorer, Mood, Detail, Dashboard
│   │   ├── services/          # Appels API backend (Axios)
│   │   ├── stores/            # Pinia (auth, soiree, mood, user)
│   │   ├── types/             # Types TS (Venue, Event, Mood, Badge…)
│   │   └── utils/
│   └── vite.config.ts
│
├── backend/                   # Laravel 12
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/   # Fins, délèguent aux services
│   │   │   ├── Middleware/
│   │   │   └── Requests/      # Form Requests
│   │   ├── Services/          # Logique métier + APIs externes
│   │   ├── Jobs/              # GenerateAINarrative, SendSoireeEmail, CheckBadges
│   │   └── Models/            # Eloquent
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/           # VenueSeeder, BadgeSeeder, EventSeeder
│   ├── routes/api.php
│   ├── config/services.php
│   └── storage/api-docs/
│
├── docker/
│   ├── nginx.conf
│   └── php.ini
├── docker-compose.yml         # local dev
├── Dockerfile                 # production (multi-stage)
├── PLAN.md                    # ce document
├── PLAN_BACKEND.md
├── PLAN_SERVICES.md
└── PLAN_DEPLOIEMENT.md
```

---

## 4. Intégrations API externes

### 4.1 OpenWeatherMap

- **But** : météo actuelle à Nantes (affichée sur Détail + landing)
- **Endpoint** : `GET /data/2.5/weather?q=Nantes,FR&appid={key}&units=metric&lang=fr`
- **Champs utilisés** : temp, feels_like, condition, icon, humidity, wind
- **Cache Redis** : `cache:weather:nantes` — TTL **10 min**
- **Auth** : API key (query param)

### 4.2 OpenAgenda

- **But** : événements ponctuels (concerts, soirées thématiques) liés aux venues
- **Endpoint** : `GET /v2/events?key={key}&size=10&sort=timings.begin&location[city]=Nantes`
- **Champs utilisés** : titre, date, lieu, description courte, lien, image
- **Cache Redis** : `cache:events:{date}:{mood}` — TTL **30 min**
- **Auth** : API key publique
- **Note** : les `events` du soir associés à un venue (champ `tonight` dans la maquette) peuvent venir d'OpenAgenda OU être saisis manuellement en BDD si OpenAgenda ne renvoie rien pour le venue.

### 4.3 Foursquare Places API

- **But** : enrichir les suggestions hors catalogue local (par ex. proposer un café d'after à proximité d'un venue, ou découvrir de nouveaux lieux pas encore seedés en BDD)
- **Endpoint** : `GET /v3/places/search?ll={lat,lng}&radius=500&categories={id}&limit=5`
- **Champs utilisés** : nom, adresse, note, catégorie, photo, lat/lng
- **Cache Redis** : `cache:places:{cat}:{zone}` — TTL **1 h**
- **Auth** : Bearer token (header `Authorization`)
- **Note** : la base venues principale reste locale (les 6 lieux seedés). Foursquare est appelé en complément, pas en source primaire.

### 4.4 TAN / Navitia (Open Data Nantes Métropole)

- **But** : horaires temps réel des trams/bus pour rejoindre un venue
- **Endpoint Navitia** : `GET /v1/coverage/fr-nw/journeys?from={coords}&to={coords}&datetime={dt}`
- **Endpoint TAN (alternative directe)** : `https://open.tan.fr/ewp/tempsattente.json/{stopId}`
- **Champs utilisés** : prochain passage (minutes), ligne, direction, statut trafic
- **Cache Redis** : `cache:tan:{stopId}` — TTL **60 s** (temps réel)
- **Auth Navitia** : API key (gratuit)

### 4.5 Mistral AI (ou Gemma — provider configurable)

- **But** : générer une courte narration de soirée ("Ce soir au Macadam, l'air frais nantais accompagne le set techno mélodique de DJ ROMA…")
- **Endpoint Mistral** : `POST https://api.mistral.ai/v1/chat/completions`
- **Modèle Mistral** : `mistral-small-latest` (free tier)
- **Endpoint Gemma (Google AI Studio)** : `POST https://generativelanguage.googleapis.com/v1beta/models/gemma-3-4b-it:generateContent`
- **Endpoint Ollama (self-hosted)** : `POST http://ollama:11434/api/generate` (modèle `gemma2:2b` ou `phi3:mini`)
- **Cache Redis** : `cache:ai:{md5(inputs)}` — TTL **1 h**
- **Auth** : Bearer token (Mistral / AI Studio) — aucun (Ollama)

Détails de la couche d'abstraction : voir `PLAN_SERVICES.md §9`.

### 4.6 Mailjet

- **But** : envoyer la soirée par email à des amis
- **Endpoint** : `POST /v3.1/send`
- **Auth** : Basic Auth (API Key + Secret Key)
- **Rate limit** : 5 emails/jour/user (côté Redis)

---

## 5. Modèle de données

### 5.1 PostgreSQL — schéma cible

```php
// users
Schema::create('users', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('email')->unique();
    $table->string('username');
    $table->string('password');
    $table->timestamps();
});

// venues — seedés (6 venues nantais de la maquette)
Schema::create('venues', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('slug')->unique();              // 'macadam', 'altercafe'…
    $table->string('name');
    $table->enum('type', ['club', 'bar', 'salle', 'pub']);
    $table->string('district');                    // 'Île de Nantes', 'Bouffay'…
    $table->enum('mood', ['festif', 'chill', 'decouverte', 'afterwork']);
    $table->string('music')->nullable();           // 'Techno', 'Indie / Pop'…
    $table->string('price')->nullable();           // '€', '€€', '€€€'
    $table->string('cover')->nullable();           // '12€', 'Gratuit'
    $table->string('time_open')->nullable();       // '23:00 — 06:00'
    $table->decimal('lat', 10, 7);
    $table->decimal('lng', 10, 7);
    $table->string('transport_hint')->nullable(); // 'Tram 1', 'Busway 5'
    $table->string('photo_url')->nullable();
    $table->jsonb('tags')->default('[]');
    $table->timestamps();
});

// events — soirée du jour rattachée à un venue (champ `tonight` de la maquette)
Schema::create('events', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('venue_id')->constrained()->cascadeOnDelete();
    $table->string('title');                       // 'DJ ROMA — Set techno mélodique'
    $table->dateTime('starts_at');
    $table->dateTime('ends_at')->nullable();
    $table->string('source')->default('local');    // 'local' | 'openagenda'
    $table->string('external_id')->nullable();     // si OpenAgenda
    $table->text('description')->nullable();
    $table->string('image_url')->nullable();
    $table->timestamps();
});

// soirees — composition sauvegardée par un user
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

// favorites — venues / events sauvegardés
Schema::create('favorites', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
    $table->morphs('favoritable');                 // venue | event
    $table->timestamps();
    $table->unique(['user_id', 'favoritable_id', 'favoritable_type']);
});

// reviews
Schema::create('reviews', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('venue_id')->constrained()->cascadeOnDelete();
    $table->unsignedTinyInteger('rating');         // 1–5
    $table->text('comment')->nullable();
    $table->timestamps();
    $table->unique(['user_id', 'venue_id']);
});

// badges — définitions (seedés)
Schema::create('badges', function (Blueprint $table) {
    $table->string('id')->primary();               // 'noctambule', 'explorateur'…
    $table->string('label');                       // 'Noctambule'
    $table->text('description');                   // '10 sorties après 1h'
    $table->string('icon');                        // '◉', '◇'…
    $table->jsonb('criteria');                     // règles d'unlock
    $table->timestamps();
});

// user_badges — débloqués par utilisateur
Schema::create('user_badges', function (Blueprint $table) {
    $table->id();
    $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
    $table->string('badge_id');
    $table->foreign('badge_id')->references('id')->on('badges')->cascadeOnDelete();
    $table->timestamp('unlocked_at')->useCurrent();
    $table->unique(['user_id', 'badge_id']);
});
```

### 5.2 Seeds obligatoires

- **VenueSeeder** : les 6 venues de la maquette (Macadam, L'Alter'Café, Le Lieu Unique, Le Cluricaune, Warehouse, Le Ferrailleur)
- **BadgeSeeder** : 4 badges (Noctambule, Explorateur, Mélomane, Fidèle)
- **EventSeeder** : 1 event "tonight" par venue (pour démarrer)

### 5.3 Redis — structure des clés

| Clé | Type | TTL | Contenu |
|---|---|---|---|
| `session:{uuid}` | Hash | 24h | userId, email, iat |
| `cache:weather:nantes` | String (JSON) | 10 min | OpenWeatherMap |
| `cache:events:{date}:{mood}` | String (JSON) | 30 min | OpenAgenda |
| `cache:places:{cat}:{zone}` | String (JSON) | 1 h | Foursquare lieux complémentaires |
| `cache:tan:{stopId}` | String (JSON) | 60 s | Prochain passage |
| `cache:ai:{inputHash}` | String | 1h | Narration IA |
| `cache:venue:crowd:{venueId}` | String | 5 min | Affluence estimée |
| `trending:spots` | Sorted Set | – | Score = nb d'affichages |
| `ratelimit:mail:{userId}` | Counter | 24h | Nb d'emails envoyés |
| `ratelimit:api:{ip}` | Counter | 1 min | Nb requêtes |

### 5.4 Affluence (`crowd`)

L'affluence est une **estimation** (pas un vrai capteur) :
- Calcul simple basé sur l'heure + jour de la semaine + popularité du venue (rating + tags)
- Stocké en cache Redis 5 min
- Renvoyé sous forme `{ percent: 87, label: 'Bondé' }`
- À rendre déterministe pour la démo (table de mapping heure → palier d'affluence)

---

## 6. Design des endpoints

### Convention

- Préfixe : `/api/v1`
- Format : JSON
- Auth : `Authorization: Bearer <token-sanctum>` sur les routes protégées
- Erreurs : `{ "error": "message", "code": "ERROR_CODE" }`

### Authentification

```
POST   /api/v1/auth/register    — Créer un compte
POST   /api/v1/auth/login       — Connexion, retourne token Sanctum
POST   /api/v1/auth/logout      — Révoque le token courant [auth]
GET    /api/v1/auth/me          — Infos utilisateur courant [auth]
```

### Soirée (cœur du service)

```
POST   /api/v1/soiree/generate          — Génère une suggestion (mood, district?) [auth optionnel]
GET    /api/v1/soiree/:id               — Récupère une soirée sauvegardée [auth]
POST   /api/v1/soiree/:id/share         — Envoie par email [auth, throttle:mail]
POST   /api/v1/soiree/:id/review        — Note la soirée [auth]
```

### Données temps réel

```
GET    /api/v1/weather                  — Météo Nantes
GET    /api/v1/events?mood=&date=       — Événements OpenAgenda + locaux
GET    /api/v1/transport?from=&to=      — Itinéraire Navitia
GET    /api/v1/transport/stop/:stopId   — Prochains passages TAN
GET    /api/v1/places?near=&category=   — Lieux Foursquare à proximité (afterclub, café…)
```

### Venues (CRUD lecture seule côté API publique)

```
GET    /api/v1/venues                   — Liste (?mood=&district=&type=)
GET    /api/v1/venues/:slug             — Détail + crowd + event tonight + reviews
GET    /api/v1/venues/:slug/crowd       — Affluence estimée seule
```

### Utilisateur

```
GET    /api/v1/user/soirees             — Historique [auth]
GET    /api/v1/user/favorites           — Favoris [auth]
POST   /api/v1/user/favorites           — Ajouter [auth]
DELETE /api/v1/user/favorites/:id       — Retirer [auth]
GET    /api/v1/user/badges              — Badges débloqués + verrouillés [auth]
```

### Stats publiques

```
GET    /api/v1/stats/trending           — Top venues (Redis Sorted Set)
```

---

## 7. Stratégie Redis

### Patterns utilisés

**1. Cache-aside (lazy loading)**
Le service vérifie Redis. Si miss → appel API/calcul → store avec TTL → retour.

```
request → check Redis →  HIT  → return cached data
                     ↓
                   MISS → call source → store in Redis (TTL) → return
```

**2. Sessions**
Sanctum stocke ses tokens en BDD ; on garde Redis pour le cache des infos profil chaudes
(`session:{userId}` avec TTL 24h) afin d'éviter une requête PostgreSQL sur chaque request.

**3. Rate limiting**
`INCR` + `EXPIRE` sur `ratelimit:api:{ip}` (60/min) et `ratelimit:mail:{userId}` (5/jour).

**4. Trending Sorted Set**
À chaque affichage d'un venue :
```
ZINCRBY trending:spots 1 "{venueId}:{name}"
ZREVRANGE trending:spots 0 9 WITHSCORES  → top 10
```

**5. Affluence**
Calculée à la volée via une fonction (heure × jour × popularité), stockée 5 min pour
ne pas recalculer 100 fois par minute.

**6. Cache IA**
Hash MD5 des inputs (`mood + venue + weather + event`) → réutilisation si même contexte
en moins d'une heure (économise le call API).

---

## 8. Sécurité

| Risque | Contre-mesure |
|---|---|
| Injection SQL | Eloquent ORM + Form Requests (jamais de requêtes brutes) |
| XSS | Validation Laravel, échappement Blade auto |
| CORS | Middleware `HandleCors` (`config/cors.php`) |
| Headers HTTP | Middleware `SecurityHeaders` (CSP, HSTS, X-Frame-Options) |
| Brute force auth | `ThrottleRequests` Laravel sur `/auth/login` (5/min/IP via Redis) |
| API keys exposées | `.env` jamais committé (`.gitignore`) — Coolify les injecte |
| Tokens Sanctum | Expiration configurable, révocation possible |
| Spam email | Rate limit Mailjet : 5 emails/jour/user via Redis |
| Over-fetching APIs | Cache Redis + `throttle:api` middleware global |
| Prompt injection IA | Inputs strictement typés (mood, venue ID, weather) — pas de texte libre user → LLM |

---

## 9. Documentation à produire

### 9.1 Documents techniques

| Document | Format | Contenu |
|---|---|---|
| **PLAN.md** | Markdown | Architecture, choix techniques, modèle (ce document) |
| **PLAN_BACKEND.md** | Markdown | Setup Laravel, migrations, auth, routes, controllers |
| **PLAN_SERVICES.md** | Markdown | Services métier, APIs externes, jobs Horizon |
| **PLAN_DEPLOIEMENT.md** | Markdown | Coolify + VPS, Dockerfile production, env, CI/CD |
| **OpenAPI Spec** | `swagger.yaml` | Tous les endpoints documentés |
| **Schéma BDD** | Migrations + ERD | Tables, relations, types |
| **README** | Markdown | Installation et lancement |
| **.env.example** | dotenv | Variables attendues (sans valeurs) |

### 9.2 Documentation OpenAPI

Chaque endpoint documente : summary, paramètres (path/query/body), schémas, réponses 200/400/401/404/422/429/500, sécurité (Sanctum).

### 9.3 Documentation dans le code

- **PHPDoc** sur chaque Service / Controller (`@param`, `@return`, `@throws`)
- **Types PHP 8.3** explicites (pas de `mixed` sauvage)
- **Commentaires** uniquement sur logique non évidente (TTL choix, hash strategy…)
- **Types TypeScript** côté Vue (pas de `any`)

---

## 10. Plan de développement

### Phase 1 — Fondations (infra + auth)
- [ ] Docker Compose local (Laravel, PostgreSQL, Redis)
- [ ] Initialisation Laravel 12 + `.env`
- [ ] Configuration Eloquent + driver PostgreSQL
- [ ] Migrations (users, venues, events, soirees, favorites, reviews, badges, user_badges)
- [ ] Seeders (VenueSeeder, BadgeSeeder, EventSeeder)
- [ ] Configuration Redis (`CACHE_DRIVER=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`)
- [ ] Routes auth Sanctum (register / login / logout / me)
- [ ] Setup L5-Swagger
- [ ] Initialisation Vue 3 + Vite + Tailwind avec palette NOCTAMBULE

### Phase 2 — Services
- [ ] WeatherService (cache 10 min)
- [ ] EventService (OpenAgenda — cache 30 min)
- [ ] VenueService (lecture BDD + lookup crowd + cache trending)
- [ ] TransportService (Navitia + TAN — cache 60 s)
- [ ] AIService (provider Mistral / Gemma / Ollama — cache 1 h)
- [ ] MailService (Mailjet + rate limit Redis)
- [ ] BadgeService (vérification critères, attribution)

### Phase 3 — Logique métier (SoireeService)
- [ ] Orchestration : `mood + (district?) → venue + event tonight + weather + tan + narrative IA`
- [ ] Sauvegarde en PostgreSQL
- [ ] Endpoint `POST /soiree/generate`
- [ ] Endpoints historique, favoris, reviews
- [ ] Endpoint `GET /user/badges` + vérification post-soirée

### Phase 4 — Frontend
- [ ] Landing (mobile + desktop, mode Night + Sunset)
- [ ] Mood picker (sliders interactifs)
- [ ] Explorer (carte Leaflet + sidebar venues)
- [ ] Détail venue (affluence, météo, TAN, avis, narrative IA)
- [ ] Dashboard (historique, sauvegardés, badges)
- [ ] Formulaire partage email

### Phase 5 — Déploiement & polish
- [ ] Compléter spec OpenAPI
- [ ] Rédiger README
- [ ] Diagramme ERD
- [ ] Tests manuels bout-en-bout
- [ ] Déploiement Coolify (cf. `PLAN_DEPLOIEMENT.md`)
- [ ] Vérification sécurité (env, CORS, headers, rate limits)

---

## 11. Séparation des responsabilités

| Membre | Périmètre suggéré |
|---|---|
| **Dev 1** | Phase 1 (infra, auth, BDD, seeds) + Phase 3 (SoireeService, endpoints métier) |
| **Dev 2** | Phase 2 (services + APIs externes + jobs Horizon) + OpenAPI |
| **Dev 3** | Phase 4 (frontend complet) + Phase 5 déploiement Coolify |

> Cette répartition est indicative. Les phases 1 et 2 peuvent avancer en parallèle
> dès que les interfaces TypeScript partagées sont définies.
> Le déploiement Coolify peut être préparé tôt (un VPS de test peut tourner dès la fin de la Phase 1).

---

## 12. Déploiement

Le déploiement complet est documenté dans **`PLAN_DEPLOIEMENT.md`**.

Résumé :
- **Cible** : VPS (Hetzner / OVH / Scaleway) avec **Coolify** comme PaaS self-hosted
- **Build** : Dockerfile multi-stage (PHP-FPM + Nginx + Vue static build)
- **Services Coolify** : app web, worker `queue:work`, scheduler `schedule:work`, PostgreSQL managé, Redis managé, (optionnel) Ollama
- **CI/CD** : webhook GitHub → Coolify build automatique sur push `main`
- **HTTPS** : Let's Encrypt automatique via le reverse proxy de Coolify (Caddy ou Traefik)

---

*Dernière mise à jour : 2026-04-29*
