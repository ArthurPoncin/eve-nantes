# EveNantes (structure Laravel)

Base de projet Laravel prete pour une application d'evenements sur Nantes.

## Domaines inclus

- Evenements
- Lieux (venues)
- Organisateurs
- Categories

## Points d'entree deja poses

- `routes/web.php` : accueil + listing + detail evenement
- `app/Http/Controllers/*` : controleurs Home et Event
- `app/Models/*` : modeles Eloquent de base
- `database/migrations/*` : schema initial
- `database/seeders/NantesDemoSeeder.php` : jeu de donnees de demo Nantes
- `resources/views/*` : vues Blade de base

## Demarrage local

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```
