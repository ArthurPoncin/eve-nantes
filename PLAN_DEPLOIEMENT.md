# NOCTAMBULE — Plan de Déploiement (VPS + Coolify)

> Ce document décrit comment déployer NOCTAMBULE en production sur un VPS,
> via **Coolify** (PaaS open-source self-hosted, alternative Heroku/Render).
>
> Stack cible : Laravel 12 (web + worker Horizon + scheduler) + PostgreSQL + Redis,
> avec en option Ollama pour héberger le modèle IA en local.
>
> Prérequis lecture : `PLAN.md`, `PLAN_BACKEND.md`, `PLAN_SERVICES.md`.

---

## Table des matières

1. [Vue d'ensemble](#1-vue-densemble)
2. [Choix du VPS](#2-choix-du-vps)
3. [Installation de Coolify](#3-installation-de-coolify)
4. [Dockerfile production](#4-dockerfile-production)
5. [Services Coolify](#5-services-coolify)
6. [Variables d'environnement](#6-variables-denvironnement)
7. [Domaines & HTTPS](#7-domaines--https)
8. [CI/CD — webhook GitHub](#8-cicd--webhook-github)
9. [Monitoring & logs](#9-monitoring--logs)
10. [Backups](#10-backups)
11. [Checklist déploiement](#11-checklist-déploiement)

---

## 1. Vue d'ensemble

### Pourquoi Coolify ?

| Critère | Coolify | Render free | Heroku |
|---|---|---|---|
| Coût | ~5–10 €/mois (VPS) | Gratuit (limité) | ~7 $/mois (eco) |
| Cold start | ❌ aucun | ✅ 30 s après 15 min | ❌ |
| Stockage BDD | Limite VPS | 1 Go (90 j puis kill) | 1 Go |
| Self-hosted | ✅ | ❌ | ❌ |
| HTTPS auto | ✅ | ✅ | ✅ |
| `docker-compose` natif | ✅ | partiel | ❌ |
| Workers séparés | ✅ illimités | ✅ payant | ✅ payant |
| Ollama hébergeable | ✅ | ❌ | ❌ |

### Schéma de déploiement

```
                         ┌─────────────────────────┐
   Internet ─── HTTPS ──▶│  Reverse Proxy Caddy    │
                         │  (auto Let's Encrypt)   │
                         └────────┬────────────────┘
                                  │
                  ┌───────────────┴───────────────┐
                  │                               │
        ┌─────────▼──────────┐         ┌──────────▼─────────┐
        │  noctambule.fr     │         │  api.noctambule.fr │
        │  Frontend Vue      │         │  Laravel + nginx   │
        │  (static build)    │         │  + php-fpm         │
        └────────────────────┘         └──┬─────────────────┘
                                          │
                            ┌─────────────┼─────────────┬──────────┐
                            │             │             │          │
                  ┌─────────▼──┐  ┌───────▼─────┐  ┌────▼────┐ ┌───▼──────┐
                  │  Worker    │  │  Scheduler  │  │  Redis  │ │PostgreSQL│
                  │ (Horizon)  │  │             │  │         │ │          │
                  └────────────┘  └─────────────┘  └─────────┘ └──────────┘

                            (optionnel)
                  ┌─────────▼──┐
                  │   Ollama   │
                  │ gemma2:2b  │
                  └────────────┘
```

Tous les services tournent dans des containers Docker orchestrés par Coolify, sur le même VPS.
Le réseau interne Coolify permet aux containers de se parler par nom DNS (`postgres`, `redis`, `ollama`, etc.).

---

## 2. Choix du VPS

### Specs recommandées

| Profil | RAM | vCPU | Disque | Use case |
|---|---|---|---|---|
| **Minimal** (Mistral / Gemma API) | 4 Go | 2 | 40 Go | Suffisant : Coolify + Laravel + Postgres + Redis |
| **Confortable** (idem) | 8 Go | 4 | 80 Go | Marge pour 2 environnements (staging + prod) |
| **Avec Ollama** (Gemma 2 2B local) | 8 Go | 4 | 80 Go | Ollama + Gemma 2 2B = ~3 Go RAM mobilisée |
| **Avec Ollama 7B** | 16 Go | 8 | 160 Go | Si tu veux un modèle plus capable |

### Fournisseurs adaptés (UE)

| Fournisseur | Plan recommandé | Prix |
|---|---|---|
| **Hetzner Cloud** | CX22 (4 Go) ou CX32 (8 Go) | ~4 € / ~7 € |
| **Scaleway** | Stardust (1 Go, trop juste) ou Dev1-S (2 Go, juste) | ~5 € |
| **OVH** | VPS Comfort (4 Go) | ~10 € |
| **DigitalOcean** | Basic 4 Go | ~24 $ |

> Recommandation : **Hetzner CX32** (8 Go, 4 vCPU, 80 Go SSD, ~7 €/mois) pour pouvoir tester
> Ollama sans contrainte, et garder de la marge pour le build Docker.

### Préparation du VPS

```bash
# Mise à jour
sudo apt update && sudo apt upgrade -y

# Utilisateur non-root (si tu es root)
adduser noctambule
usermod -aG sudo noctambule
rsync --archive --chown=noctambule:noctambule ~/.ssh /home/noctambule

# Pare-feu basique
sudo apt install -y ufw
sudo ufw allow OpenSSH
sudo ufw allow 80
sudo ufw allow 443
sudo ufw allow 8000   # Coolify dashboard pendant l'install (sera fermé après mise sur HTTPS)
sudo ufw --force enable
```

---

## 3. Installation de Coolify

### Installation en une commande

```bash
curl -fsSL https://cdn.coollabs.io/coolify/install.sh | sudo bash
```

L'installeur :
- vérifie/installe Docker
- crée la configuration Coolify dans `/data/coolify/`
- démarre les containers Coolify (proxy, db interne, app dashboard)
- affiche l'URL d'accès initiale (`http://<ip-vps>:8000`)

### Premier accès

1. Ouvrir `http://<ip-vps>:8000`
2. Créer le compte admin
3. Aller dans **Settings → General**, définir l'URL Coolify (ex. `coolify.noctambule.fr` après avoir fait pointer un sous-domaine vers le VPS)
4. Activer **Force HTTPS** une fois le domaine SSL en place
5. **Settings → Servers** → vérifier que le serveur localhost est `Reachable`

### Connexion à GitHub

**Source → Add → GitHub App** (préféré) ou **GitHub PAT**.

L'option GitHub App :
- Coolify guide la création d'une GitHub App
- Tu autorises Coolify à lire les repos sélectionnés
- Le webhook auto-deploy se configure tout seul

---

## 4. Dockerfile production

> Multi-stage : build des assets frontend + composer install, puis image runtime légère.
> Le Dockerfile vit à la racine du repo.

### `Dockerfile`

```dockerfile
# =========================================
# Stage 1 — Build frontend Vue
# =========================================
FROM node:20-alpine AS frontend
WORKDIR /app
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build      # produit dist/ → assets statiques

# =========================================
# Stage 2 — Vendor PHP
# =========================================
FROM composer:2 AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# =========================================
# Stage 3 — Runtime PHP-FPM + Nginx
# =========================================
FROM php:8.3-fpm-alpine AS runtime

RUN apk add --no-cache \
        nginx \
        supervisor \
        postgresql-dev \
        libzip-dev \
        oniguruma-dev \
        icu-dev \
        git \
    && docker-php-ext-install pdo_pgsql pcntl bcmath intl zip \
    && rm -rf /var/cache/apk/*

WORKDIR /var/www/html

# Code Laravel + vendor
COPY backend/ ./
COPY --from=vendor /app/vendor ./vendor

# Frontend buildé dans public/
COPY --from=frontend /app/dist ./public/app

# Permissions Laravel
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Optimisation autoload + cache config
RUN composer dump-autoload --optimize \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Nginx + supervisord configs
COPY docker/nginx.conf       /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf

EXPOSE 80
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
```

### `docker/nginx.conf`

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location /horizon {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

### `docker/supervisord.conf`

```ini
[supervisord]
nodaemon=true
user=root

[program:php-fpm]
command=php-fpm -F
autostart=true
autorestart=true

[program:nginx]
command=nginx -g 'daemon off;'
autostart=true
autorestart=true
```

> Pour les services **worker** et **scheduler**, on réutilise la même image Docker
> mais en surchargant `CMD` côté Coolify (cf. §5). Pas besoin d'un Dockerfile séparé.

---

## 5. Services Coolify

Sur Coolify, on crée **un projet** `NOCTAMBULE` qui contient plusieurs ressources.

### 5.1 Application Web (Laravel + Nginx)

- **Type** : Application
- **Source** : GitHub (repo `evenantes/...`) — branche `main`
- **Build pack** : Dockerfile
- **Dockerfile path** : `./Dockerfile`
- **Port** : `80`
- **Domaines** : `api.noctambule.fr` (configuré §7)
- **Health check** : `GET /api/v1/weather` (réponse 200 ou 429)
- **Resources** : 1 vCPU, 1 Go RAM

### 5.2 Worker Horizon

- **Type** : Application (clone du Web ou nouvelle ressource pointant le même repo)
- **Build pack** : Dockerfile (même)
- **Custom start command** : `php artisan horizon`
- **Pas de port exposé**
- **Resources** : 0.5 vCPU, 512 Mo RAM

> Astuce Coolify : l'option *"Use the same source as another resource"* permet de
> réutiliser le build de la web app au lieu de tout rebuilder.

### 5.3 Scheduler

- **Type** : Application (idem)
- **Custom start command** : `php artisan schedule:work`
- **Pas de port exposé**
- **Resources** : 0.25 vCPU, 256 Mo RAM

### 5.4 PostgreSQL

- **Type** : Database → PostgreSQL 16
- **Persistent volume** : oui, monté sur `/var/lib/postgresql/data`
- **Backups** : activer le backup quotidien Coolify
- Coolify expose `DATABASE_URL` aux apps reliées via shared environment

### 5.5 Redis

- **Type** : Database → Redis 7
- **Persistent volume** : oui (pour ne pas perdre les jobs Horizon en pause)
- **Mode** : standalone (pas de cluster pour ce projet)

### 5.6 Ollama (optionnel — IA self-hosted)

À créer **uniquement si** `AI_PROVIDER=ollama` et VPS ≥ 8 Go RAM.

- **Type** : Service → Custom Docker image
- **Image** : `ollama/ollama:latest`
- **Persistent volume** : `/root/.ollama` (les modèles téléchargés y sont stockés)
- **Pas de port exposé publiquement** (réseau interne uniquement)
- **Init command** (run-once après démarrage) : `ollama pull gemma2:2b`
- **Resources** : 2 vCPU, 4 Go RAM

L'app Laravel y accède via `OLLAMA_BASE_URL=http://ollama:11434` (DNS interne Coolify).

---

## 6. Variables d'environnement

Dans Coolify, chaque ressource a son onglet **Environment**. Coolify injecte les variables au démarrage du container — **jamais de `.env` committé**.

Variables minimales à définir sur la **web** (et à dupliquer sur worker + scheduler) :

```dotenv
APP_NAME=NOCTAMBULE
APP_ENV=production
APP_KEY=base64:...           # généré une fois avec `php artisan key:generate --show`
APP_URL=https://api.noctambule.fr
APP_DEBUG=false

# DB — Coolify injecte automatiquement DATABASE_URL ; on peut aussi décomposer :
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=noctambule
DB_USERNAME=noctambule
DB_PASSWORD=...

# Redis (idem, hostname interne)
REDIS_HOST=redis
REDIS_PORT=6379
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Sanctum
SANCTUM_STATEFUL_DOMAINS=noctambule.fr
FRONTEND_URL=https://noctambule.fr

# APIs externes (cf. PLAN_BACKEND.md §4)
OPENWEATHER_API_KEY=...
OPENAGENDA_API_KEY=...
FOURSQUARE_API_KEY=...
NAVITIA_API_KEY=...

# IA
AI_PROVIDER=mistral           # ou gemma, ou ollama
MISTRAL_API_KEY=...
# (si ollama)
# OLLAMA_BASE_URL=http://ollama:11434
# OLLAMA_MODEL=gemma2:2b

# Mail
MAIL_MAILER=mailjet
MAILJET_APIKEY=...
MAILJET_APISECRET=...
MAIL_FROM_ADDRESS=noreply@noctambule.fr
MAIL_FROM_NAME=NOCTAMBULE

# OAuth (production)
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=https://api.noctambule.fr/api/v1/auth/google/callback
GITHUB_CLIENT_ID=...
GITHUB_CLIENT_SECRET=...
GITHUB_REDIRECT_URI=https://api.noctambule.fr/api/v1/auth/github/callback
```

> Coolify a une fonctionnalité **Shared variables** au niveau projet — utile pour ne définir
> les credentials APIs externes qu'**une seule fois** et les partager à web/worker/scheduler.

### Migrations

Au premier deploy, exécuter dans le terminal Coolify de la web app (ou via la commande post-deploy) :

```bash
php artisan migrate --seed --force
```

Pour les deploys suivants, ajouter une **post-deploy command** dans Coolify :
```bash
php artisan migrate --force && php artisan config:cache && php artisan route:cache
```

---

## 7. Domaines & HTTPS

### Préparation DNS

Sur ton registrar (OVH, Gandi, Cloudflare…), créer des records `A` :

```
noctambule.fr           A   <ip-vps>      ← frontend
api.noctambule.fr       A   <ip-vps>      ← Laravel API
coolify.noctambule.fr   A   <ip-vps>      ← dashboard Coolify (optionnel mais conseillé)
```

> Si Cloudflare est devant : laisser le proxy Cloudflare en **DNS only** (nuage gris)
> pour que Let's Encrypt puisse valider via HTTP-01. Activer le proxy orange ensuite si besoin.

### Configuration Coolify

Sur la ressource Web, onglet **Domains** :
1. Ajouter `api.noctambule.fr`
2. Coolify détecte le DNS et provisionne automatiquement un certificat Let's Encrypt
3. Activer **Force HTTPS redirect**

Idem pour le frontend (s'il est déployé en static via Coolify) ou via un autre service.

> Pour le dashboard Coolify lui-même : `Settings → General → Instance Domain` →
> `coolify.noctambule.fr`. Coolify se met automatiquement en HTTPS.

---

## 8. CI/CD — webhook GitHub

### Configuration auto-deploy

Sur la ressource Web → **Configuration** → **Source** :
1. Cocher **Auto deploy on push**
2. Brancher la branche `main`
3. Coolify a déjà créé le webhook GitHub via la GitHub App

### Workflow recommandé

```
Dev ──► PR ──► tests CI (GitHub Actions)
                  │
                  ✓ green
                  │
                  ▼
              merge main
                  │
                  ▼
       Webhook GitHub → Coolify
                  │
                  ▼
       Build Docker image (Dockerfile)
                  │
                  ▼
       Deploy zero-downtime (nouveau container, healthcheck OK, swap)
                  │
                  ▼
       Post-deploy : migrate --force
```

### Tests CI minimaux (GitHub Actions)

`.github/workflows/ci.yml` :

```yaml
name: CI
on:
  pull_request:
  push:
    branches: [main]
jobs:
  laravel-tests:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:16-alpine
        env: { POSTGRES_PASSWORD: secret, POSTGRES_DB: noctambule_test }
        ports: ['5432:5432']
        options: --health-cmd="pg_isready" --health-interval=5s
      redis:
        image: redis:7-alpine
        ports: ['6379:6379']
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3', extensions: pdo_pgsql, redis }
      - run: composer install --no-progress
        working-directory: backend
      - run: cp .env.example .env && php artisan key:generate
        working-directory: backend
      - run: php artisan migrate --force
        working-directory: backend
      - run: php artisan test
        working-directory: backend
```

---

## 9. Monitoring & logs

### Logs Coolify

- Onglet **Logs** sur chaque ressource → stream en temps réel
- Filtrage par `stdout` / `stderr`
- Téléchargement possible

### Logs Laravel

Les logs Laravel sont écrits dans `storage/logs/laravel.log` à l'intérieur du container.
Pour les exposer dans Coolify :
- Configurer le driver de log Laravel sur `stderr` (`LOG_CHANNEL=stderr`)
- Ils apparaissent alors dans la fenêtre Logs de Coolify

```dotenv
LOG_CHANNEL=stderr
LOG_LEVEL=info
```

### Horizon dashboard

Accessible sur `https://api.noctambule.fr/horizon` après auth.
Restreindre l'accès dans `app/Providers/HorizonServiceProvider.php` :

```php
protected function gate(): void
{
    Gate::define('viewHorizon', function ($user) {
        return in_array($user->email, [
            'arthur.poncin@sixense-group.com',
        ]);
    });
}
```

### Alertes Coolify

- Notifications par email ou webhook Discord/Slack/Telegram (Settings → Notifications)
- Alertes sur : deploy failed, container crashed, certificat expiré

---

## 10. Backups

### PostgreSQL

Coolify propose un **backup automatique** intégré sur les BDD :
- Settings BDD → **Scheduled Backups**
- Fréquence : quotidienne (cron `0 3 * * *`)
- Rétention : 7 jours sur le VPS + (optionnel) S3-compatible (Backblaze B2, Scaleway Object Storage…)

### Volumes Docker

Pour Redis (sessions + queues Horizon en pause) et Ollama (modèles téléchargés) :
- Snapshots Hetzner / OVH du VPS — 1× par semaine, conservation 4 snapshots
- Coût : ~20% du prix du VPS

### Code

Le repo Git est la source de vérité — pas de backup nécessaire si push réguliers sur GitHub.

---

## 11. Checklist déploiement

### Avant le premier deploy
- [ ] VPS commandé (Hetzner CX32 recommandé) et configuré (user non-root, UFW)
- [ ] DNS pointant vers l'IP du VPS (`noctambule.fr`, `api.noctambule.fr`, `coolify.noctambule.fr`)
- [ ] Coolify installé et accessible
- [ ] GitHub App Coolify autorisée sur le repo
- [ ] Comptes APIs externes créés et clés récupérées (OpenWeather, OpenAgenda, Foursquare, Navitia, Mistral, Mailjet)

### Setup projet Coolify
- [ ] Projet `NOCTAMBULE` créé
- [ ] Ressource PostgreSQL 16 + persistent volume
- [ ] Ressource Redis 7 + persistent volume
- [ ] Ressource Application Web (Dockerfile, port 80, domaine `api.noctambule.fr`)
- [ ] Ressource Application Worker (start command `php artisan horizon`)
- [ ] Ressource Application Scheduler (start command `php artisan schedule:work`)
- [ ] (Optionnel) Ressource Ollama (image officielle, volume `/root/.ollama`)

### Variables d'environnement
- [ ] `APP_KEY` généré et défini
- [ ] Toutes les clés APIs externes définies (cf. §6)
- [ ] `AI_PROVIDER` choisi (`mistral` recommandé pour démarrer)
- [ ] OAuth Google + GitHub configurés avec les bonnes redirect URIs

### Premier deploy
- [ ] Push sur `main` → webhook → build Docker OK
- [ ] Container web `Healthy` (healthcheck `/api/v1/weather`)
- [ ] Migrations exécutées : `php artisan migrate --seed --force`
- [ ] HTTPS Let's Encrypt actif (cadenas vert dans le navigateur)
- [ ] Test manuel : `curl https://api.noctambule.fr/api/v1/weather` → JSON OK
- [ ] Test manuel : `curl https://api.noctambule.fr/api/v1/venues` → 6 venues seedés

### Vérifications post-deploy
- [ ] Worker Horizon visible et `Active` sur `https://api.noctambule.fr/horizon`
- [ ] Un job test (`GenerateAINarrativeJob::dispatch($soiree)`) passe en `Completed`
- [ ] Scheduler exécute `PrefetchWeatherJob` toutes les 10 minutes (logs)
- [ ] Email de test envoyé via Mailjet (vérifier dans le dashboard Mailjet)
- [ ] Logs Laravel apparaissent dans Coolify (driver `stderr`)
- [ ] Backup BDD planifié (Coolify → Postgres → Backups)

### Sécurité
- [ ] `APP_DEBUG=false` en prod
- [ ] Dashboard Horizon protégé par gate (cf. §9)
- [ ] Dashboard Coolify accessible uniquement avec compte admin (2FA recommandé)
- [ ] Port 8000 fermé dans UFW (Coolify est désormais sur HTTPS)
- [ ] Rate limiters Laravel actifs (60 req/min, 5 mails/jour)

---

*Dernière mise à jour : 2026-04-29*
