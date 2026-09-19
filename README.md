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
npm install
npm run build   # or `npm run dev` for the Vite dev server with hot reload
```

Web Admin is at http://localhost:8000 (login page). The seeder creates two dev accounts (password `password` for both):

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

### Offline Sync (Milestone H)

Room and WorkManager (docs section 21's local-first flow) are Android's job and live in `ama-android` once that repo exists — nothing to build here for those. The one piece of this milestone that *is* a backend contract (docs section 22: "Retry harus: aman; idempotent; tidak membuat activity ganda; tidak mengupload foto ganda. Gunakan idempotency key / client-generated UUID.") was a real gap in Milestones D/E/F: `POST /plans`, `POST /activities`, and `POST /activities/{id}/photos` were not safe to retry — a request that succeeded but whose response got lost (the exact scenario WorkManager exists to handle) would have created a duplicate plan/activity/photo on resend. Fixed, no new module needed (this hardens three existing endpoints rather than introducing a new concept):

- `POST /plans` and `POST /activities` now require `idempotency_key` (client-generated UUID). A retried request with the same key from the same creator returns the original record (`200`) instead of creating a new one (`201` the first time).
- `POST /activities/{id}/photos` dedupes by `(capture_session_id, sha256_hash)` — reuploading the identical bytes into the same session returns the existing photo (`200`) without writing a second object to storage.

### Web Admin / Web Monitoring (Milestone I)

First frontend work in this repo: Inertia.js + React + Tailwind, session-based auth (docs section 25.2), scoped to exactly what docs section 48 names for this milestone — activity list, map, evidence viewer, integrity review. (User Management and Master Data CRUD UI aren't in section 48's milestone list at all despite section 29 describing them; the backend APIs already exist from Milestones B/C — their UI isn't built yet and isn't implied by "Web Monitoring".)

```
GET  /login, POST /login, POST /logout        — session auth (Modules/Identity/.../WebAuthController)
GET  /dashboard                                — today's summary (docs section 29.1)
GET  /activities                               — filterable list + Leaflet/OpenStreetMap markers (docs section 29.4/29.5)
GET  /activities/{id}                          — evidence viewer + integrity review (photos, GPS, anomaly_reasons)
```

`/dashboard` and `/activities*` require the `activities.view` permission — same monitoring roles as the API (ADMIN/MANAGER/SUPERVISOR/SUPER_ADMIN); AGRONOMIST isn't meant to use Web Admin. Any active user can reach `/login` itself.

**MinIO photo URLs needed a second disk.** Evidence photos are stored via the app container's Docker-internal view of MinIO (`AWS_ENDPOINT=http://minio:9000`), but a presigned URL opened in the admin's own browser can't resolve that hostname. Added `s3_public` in `config/filesystems.php` — same bucket/credentials, but `AWS_ENDPOINT_PUBLIC` (defaults to `http://localhost:9000`, MinIO's published port) instead. Used only for `Storage::disk('s3_public')->temporaryUrl(...)` in the evidence viewer. In staging/production this collapses to a no-op (`AWS_ENDPOINT_PUBLIC` = `AWS_ENDPOINT`, both the real public S3/R2 endpoint).

Verified with a real headless-browser pass (login → dashboard → activities list with map markers → an activity's evidence viewer, confirming a MinIO-stored photo actually renders via the presigned URL → logout), not just `php artisan test` — this is the repo's first UI, and a green test suite doesn't catch a blank screen or a broken image. Caught and fixed one bug this way that the test suite's `actingAs()`-based tests didn't (they don't exercise real seeded roles): the agronomist filter dropdown queried `User::role('AGRONOMIST')`, which throws instead of returning empty when that role doesn't exist yet in a given environment.

### Web Admin: User Management (doc schedule "Part 12")

Until now the only way to create an account was the seeder. `/users` (docs section 29.2) adds list + search, create, edit, activate/deactivate and per-user device revoke, gated by `users.manage` (ADMIN/SUPER_ADMIN); the nav link only shows for holders of that permission.

