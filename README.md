# Agro Marketing App — Backend

Laravel modular monolith backend + Web Admin (Inertia + React) for Agro Marketing App (AMA).

Source of truth for requirements/architecture: [`docs/AMA_SYSTEM_DOCUMENTATION_v1.0.md`](docs/AMA_SYSTEM_DOCUMENTATION_v1.0.md).

## Stack

- Laravel 13 (PHP 8.5)
- PostgreSQL 16 + PostGIS 3.4
- Redis (cache, session, queue)
- Docker Compose for local environment parity

## Local development

```bash
docker compose up -d --build
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

The seeder creates two dev accounts (password `password` for both):

| Email | Role |
|-------|------|
| admin@ama.test | SUPER_ADMIN |
| agronomist@ama.test | AGRONOMIST |

App is served at http://localhost:8000 (via Nginx -> PHP-FPM).

Services:

| Service  | Port |
|----------|------|
| app (http) | 8000 |
| postgres | 5432 |
| redis    | 6379 |

## Testing

```bash
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
```

## Conventions

- Backend code is organized as a modular monolith under `Modules/` (see docs section 6-7). Business rules live in Domain/Application layers, not controllers.
- Requirements not yet finalized by the Product Owner (docs section 44) are implemented with a documented sensible default and marked `// OPEN QUESTION:` in code so they can be revisited without a large refactor.
- Do not change core architecture (framework, database engine, modular-monolith approach) without Product Owner approval (docs section 41.1).

## Modules implemented so far

### Identity (Milestone B)

Users, roles/permissions (`spatie/laravel-permission`), mobile access+refresh token auth (`laravel/sanctum` for access tokens, a custom rotating `refresh_tokens` table), and device registration/revocation.

```
POST /api/v1/auth/login       { email, password, device_uuid? }
POST /api/v1/auth/refresh     { refresh_token }              -> rotates the refresh token
POST /api/v1/auth/logout      { refresh_token? }              (auth:sanctum)
GET  /api/v1/me                                               (auth:sanctum)
POST /api/v1/devices/register { device_uuid, app_version, os_version?, manufacturer?, model? } (auth:sanctum)
```

Implementation defaults not specified by the baseline doc (tune in `config/identity.php` / revisit as needed):
- Access token TTL: 60 minutes. Refresh token TTL: 30 days, single-use (rotated on every `/auth/refresh` call).
- A `device_uuid` is bound to the account that first registers it; a revoked device, or a UUID reused under a different account, is rejected (`403 DEVICE_REVOKED`) at both login and `/devices/register`.
- Positions and Work Locations got minimal tables here (`users` needs the FK) — full Master Data CRUD/API for them is Milestone C.
