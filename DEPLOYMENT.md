# Deploying to a plain Ubuntu server (no Docker)

Docker Compose (`docker-compose.yml`) is a local-development choice only —
nothing in this app depends on it. This is the native-install path for a
real Ubuntu server, matching docs section 38's deployment baseline
(Nginx -> Laravel -> PostgreSQL/PostGIS + Redis + S3-compatible storage,
plus a queue worker and the scheduler).

## 1. Prerequisites (Ubuntu 22.04/24.04 LTS)

```bash
sudo apt update
sudo apt install -y nginx postgresql-16 postgresql-16-postgis-3 redis-server \
    php8.5-fpm php8.5-pgsql php8.5-redis php8.5-gd php8.5-zip php8.5-mbstring \
    php8.5-xml php8.5-bcmath php8.5-curl composer nodejs npm certbot python3-certbot-nginx
```

(If Ubuntu's default repos don't carry PHP 8.5 yet, add [ondrej/php](https://launchpad.net/~ondrej/+archive/ubuntu/php) first.)

## 2. Database and object storage

```bash
sudo -u postgres createuser ama --pwprompt
sudo -u postgres createdb ama -O ama
sudo -u postgres psql -d ama -c 'CREATE EXTENSION postgis;'
```

Object storage: point `AWS_*` at a real bucket (AWS S3 or Cloudflare R2 —
docs section 5.5). MinIO was only ever the local Docker Compose stand-in —
and as of 2026-09-23, MinIO's standalone server/client *binary* downloads
are discontinued/archived upstream (both dl.min.io and GitHub Releases
404/410), so it can no longer be installed on a plain server this way at
all (Docker images are a separate channel and still work — irrelevant
here, since this guide is the no-Docker path). `AWS_ENDPOINT_PUBLIC`
should equal `AWS_ENDPOINT` here (both the same public endpoint) — the
split only exists to work around MinIO's Docker-internal hostname in
local dev.

**No object storage account yet? Use `FILESYSTEM_DISK=local` instead.**
Set `FILESYSTEM_DISK=local` in `.env` and skip the `AWS_*` values entirely —
photos are stored under `storage/app/private` on the server's own disk.
The Web Admin evidence viewer's photo URLs work unchanged (see
`AppServiceProvider`'s `buildTemporaryUrlsUsing()` and the
`evidence-photos.show` route in `routes/web.php`: a signed, time-limited
link standing in for S3's `temporaryUrl()`, same trust model). Switching
to real S3/R2 later needs no code change, just filling in the `AWS_*`
values — old photos already on local disk would need a manual copy to the
bucket, though. **This is only as good as the `chown` step below** — the
photos are written and later read by whichever user php-fpm runs as
(normally `www-data`); if that step is skipped, or you hand-place a file
as a different user (e.g. root over SSH), reads 404 with no other symptom.
This cost real time to track down while building it — the fix was
literally just getting the ownership consistent.

## 3. Deploy the code

```bash
cd /var/www
sudo git clone <repo> ama-backend && cd ama-backend
cp .env.example .env   # then fill in production values — see below
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force              # roles, permissions and starter master data only — no test accounts outside local/testing
php artisan ama:create-admin you@company.com --name="Your Name"   # prompts for the password; the first login
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

`.env` production values to set:
- `APP_ENV=production`, `APP_DEBUG=false`
- `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — the Postgres instance from step 2
- `REDIS_HOST` — `127.0.0.1` if Redis is on the same box
- `AWS_*` — real S3/R2 bucket + credentials (see above)
- `INTEGRITY_MOCK_LOCATION_POLICY`, `INTEGRITY_MAX_ACCEPTABLE_ACCURACY_METERS`, etc. — tune per `config/integrity.php` once there's field data to base thresholds on

## 4. Nginx

Same shape as `docker/nginx/default.conf`, minus the Docker-DNS resolver
trick (`resolver 127.0.0.11` / `$app_upstream` variable) — that exists
purely to work around Docker container IPs changing on rebuild, which
doesn't apply here since php-fpm is a local Unix socket, not a container
that gets recreated.

```nginx
server {
    listen 80;
    server_name ama.example.com;
    root /var/www/ama-backend/public;
    index index.php;

    client_max_body_size 16m;   # UploadPhotoRequest allows 15MB; nginx defaults to 1MB

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/ama-backend /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d ama.example.com   # docs section 36: HTTPS wajib
```

**Photo upload limits.** Evidence photos are validated up to 15MB, but PHP and
nginx reject far smaller bodies by default (PHP 2MB upload / 8MB post, nginx
1MB), so a real phone-camera photo fails with 413 before Laravel sees it.
Besides `client_max_body_size 16m;` above, set in the php-fpm `php.ini` (or
a `conf.d` drop-in):

```ini
upload_max_filesize = 16M
post_max_size = 20M
```

then reload php-fpm. The Android app also downsizes photos (~430KB), so this
is headroom, not the normal case.

## Security notes (production)

- **HTTPS is required.** `APP_ENV=production` makes the session cookie `Secure`
  by default and turns on HSTS (only sent over HTTPS). Set `SESSION_SECURE_COOKIE`
  explicitly if you terminate TLS in front of PHP and need to override.
- **Rate limits:** Web Admin login 5/min per email+IP (30/min per IP); API login
  and refresh 10/min per IP; every other API call 120/min per user. Uses the
  cache store, so `CACHE_STORE=redis` in production keeps them shared across workers.
- **Response headers** (`nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`,
  `Permissions-Policy`) are set by the app. There is deliberately **no
  Content-Security-Policy** yet — it must be written against the real hosts
  (map tiles, MinIO/S3 photo URLs) and tested; do not add one blindly.
- **First admin:** `php artisan ama:create-admin` (see step 3). The seeder creates
  no accounts outside local/testing.
- Not done: 2FA for admins, password-reset flow, account lockout notifications,
  dependency/CVE scanning in CI. These are worth deciding before go-live.

## 5. Queue worker + scheduler (systemd)

`/etc/systemd/system/ama-queue.service`:

```ini
[Unit]
Description=AMA queue worker
After=network.target redis-server.service postgresql.service

[Service]
User=www-data
WorkingDirectory=/var/www/ama-backend
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now ama-queue
```

Scheduler (docs section 28's `notifications:plan-reminders` and anything
future) — a single cron entry runs Laravel's own scheduler, which decides
what's actually due:

```
* * * * * www-data /usr/bin/php /var/www/ama-backend/artisan schedule:run >> /dev/null 2>&1
```

## 6. Releasing an update

```bash
cd /var/www/ama-backend
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
sudo systemctl restart php8.5-fpm ama-queue
```

## What still needs real credentials before it's fully functional here

Both were left as explicit, honest placeholders rather than faked (see
`README.md`) — nothing above unblocks them, they need actual external
setup:

- **Play Integrity** (`Modules/Integrity`): a Google Cloud project with
  the Play Integrity API enabled + a service account, plus the Android
  app's real package name once `ama-android` exists.
- **FCM push** (`Modules/Notifications`): a Firebase project with Cloud
  Messaging enabled + a service account, same Android-app dependency.