```
GET  /users, /users/create, /users/{id}/edit
POST /users, PUT /users/{id}
POST /users/{id}/devices/{deviceId}/revoke
```

Rules worth knowing (all covered in `tests/Feature/Identity/WebUserManagementTest.php`):
- Users are never deleted, only deactivated — activities, plans and audit rows reference them. Deactivating also deletes their Sanctum tokens and revokes their refresh tokens, so a phone that is already logged in stops working immediately rather than at token expiry.
- Only a SUPER_ADMIN can assign or edit SUPER_ADMIN (an ADMIN could otherwise promote themselves past their own role).
- Nobody can deactivate or change the role of their own account from this screen.
- Create/update are written to `audit_logs`; the password never appears in the old/new values. Leaving the password blank on edit keeps the current one.

Verified in a headless browser against the real stack (create → search → edit → deactivate, no console errors, DB and audit rows checked), plus 9 feature tests; full suite 89/89 green.

**Still not built in Web Admin** (docs section 29): dashboard filters, a per-activity "sync status" column, and a notification/announcement screen. `phone`/`nip` have no format rules beyond uniqueness/length (OPEN QUESTION with the product owner).

### Web Admin: Master Data (doc schedule "Part 12", continued)

`/master-data/{activity-types|product-categories|products|positions|work-locations}` (docs section 29.3, `master_data.manage`): one tabbed page, add and edit rows (name, unique code, active flag; products also pick a category). **No delete on purpose** — rows are referenced by activities, plans and users, and the existing `DELETE /api/v1/master/*` hard-deletes; retire a value by turning Aktif off (mobile only fetches active ones). Every change is audited. Verified in a headless browser (add product, duplicate-code error shown inline, edit, deactivate) plus 5 tests.

Known sharp edge left alone: the existing `DELETE /api/v1/master/*` endpoints still hard-delete and will fail or orphan data for values already in use; nothing in the UI calls them.

### Web Admin: Reports (doc schedule "Part 12", continued)

`/reports` (docs section 29.6, `reports.view`): filters by date range / work location / agronomist / activity type / product, summary cards (activities, plans, realized, realization rate with cancelled plans excluded, integrity Verified / Needs Review / Rejected) and a CSV download of the same filtered rows. The filter and aggregation logic moved from `ReportController` into `Modules/Reports/Application/ActivityReport`, so the API and this page share one implementation.

Two fixes that came out of testing this:
- **The reports API failed whenever a date or creator/type filter was combined with the integrity breakdown** (`created_at` was ambiguous after the join to `activity_locations`); the summary endpoint had never been called with a filter in a test. Columns are now qualified.
- **CSV formula injection:** `location` is free text typed by field users; a value like `=HYPERLINK(...)` would execute when the export is opened in Excel. Cells starting with `=`, `+`, `-`, `@` (or tab/CR) are now prefixed with a single quote.

Verified in a headless browser (login, Laporan, date filter, CSV download) and by tests.

### Notifications & Reports (Milestone J)

**FCM push is honestly not functional yet — same pattern as Play Integrity (Milestone G).** `Modules/Notifications/` saves every notification to the database regardless (docs section 28: "disimpan dalam database agar notification center tetap memiliki history") and the API/triggers all work — only the actual push is a placeholder. `NullPushNotifier` logs what it would have sent and returns `false`, rather than faking success. Needs a Firebase project with Cloud Messaging enabled (+ a service account) before it can do anything real, which in turn needs `ama-android` to exist to register genuine `fcm_token`s — ask before assuming this is wired up.

```
GET  /api/v1/notifications              — own notifications, paginated
POST /api/v1/notifications/{id}/read
GET  /api/v1/reports/activities/summary — activities/plans counts, realization rate, integrity breakdown (reports.view)
GET  /api/v1/reports/activities/export  — CSV (docs section 44 Q14: format unresolved; CSV opens in Excel without picking/installing a PDF or xlsx-specific library before the actual desired format is confirmed)
```

