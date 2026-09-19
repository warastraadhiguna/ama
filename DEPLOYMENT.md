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
docs section 5.5). MinIO was only ever the local Docker Compose stand-in.
`AWS_ENDPOINT_PUBLIC` should equal `AWS_ENDPOINT` here (both the same
public endpoint) — the split only exists to work around MinIO's
Docker-internal hostname in local dev.

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
