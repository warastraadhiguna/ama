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

### Master Data (Milestone C)

Activity Types, Product Categories, Products, Positions, Work Locations — server-owned lookups the Android app caches for offline use (docs section 27). Reads are open to any authenticated user (mobile sync); writes require the `master_data.manage` permission (ADMIN/SUPER_ADMIN).

```
GET    /api/v1/master/{activity-types|product-categories|products|positions|work-locations}
GET    /api/v1/master/{resource}/{id}
POST   /api/v1/master/{resource}                 (master_data.manage)
PUT    /api/v1/master/{resource}/{id}             (master_data.manage)
DELETE /api/v1/master/{resource}/{id}             (master_data.manage)
```

`products` additionally accepts `?product_category_id=` to drive the dependent Kategori Produk -> Produk/Varietas dropdown (docs section 16) and returns the `category` relation inline. Seeded with the starter lists from docs sections 15-16 (7 activity types; BEKA/POMMIX/POMI under their categories).

Activity Types, Product Categories, Positions, and Work Locations are all shaped identically (name/code/is_active), so they share one generic `SimpleMasterDataController` rather than four near-duplicate CRUD implementations; Products gets its own controller for the category relation/filter.

### Planning (Milestone D)

Activity plans (docs section 13) — a plan can carry multiple products (`activity_plan_products`, matching the docs' own section 31 schema baseline, which answers OPEN QUESTION #1 for plans/activities). State machine (docs section 13.3): `PLANNED -> READY -> CANCELLED`, both non-terminal states can be edited; `REALIZED` is reserved for the Activities module (Milestone E) to set when a realization is linked — the Planning API rejects setting it directly.

```
GET  /api/v1/plans            ?status=   (plans.view sees everyone's; otherwise only your own)
GET  /api/v1/plans/{id}
POST /api/v1/plans            { activity_type_id, location, planned_date, notes?, product_ids: [] } (plans.create)
PUT  /api/v1/plans/{id}       partial update, incl. status                                          (plans.create + must be the creator)
```

Authorization default (docs section 44 Q9/Q11 are unresolved, so this is a documented assumption, not a confirmed rule): `plans.view` is a monitoring permission (ADMIN/MANAGER/SUPERVISOR/SUPER_ADMIN) — see every plan, read-only. `plans.create` (AGRONOMIST) — manage only the plans you created yourself; no separate "view own" permission is needed for that, so AGRONOMIST intentionally does **not** have `plans.view` (it would widen them to everyone's plans instead of just their own).

### Activity Realization (Milestone E)

Activities (docs section 14) — both realization modes from one endpoint, branching on whether `activity_plan_id` is present:

```
GET  /api/v1/activities        ?status=   (activities.view sees everyone's; otherwise only your own)
GET  /api/v1/activities/{id}
POST /api/v1/activities        (activities.create)
     Manual (docs 14.2):    { activity_type_id, product_ids: [], location, notes? }
     From a plan (docs 14.1): { activity_plan_id, location, notes? }  -- activity type & products are
                                copied from the plan, not re-entered
```

Realizing a plan (docs section 13.3) atomically: creates the activity, copies the plan's activity type/products onto it, sets the plan's `status` to `REALIZED`, and sets `activity_plans.realized_activity_id` to point at the new activity (a column that could only be added now — it references `activities`, which didn't exist during Milestone D). Only the plan's own creator can realize it, and only from `PLANNED`/`READY` (not already `REALIZED`/`CANCELLED`) — enforced with a row lock (`lockForUpdate`) so two concurrent realize requests can't double-spend the same plan.

New activities always start `DRAFT` (docs section 23's full state machine — `DRAFT/SUBMITTED/SYNCED/VERIFIED/REJECTED` — is modeled as an enum now so the design doesn't need to change later, but only `DRAFT` is reachable from this milestone). Deliberately out of scope here, coming in later milestones:
- `POST /activities/{id}/location`, `/photos`, `/complete` — Milestone F (Evidence); `/complete` is what moves `DRAFT -> SUBMITTED`.
- A `/activities/{id}/verify` review action gated by `activities.verify` — docs section 23 says V1 must not *force* approval, but the design should allow adding it, which the status enum already does.
