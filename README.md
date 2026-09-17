# Agro Marketing App — Backend

Laravel modular monolith backend + Web Admin (Inertia + React) for Agro Marketing App (AMA).

Source of truth for requirements/architecture: [`docs/AMA_SYSTEM_DOCUMENTATION_v1.0.md`](docs/AMA_SYSTEM_DOCUMENTATION_v1.0.md).

## Stack

- Laravel 13 (PHP 8.5)
- PostgreSQL 16 + PostGIS 3.4
- Redis (cache, session, queue)
- MinIO (S3-compatible object storage for evidence photos; local dev only — see docs section 5.5)
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
| minio (S3 API) | 9000 |
| minio (console) | 9001 |

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

### Evidence (Milestone F)

Adds `Modules/Evidence/`, covering the "Activity Evidence Bundle" (docs section 18): GPS + photo tied together through a capture session. **Naming deviation from docs section 6** (worth flagging per section 50's "report conflicts, don't silently pick"): the doc lists `Location` and `Media` as separate modules, but a capture session only makes sense with both together, and Milestone F itself bundles them as one deliverable — so this is one module instead of two. Can still be split later if a real need for that separation shows up.

```
POST /api/v1/activities/{id}/location  { capture_session_uuid, started_at, latitude, longitude, accuracy, altitude?, speed?, bearing?, provider?, captured_at_device }
POST /api/v1/activities/{id}/photos    multipart: photo, capture_session_uuid, started_at, latitude, longitude, accuracy, captured_at_device
POST /api/v1/activities/{id}/complete
```

All three require `activities.create` + being the activity's own creator, and the activity must still be `DRAFT`. `capture_session_uuid` is client-generated (find-or-create per activity) so retried location/photo uploads after a dropped connection don't create duplicate sessions. Photos: only `CameraX`/`image/jpeg|png` accepted (docs section 17.2), hashed with SHA-256 server-side, stored on the `s3` disk (MinIO locally — see `docker-compose.yml`; point `AWS_*` env vars at real S3/R2 for staging/production, docs section 5.5), max 15MB (docs section 17.4's upper bound — real compression is the client's job before upload). `/complete` requires at least one capture session with both a location and a photo, then moves the activity to `SUBMITTED`.

Deliberately not evaluated yet (Milestone G — Integrity): mock-location detection, Play Integrity, the 30-60s GPS/photo time-window check, impossible-travel detection. `activity_photos.integrity_status` defaults to `PENDING` as a placeholder for that.

Two real bugs found and fixed while building this (both covered by regression tests now):
- The access token's Sanctum "name" is used to recover which device made a request (login names the token after `device_uuid`) — but a login *without* a device names the token the literal string `"login"`, not a UUID, which crashed the lookup against the uuid-typed `devices.device_uuid` column. `ResolveCurrentDevice` now checks `Str::isUuid()` first. This class of bug only reproduces through a **real** login (`postJson('/auth/login')`), not `actingAs()`, which fakes authentication without creating a token — the regression test therefore does a real login.
- Docker Compose recreating the `app` container (e.g. on every `--build`) gives it a new internal IP; nginx's static `fastcgi_pass app:9000` had cached the old one and 502'd until manually restarted. Fixed with `resolver 127.0.0.11` + a `$app_upstream` variable in `docker/nginx/default.conf` so nginx re-resolves instead of caching for the worker's lifetime.

### Integrity (Milestone G)

Adds `Modules/Integrity/` (docs section 19's multi-layer location integrity — **another module not in docs section 6's list**, same rationale/flagging as Evidence: the layers overlap Location/Activities enough that a dedicated module reads clearer than force-fitting into an existing one). Evaluates the signals Milestone F only stored:

- **Layer 1 (mock location)**: `SubmitLocationRequest` now requires `is_mock_location` (the Android-reported flag). Policy is `config('integrity.mock_location_policy')`, `FLAGGED` by default (docs section 44 Q7, unresolved) — `BLOCK` rejects the submission outright (`403`, no row created), `FLAGGED` accepts it but marks the location `SUSPICIOUS`.
- **Layer 3 (accuracy)**: worse than `config('integrity.max_acceptable_accuracy_meters')` (default 50m, docs section 44 Q5) marks the location `SUSPICIOUS` with reason `LOW_GPS_ACCURACY`.
- **Layer 4 (impossible travel)**: compares a new location against the same user's most recent prior location (any activity) via a Haversine distance; implied speed over `config('integrity.max_plausible_speed_kmh')` (default 150 km/h) marks it `SUSPICIOUS` with `IMPOSSIBLE_TRAVEL`.
- **Layer 5 (capture window)**: at `/complete`, a capture session whose location and photo `captured_at_device` are further apart than `config('integrity.capture_session_max_window_seconds')` (default 60s, docs section 44 Q6) gets its location marked `SUSPICIOUS` with `CAPTURE_WINDOW_EXCEEDED` — this does **not** block completion (docs section 23: V1 must not force approval), only annotates it.
- **Layer 2 (Play Integrity) — honestly not functional yet**: `POST /api/v1/integrity/play` exists (matches the docs section 33 API baseline) and is wired through a `PlayIntegrityVerifierInterface`, but the bound implementation (`NullPlayIntegrityVerifier`) is a placeholder that reports itself `"configured": false` and leaves the device's `integrity_status` untouched. Real verification needs a Google Cloud project with the Play Integrity API enabled, a service account, and the Android app's real package name — none of which exist yet (the Android app itself isn't built). **This needs you to provide those credentials before it can do anything real** — swapping in a real verifier behind the same interface is a small, isolated change once they exist.

All these are additive signals (`TRUSTED`/`SUSPICIOUS`/`REJECTED` on `activity_locations`, plus an `anomaly_reasons` JSON array) — none of it is exposed to the field user (docs section 20: "algoritma risk scoring tidak perlu ditampilkan ke user lapangan"); a future Web Admin would map these three statuses to "Verified / Needs Review / Rejected" (docs section 29.4/section 21) rather than getting its own separate confidence field.
