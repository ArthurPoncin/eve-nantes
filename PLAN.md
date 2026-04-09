# NantesVibes — Plan de projet

> Application web permettant aux Nantais de générer une soirée ou journée parfaite,
> en agrégeant météo, événements, lieux, transports et ambiance musicale.

---

## Table des matières

1. [Vue d'ensemble](#1-vue-densemble)
2. [Stack technique](#2-stack-technique)
3. [Architecture globale](#3-architecture-globale)
4. [Intégrations API externes](#4-intégrations-api-externes)
5. [Modèle de données](#5-modèle-de-données)
6. [Design des endpoints (OpenAPI)](#6-design-des-endpoints)
7. [Stratégie Redis](#7-stratégie-redis)
8. [Sécurité](#8-sécurité)
9. [Documentation à produire](#9-documentation-à-produire)
10. [Plan de développement](#10-plan-de-développement)
11. [Séparation des responsabilités](#11-séparation-des-responsabilités)

---

## 1. Vue d'ensemble

### Concept

L'utilisateur arrive sur NantesVibes, renseigne :
- Son **humeur** (chill, festif, romantique, culturel…)
- Son **budget** (petit / moyen / libre)
- Sa **disponibilité** (ce soir, ce week-end, journée)
- Sa **localisation** dans Nantes (optionnel)

L'application compose alors une **proposition de soirée/journée complète** :
- Météo du moment + conseil vestimentaire
- 1–2 événements culturels ou concerts
- Un bar ou restaurant adapté à l'humeur
- Un itinéraire transport (tramway, bus, vélo)
- Une playlist Spotify thématique
- Une courte description narrative générée par IA
- Option : partager la soirée par email à des amis

### Valeur ajoutée

- Tout en un seul endroit, pensé pour Nantes
- Données en temps réel (météo, transports, dispo lieux)
- Personnalisation par l'IA
- Fonctionnalités sociales légères (partage, favoris, historique)

---

## 2. Stack technique

### Frontend

| Technologie | Justification |
|---|---|
| **Vue 3 + Vite** | Écosystème naturel avec Laravel, syntaxe Composition API claire |
| **TypeScript** | Typage fort, meilleure maintenabilité |
| **TailwindCSS** | Utility-first, idéal pour itérer vite sur le design |
| **Leaflet.js** | Carte interactive OSM, open source, gratuit |
| **Axios** | Client HTTP avec intercepteurs (gestion token Sanctum) |
| **Pinia** | State management officiel Vue 3, remplace Vuex |
| **Vue Query (TanStack)** | Gestion du cache côté client, états loading/error |

### Backend

| Technologie | Justification |
|---|---|
| **PHP 8.3 + Laravel 11** | Framework full-featured, Redis/PostgreSQL intégrés nativement |
| **Eloquent ORM** | Migrations, relations, query builder intégré à Laravel |
| **Laravel Sanctum** | Auth API par tokens, simple et adapté aux SPA |
| **Laravel Cache (Predis)** | Facade `Cache::remember()` = cache-aside Redis en 1 ligne |
| **Laravel HTTP Client** | Wrapper Guzzle intégré pour les appels APIs externes |
| **Laravel Form Requests** | Validation des entrées découplée des controllers |
| **Laravel Mail** | Envoi d'emails avec driver Mailjet, templates Blade |
| **L5-Swagger** | Documentation OpenAPI 3.0 générée depuis annotations PHP |

### Bases de données

| BDD | Rôle |
|---|---|
| **PostgreSQL** | Données persistantes : users, soirées sauvegardées, favoris, reviews |
| **Redis** | Sessions, cache des APIs externes, rate limiting, trending |

### Infrastructure

| Outil | Rôle |
|---|---|
| **Docker + Docker Compose** | Orchestration locale : Laravel (PHP-FPM + Nginx), PostgreSQL, Redis |
| **Laravel `.env`** | Gestion des variables d'environnement |

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
│              PHP 8.3 + Laravel 11 (port 8000)           │
│                                                         │
│  ┌─────────────┐  ┌──────────────┐  ┌───────────────┐  │
│  │  Sanctum    │  │  Routes      │  │  Middleware   │  │
│  │  Middleware │  │  /api/v1/... │  │  CORS/Throttle│  │
│  └─────────────┘  └──────────────┘  └───────────────┘  │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │              Services Layer                     │   │
│  │  WeatherService | EventService | PlaceService   │   │
│  │  SpotifyService | TransportService | AIService  │   │
│  │  MailService    | SoireeService                 │   │
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
│ soirees      │     │ cache:weather  │
│ favorites    │     │ cache:events   │
│ reviews      │     │ trending:spots │
└──────────────┘     └────────────────┘
        │
        │ Appels HTTP sortants
        ▼
┌─────────────────────────────────────────────────┐
│              APIs EXTERNES                      │
│                                                 │
│  OpenWeatherMap  │  OpenAgenda  │  Foursquare   │
│  Spotify         │  TAN/OData   │  Mistral AI   │
│  Mailjet                                        │
└─────────────────────────────────────────────────┘
```

### Structure des dossiers

```
nantesvibes/
├── frontend/                  # Vue 3 + Vite
│   ├── src/
│   │   ├── components/        # Composants réutilisables
│   │   ├── pages/             # Pages (Home, Soiree, Profil…)
│   │   ├── services/          # Appels à l'API backend (Axios)
│   │   ├── stores/            # Stores Pinia (auth, soiree, user)
│   │   ├── types/             # Types TypeScript
│   │   └── utils/
│   └── vite.config.ts
│
├── backend/                   # Laravel 11
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/   # Gestion req/res, délègue aux services
│   │   │   ├── Middleware/    # Sanctum, CORS, ThrottleRequests
│   │   │   └── Requests/      # Form Requests (validation)
│   │   ├── Services/          # Logique métier + appels APIs externes
│   │   └── Models/            # Modèles Eloquent
│   ├── database/
│   │   └── migrations/        # Migrations Laravel
│   ├── routes/
│   │   └── api.php            # Routes /api/v1/...
│   ├── config/
│   │   └── services.php       # Clés APIs externes
│   └── storage/api-docs/      # swagger.yaml généré par L5-Swagger
│
├── docker-compose.yml
└── PLAN.md
```

---

## 4. Intégrations API externes

### 4.1 OpenWeatherMap

- **But** : Météo actuelle et prévisions à Nantes
- **Endpoint utilisé** : `GET /data/2.5/weather?q=Nantes&appid={key}&units=metric&lang=fr`
- **Ce qu'on utilise** : température, description météo, icône, vent
- **Fréquence de mise à jour** : toutes les 10 min
- **Cache Redis** : `cache:weather:nantes` — TTL **10 minutes**
- **Authentification** : API Key en query param

### 4.2 OpenAgenda

- **But** : Événements culturels, concerts, expositions à Nantes
- **Endpoint utilisé** : `GET /v2/events?key={key}&oaq[uid]={agenda_nantes}&size=10&sort=timings.begin`
- **Ce qu'on utilise** : titre, date, lieu, description, lien, image
- **Fréquence de mise à jour** : toutes les 30 min
- **Cache Redis** : `cache:events:{date}:{humeur}` — TTL **30 minutes**
- **Authentification** : API Key publique

### 4.3 Foursquare Places API

- **But** : Bars, restaurants, lieux adaptés à l'humeur
- **Endpoint utilisé** : `GET /v3/places/search?near=Nantes&categories={id}&limit=5`
- **Ce qu'on utilise** : nom, adresse, note, catégorie, horaires, photo
- **Fréquence de mise à jour** : toutes les heures
- **Cache Redis** : `cache:places:{categorie}:{zone}` — TTL **1 heure**
- **Authentification** : Bearer token (header `Authorization`)

### 4.4 Spotify Web API

- **But** : Recommandations musicales selon l'humeur
- **Endpoint utilisé** :
  - `GET /v1/search?q={mood}&type=playlist&market=FR`
  - `GET /v1/recommendations?seed_genres={genre}&limit=10`
- **Ce qu'on utilise** : playlist name, description, cover, lien externe
- **Authentification** : OAuth2 Client Credentials Flow (pas besoin de compte user)
- **Cache Redis** : `cache:spotify:{humeur}` — TTL **2 heures**
- **Note** : Token OAuth2 stocké dans Redis avec TTL calqué sur `expires_in`

### 4.5 TAN / Open Data Nantes Métropole

- **But** : Lignes de tram/bus pour rejoindre les lieux suggérés
- **Source** : `data.nantesmetropole.fr` — GTFS statique + API Navitia
- **Endpoint utilisé** : `GET /v1/coverage/fr-nw/journeys?from={coords}&to={coords}`
- **Ce qu'on utilise** : itinéraire, temps de trajet, correspondances, horaires
- **Cache Redis** : `cache:transport:{from}:{to}:{datetime}` — TTL **5 minutes**
- **Authentification** : API Key Navitia (gratuit)

### 4.6 Mistral AI

- **But** : Générer une description narrative et personnalisée de la soirée
- **Endpoint utilisé** : `POST /v1/chat/completions`
- **Modèle** : `mistral-small-latest` (free tier)
- **Prompt** : Synthèse des données collectées (météo, event, lieu, musique) → texte engageant
- **Cache Redis** : `cache:ai:{hash_des_inputs}` — TTL **1 heure** (éviter double appel à contexte identique)
- **Authentification** : Bearer token

### 4.7 Mailjet

- **But** : Envoyer la soirée générée par email à des amis
- **Endpoint utilisé** : `POST /v3.1/send`
- **Ce qu'on envoie** : email HTML avec le programme de la soirée
- **Pas de cache** : action ponctuelle déclenchée par l'utilisateur
- **Authentification** : Basic Auth (API Key + Secret Key)
- **Rate limit** : 200 emails/jour en free tier — on limite côté Redis (`ratelimit:mail:{userId}`)

---

## 5. Modèle de données

### 5.1 PostgreSQL (Migrations Laravel + Modèles Eloquent)

```php
// users (table par défaut Laravel, étendue)
Schema::create('users', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('email')->unique();
    $table->string('username');
    $table->string('password');        // bcrypt
    $table->timestamps();
});

// soirees
Schema::create('soirees', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
    $table->enum('humeur', ['chill', 'festif', 'romantique', 'culturel']);
    $table->enum('budget', ['petit', 'moyen', 'libre']);
    $table->jsonb('weather_data');     // snapshot météo
    $table->jsonb('event_data');       // snapshot événement
    $table->jsonb('place_data');       // snapshot lieu
    $table->jsonb('transport_data');
    $table->text('playlist_url')->nullable();
    $table->text('ai_narrative')->nullable();
    $table->jsonb('shared_with')->default('[]'); // emails destinataires
    $table->timestamps();
});

// favorites
Schema::create('favorites', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
    $table->enum('type', ['event', 'place', 'playlist']);
    $table->string('external_id');     // ID dans l'API source
    $table->enum('source', ['openagenda', 'foursquare', 'spotify']);
    $table->string('label');
    $table->timestamps();
});

// reviews
Schema::create('reviews', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('soiree_id')->constrained()->cascadeOnDelete();
    $table->unsignedTinyInteger('rating'); // 1–5
    $table->text('comment')->nullable();
    $table->timestamps();
});
```

### 5.2 Redis — Structure des clés

| Clé | Type | TTL | Contenu |
|---|---|---|---|
| `session:{uuid}` | Hash | 24h | userId, email, iat |
| `cache:weather:nantes` | String (JSON) | 10 min | Réponse OpenWeatherMap |
| `cache:events:{date}:{humeur}` | String (JSON) | 30 min | Liste events OpenAgenda |
| `cache:places:{cat}:{zone}` | String (JSON) | 1h | Liste lieux Foursquare |
| `cache:spotify:{humeur}` | String (JSON) | 2h | Playlist/tracks Spotify |
| `cache:transport:{from}:{to}:{dt}` | String (JSON) | 5 min | Itinéraire Navitia |
| `cache:ai:{inputHash}` | String | 1h | Narrative Mistral AI |
| `token:spotify` | String | `expires_in`s | Access token OAuth2 |
| `trending:spots` | Sorted Set | - | Score = nb de fois affiché |
| `ratelimit:mail:{userId}` | Counter | 24h | Nb d'emails envoyés |
| `ratelimit:api:{ip}` | Counter | 1 min | Nb requêtes par IP |

---

## 6. Design des endpoints

### Convention

- Préfixe : `/api/v1`
- Format : JSON
- Auth : `Authorization: Bearer <jwt>` sur les routes protégées
- Erreurs : `{ "error": "message", "code": "ERROR_CODE" }`

### Authentification

```
POST   /api/v1/auth/register    — Créer un compte
POST   /api/v1/auth/login       — Connexion, retourne JWT
POST   /api/v1/auth/logout      — Invalide la session Redis
GET    /api/v1/auth/me          — Infos utilisateur courant [auth]
```

### Génération de soirée (cœur du service)

```
POST   /api/v1/soiree/generate  — Lance la génération (humeur, budget, dispo) [auth optionnel]
GET    /api/v1/soiree/:id       — Récupère une soirée sauvegardée [auth]
POST   /api/v1/soiree/:id/share — Envoie la soirée par email [auth]
POST   /api/v1/soiree/:id/review — Note une soirée [auth]
```

### Données en temps réel (proxies vers APIs externes)

```
GET    /api/v1/weather          — Météo Nantes (cachée Redis)
GET    /api/v1/events           — Événements ?humeur=&date=
GET    /api/v1/places           — Lieux ?categorie=&zone=
GET    /api/v1/transport        — Itinéraire ?from=&to=&datetime=
GET    /api/v1/music            — Playlist/tracks ?humeur=
```

### Utilisateur

```
GET    /api/v1/user/soirees     — Historique des soirées [auth]
GET    /api/v1/user/favorites   — Favoris [auth]
POST   /api/v1/user/favorites   — Ajouter un favori [auth]
DELETE /api/v1/user/favorites/:id — Retirer un favori [auth]
```

### Stats

```
GET    /api/v1/stats/trending   — Top spots du moment (Redis Sorted Set)
```

---

## 7. Stratégie Redis

### Patterns utilisés

**1. Cache-aside (lazy loading)**
Le service vérifie d'abord Redis. Si miss → appel API externe → store en Redis avec TTL.

```
request → check Redis →  HIT  → return cached data
                     ↓
                   MISS → call external API → store in Redis (TTL) → return data
```

**2. Sessions**
À la connexion, on stocke les infos de session dans un Hash Redis avec TTL 24h.
Le JWT contient uniquement le `sessionId`. Le middleware auth vérifie la session Redis.

**3. Rate limiting**
Compteur `INCR` + `EXPIRE` sur `ratelimit:api:{ip}` et `ratelimit:mail:{userId}`.

**4. Trending Sorted Set**
À chaque affichage d'un spot Foursquare, on incrémente son score :
```
ZINCRBY trending:spots 1 "{placeId}:{nom}"
ZREVRANGE trending:spots 0 9 WITHSCORES  → top 10
```

**5. Token OAuth2 Spotify**
Token stocké avec TTL = `expires_in` (3600s). Rafraîchi automatiquement en cas de miss.

---

## 8. Sécurité

| Risque | Contre-mesure |
|---|---|
| Injection SQL | Eloquent ORM + Form Requests (jamais de requêtes SQL brutes) |
| XSS | Validation Laravel, échappement automatique des templates Blade |
| CORS | Middleware `HandleCors` configuré dans `config/cors.php` |
| Headers HTTP | Middleware `SecurityHeaders` (CSP, HSTS, X-Frame-Options) |
| Brute force auth | `ThrottleRequests` Laravel sur `/auth/login` (5 tentatives/min/IP via Redis) |
| API keys exposées | Fichier `.env` uniquement, jamais committé (`.gitignore`) |
| Tokens Sanctum | Expiration configurable, révocation immédiate possible |
| Spam email | Rate limit Mailjet par user via Redis (max 5/jour) |
| Over-fetching APIs | Cache Redis + `throttle:api` middleware Laravel global |

---

## 9. Documentation à produire

### 9.1 Documentation technique (pour le rendu de cours)

| Document | Format | Contenu |
|---|---|---|
| **Ce plan (PLAN.md)** | Markdown | Architecture, choix techniques, modèle de données |
| **OpenAPI Spec** | `swagger.yaml` | Tous les endpoints documentés (schémas, exemples, erreurs) |
| **Schéma BDD** | Migrations Laravel + diagramme ERD | Tables, relations, types |
| **Schéma d'architecture** | Diagramme ASCII / draw.io | Vue globale du système |
| **README** | Markdown | Instructions d'installation et lancement |
| **.env.example** | dotenv | Variables d'environnement attendues (sans valeurs) |

### 9.2 Documentation OpenAPI (swagger.yaml)

Chaque endpoint doit documenter :
- Summary et description
- Paramètres (path, query, body) avec types et exemples
- Réponses : 200, 400, 401, 404, 429, 500
- Schémas de réponse (objets JSON typés)
- Sécurité (routes protégées par JWT)

### 9.3 Documentation dans le code

- **PHPDoc** sur chaque Service et Controller (`@param`, `@return`, `@throws`)
- **Types PHP 8.3** explicites sur les méthodes (pas de `mixed` sauvage)
- **Commentaires** uniquement sur la logique non évidente (TTL choix, hash strategy…)
- **Types TypeScript** côté Vue (pas de `any`)

---

## 10. Plan de développement

### Phase 1 — Fondations (infra + auth)
- [ ] Mise en place Docker Compose (Laravel, PostgreSQL, Redis)
- [ ] Initialisation projet Laravel 11 + configuration `.env`
- [ ] Configuration Eloquent + driver PostgreSQL
- [ ] Migrations initiales (users, soirees, favorites, reviews)
- [ ] Configuration driver Redis (`CACHE_DRIVER=redis`, `SESSION_DRIVER=redis`)
- [ ] Routes auth avec Laravel Sanctum (register / login / logout / me)
- [ ] Middleware d'erreur global (Handler Laravel)
- [ ] Setup L5-Swagger (annotations OpenAPI)
- [ ] Initialisation Vue 3 + Vite + Tailwind

### Phase 2 — Services APIs externes
- [ ] WeatherService (OpenWeatherMap + `Cache::remember()` 10min)
- [ ] EventService (OpenAgenda + `Cache::remember()` 30min)
- [ ] PlaceService (Foursquare + `Cache::remember()` 1h)
- [ ] MusicService (Spotify OAuth2 + token Redis + `Cache::remember()` 2h)
- [ ] TransportService (Navitia/TAN + `Cache::remember()` 5min)
- [ ] AIService (Mistral AI + `Cache::remember()` 1h)
- [ ] MailService (Laravel Mail + driver Mailjet + rate limit Redis)

### Phase 3 — Logique métier (SoireeService)
- [ ] Orchestration des 6 services → génération d'une soirée complète
- [ ] Sauvegarde en PostgreSQL
- [ ] Endpoint `POST /soiree/generate`
- [ ] Endpoints historique, favoris, reviews

### Phase 4 — Frontend
- [ ] Page d'accueil + formulaire humeur/budget/dispo
- [ ] Page résultat de soirée (météo, event, lieu, transport, playlist, narration)
- [ ] Carte Leaflet avec les lieux
- [ ] Page profil (historique, favoris)
- [ ] Formulaire partage email

### Phase 5 — Polish & documentation
- [ ] Compléter la spec OpenAPI
- [ ] Rédiger le README
- [ ] Générer le diagramme ERD
- [ ] Tests manuels de bout en bout
- [ ] Vérification sécurité (variables d'env, CORS, Helmet)

---

## 11. Séparation des responsabilités

| Membre | Périmètre suggéré |
|---|---|
| **Dev 1** | Phase 1 (infra, auth, BDD) + Phase 3 (SoireeService, endpoints métier) |
| **Dev 2** | Phase 2 (services APIs externes) + Phase 5 (OpenAPI, documentation) |
| **Dev 3** | Phase 4 (frontend complet) |

> Cette répartition est indicative. Les phases 1 et 2 peuvent avancer en parallèle
> dès que les interfaces TypeScript partagées sont définies.

---

*Dernière mise à jour : 2026-04-09*