Automatic triggers (docs section 28's examples), all best-effort — a failed/unconfigured push never fails the triggering action:
- `notifications:plan-reminders` (scheduled daily at 06:00, `routes/console.php`): `PLAN_TOMORROW`/`PLAN_TODAY`/`PLAN_OVERDUE`, each plan reminded at most once per type (a rerun or a caught-up missed day doesn't spam it again).
- Completing an activity (Evidence module): `ACTIVITY_COMPLETED` to its creator; `ACTIVITY_NEEDS_REVIEW` to every `activities.verify` holder if any of its locations came out of Milestone G's evaluation as anything other than `TRUSTED`.

Deliberately not built: a Web Admin UI for notifications or reports. Docs section 29 describes both as Web Admin features, but neither is in section 48's Milestone J description (just "FCM; notification center; reports" — read as the data/API layer, the same way "notification center" reads as Android's own notification inbox, not a Web Admin screen), and Milestone I was scoped the same way for User Management/Master Data. The APIs above are ready for either a future Web Admin page or the Android app to consume.

**Removed Laravel's built-in `Notifiable` trait from `User`.** It shipped unused on the model (Laravel's skeleton default) but its own `notifications()` method and its polymorphic `notifiable_type`/`notifiable_id` "notifications" table convention would have collided with this app's own `user_id`-based `notifications` table and `User::notifications()` relation — caught before it shipped as a bug, not after.

**Infra note for local dev:** OPcache is tuned in `docker/php/opcache-dev.ini` because the bind-mounted source (Windows host → WSL2 → container) makes per-file `mtime` stats slow enough to turn every request into a ~12s page load otherwise. First tried disabling revalidation entirely (`validate_timestamps=0`) — immediately regretted it: a `bootstrap/providers.php` edit (registering this milestone's new providers) silently kept running the old cached version until the container was restarted, which is a worse failure mode than the slow requests it fixed. Settled on `revalidate_freq=15` instead — same fast requests, but a code edit is picked up on the next request after at most ~15s, same as stock PHP just less eager about re-checking.

### Hardening (Milestone K)

Adds `Modules/Audit/` (docs section 30: "Sistem wajib memiliki audit log untuk aksi penting") — a plain injectable `AuditLogger`, not a full module layer, since every call site just records one row. Wired into the write paths where an admin/operator action changes something on someone else's behalf or reshapes shared reference data, per section 30's own example (an *edit*, not every creation of routine field data):

- All 5 Master Data resources' create/update/delete (`SimpleMasterDataController` — one wiring point covers Activity Types/Product Categories/Positions/Work Locations/Products, since Products inherits store/update/destroy from it).
- Plan updates, including status transitions (matches section 30's own "Old location = A, New location = B" example).
- Device revocation (see below).

**Completed a half-built security feature.** Milestone B built the *data* for device revocation (`devices.revoked_at`, and every check against it — login, evidence submission, re-registration all already refused a revoked device) but never exposed the actual admin action to set it (docs section 24: "Admin dapat melakukan revoke device"). Added:

```
GET  /api/v1/admin/devices           ?user_id=   (users.manage)
POST /api/v1/admin/devices/{id}/revoke            (users.manage)
```

**Rate limiting** (docs section 36: "Rate limit endpoint sensitif") on `POST /api/v1/auth/login` and `/auth/refresh` — 10 requests/minute, the obvious brute-force targets in the API.

**`DEPLOYMENT.md`**: the Ubuntu + native-nginx production deployment guide promised earlier in this project (Docker was always a local-dev choice, never an architectural requirement) — install steps, an nginx vhost (the same shape as `docker/nginx/default.conf` minus the Docker-DNS-resolver workaround, which doesn't apply outside Docker), systemd units for the queue worker, and cron for the scheduler.

Verified: full test suite (80 passing: 8 new Audit/device-revocation cases) and Pint pass; manually created master data and confirmed the resulting `audit_logs` row against the real Postgres-backed stack, and confirmed rate limiting kicks in (`429`) after repeated failed logins.
