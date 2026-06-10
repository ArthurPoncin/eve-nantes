# 🌙 NOCTAMBULE

> Le compagnon des nuits nantaises — découvrir où sortir, composer sa soirée, et la partager.

**Démo en ligne** : [noctambule.zespri.duckdns.org](https://noctambule.zespri.duckdns.org) · **Documentation API (Swagger)** : [/api/documentation](https://noctambule.zespri.duckdns.org/api/documentation)

![Maquette](images/MaquetteSunSet.png)

## Le concept

NOCTAMBULE (projet *Eve-Nantes*) est une application web qui répond à une question simple : **« on fait quoi ce soir à Nantes ? »**

Elle agrège des données publiques (open data métropolitain, OpenStreetMap, météo, transports en temps réel) pour proposer plus de **400 lieux** (bars, pubs, boîtes) et **~500 événements** à venir, puis va plus loin que le simple annuaire :

- un **composeur de soirée** génère un itinéraire personnalisé (lieu + événement) selon l'ambiance recherchée et la météo du soir, avec une narration écrite par IA ;
- une dimension sociale façon **« Strava de la nuit »** : on *check-in* dans les bars, les check-ins s'enchaînent en **virées** que l'on peut partager, suivre d'autres noctambules, leur envoyer des « Santé ! », débloquer des badges et relever des défis mensuels.

L'application est volontairement **100 % gratuite côté APIs** : toutes les sources de données sont publiques ou en free tier, aucune ne nécessite d'abonnement payant.

## Équipe

| Membre | Contact |
|---|---|
| Martin Ornh | martin.ornh@ecoles-epsi.net |
| Arthur Poncin | poncin.arthur@gmail.com |
| Foidjou Daumard | foidjou.daumard@gmail.com |

## Fonctionnalités

### 🗺️ Découvrir
- **Carte interactive** (`/explorer`) : tous les lieux sur une carte Leaflet, filtrables par ambiance (*chill*, *festif*, *afterwork*, *découverte*).
- **Fiches lieux** : prochain événement programmé, avis des utilisateurs, météo du soir, arrêts TAN/Naolib les plus proches avec **temps d'attente en temps réel**.
- **Agenda** : concerts, festivals, bals, spectacles d'humour et arts de la rue sur les 2 prochains mois.

### 🍸 Composer sa soirée
- **Générateur de soirée** : ambiance + envies → un itinéraire bar/événement cohérent, narré par **Mistral AI** (avec repli sur une narration locale si l'API est indisponible — l'application ne dépend jamais d'un service externe pour fonctionner).
- **Partage par email** du programme de la soirée (via Resend).

### 🏃 Le « Strava de la nuit »
- Bouton **« J'y suis »** sur chaque fiche lieu : les check-ins de la soirée forment une **virée**, avec récap (lieux visités, distance nocturne parcourue) et visibilité publique ou privée.
- **Suivre** d'autres noctambules, **fil d'actualité** de leurs virées, kudos **« Santé ! »**.
- **Profils publics** (`/u/pseudo`) avec recherche d'utilisateurs.

### 🏆 Gamification
- **Badges** à débloquer (check-ins, virées, kilomètres de nuit…).
- **Défis mensuels** avec progression.
- **Wrapped nocturne** (`/profil/stats`) : rétrospective personnelle de ses sorties.
- Titre de **« Pilier de bar »** pour le plus assidu de chaque lieu.

### ✨ Interface
- Double thème **sunset / night**, choisi automatiquement selon l'heure (sunset de 8 h à 21 h, night la nuit), surchargeable manuellement.
- Design *glassmorphism*, responsive (menu burger mobile, carte plein écran).

## Stack technique

| Couche | Technologies |
|---|---|
| **Backend** | PHP 8.2 · Laravel 12 · Sanctum (auth par tokens) · L5-Swagger (OpenAPI) |
| **Base de données** | PostgreSQL 16 · Redis 7 (cache, sessions, files d'attente) |
| **Frontend** | Vue 3 (Composition API) · TypeScript · Vite · Tailwind CSS 4 · Pinia · Leaflet |
| **Tests** | PHPUnit (142 tests) · Vitest + MSW (150 tests) · Playwright (E2E) |
| **Infra** | Docker Compose · GitHub Actions · GHCR · Watchtower · Traefik (HTTPS) |
| **Outils** | Claude (assistance IA au développement) |

## Les données : tout vient d'APIs publiques

| Source | Usage | Rafraîchissement |
|---|---|---|
| [Open data Nantes Métropole](https://data.nantesmetropole.fr) | Agenda des événements (5 catégories festives, fenêtre de 2 mois) | Import quotidien à 4 h |
| [OpenStreetMap / Overpass](https://overpass-api.de) | Bars, pubs et boîtes de la commune de Nantes | Import hebdomadaire |
| [Open-Meteo](https://open-meteo.com) | Météo du soir (sans clé) | Temps réel, mis en cache |
| TAN / Naolib | Attentes tram & bus en temps réel | Temps réel |
| [Mistral AI](https://mistral.ai) | Narration du composeur de soirée (free tier) | À la demande |
| [Resend](https://resend.com) | Envoi des emails de partage | À la demande |

Quelques choix d'implémentation notables :

- Les imports sont **idempotents** (rapprochement par slug) : un lieu enrichi à la main n'est jamais écrasé par l'import suivant.
- L'import OpenStreetMap **bascule automatiquement sur une instance miroir** quand l'instance principale rate-limite (cas réel rencontré en production).
- L'ambiance d'un lieu est **déduite heuristiquement** : type OSM (nightclub → festif, pub → afterwork) affiné par les mots-clés du nom (une « cave à vin » taguée bar devient afterwork).
- Les imports sont relancés **à chaque déploiement** : la production reste à jour sans intervention.

## Architecture

```
Webservice/
├── backend/    API REST Laravel (préfixe /api/v1)
├── frontend/   SPA Vue 3
└── docker-compose.yml
       ├── postgres    PostgreSQL 16
       ├── redis       cache / sessions / queues
       ├── backend     API (port 8000)
       ├── scheduler   planificateur Laravel (imports périodiques)
       └── frontend    Vite dev server (port 5173)
```

- **API REST versionnée** (`/api/v1/…`) : réponses JSON normalisées par des API Resources, validation par Form Requests, erreurs structurées.
- **Authentification Sanctum** : tokens bearer ; les routes publiques (lieux, événements, météo) restent accessibles sans compte, les routes sociales (favoris, avis, check-ins, fil) sont protégées.
- **Rate limiting** sur les endpoints sensibles (partage email, recherche).
- La SPA consomme exclusivement l'API — aucun rendu côté serveur.

## Documentation API

L'API est intégralement documentée en **OpenAPI 3** via des attributs PHP 8 posés directement sur les contrôleurs et les Resources (source unique de vérité, vérifiée par les tests).

- Swagger UI : [`/api/documentation`](https://noctambule.zespri.duckdns.org/api/documentation) — toutes les opérations sont essayables depuis l'interface, avec authentification bearer intégrée.
- La spec est régénérée automatiquement à chaque démarrage du conteneur.

## Lancer le projet en local

Prérequis : Docker + Docker Compose. **Aucune installation de PHP, Composer ou Node n'est nécessaire.**

```bash
git clone https://github.com/ArthurPoncin/eve-nantes.git
cd eve-nantes
docker compose up -d
```

Le conteneur backend s'auto-configure au démarrage : copie du `.env`, installation des dépendances Composer, génération de la clé d'application, migrations, génération de la doc Swagger.

Pour peupler la base immédiatement (sinon le planificateur s'en charge la nuit) :

```bash
docker compose exec backend php artisan events:import
docker compose exec backend php artisan venues:import
```

L'application est alors disponible sur :
- **Frontend** : http://localhost:5173
- **API** : http://localhost:8000/api/v1
- **Swagger** : http://localhost:8000/api/documentation

> Les clés optionnelles (`MISTRAL_API_KEY`, `RESEND_API_KEY`) se posent dans `backend/.env` ; sans elles, le composeur de soirée fonctionne en mode narration locale et seul le partage par email est désactivé.

## Tests

```bash
# Backend — 142 tests (sqlite en mémoire, HTTP mocké : aucun appel réseau réel)
docker compose run --rm --no-deps -e APP_ENV=testing -e DB_CONNECTION=sqlite \
  -e DB_DATABASE=:memory: -e CACHE_STORE=array -e SESSION_DRIVER=array \
  -e QUEUE_CONNECTION=sync --entrypoint sh backend -c 'php artisan test'

# Frontend — 150 tests unitaires (Vitest + Testing Library, API mockée par MSW)
cd frontend && npm ci && npm test

# E2E — parcours complets dans un vrai navigateur (Playwright)
cd frontend && npm run e2e
```

Les imports de données sont testés contre des fixtures reproduisant les réponses réelles des APIs (y compris les cas d'erreur : rate limiting, instances indisponibles, données incomplètes).

## CI/CD et déploiement

Chaque push sur `main` déclenche le pipeline GitHub Actions :

```
push main ─┬─> tests backend (PHPUnit) ──────────┐
           ├─> lint + format + tests frontend ───┼─> images Docker ─> GHCR
           └─> tests E2E Playwright ─────────────┘                     │
                                                                       ▼
                     VPS : Watchtower détecte les nouvelles images,
                     recrée les conteneurs (migrations + réimport des
                     données au boot), Traefik termine le TLS.
```

Aucun déploiement manuel : si les tests passent, la production est à jour quelques minutes plus tard.

---

*Projet réalisé dans le cadre du module Webservice — EPSI.*
