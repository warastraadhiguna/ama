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
```

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
