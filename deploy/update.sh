#!/usr/bin/env bash
#
# Release an update on the server:   cd /var/www/ama/html && bash deploy/update.sh
# Same care as install.sh: explicit php8.4, no global restarts, stops on the first error.
#
set -Eeuo pipefail
trap 'echo; printf "\033[1;31mGAGAL di baris %s (perintah: %s)\033[0m\n" "$LINENO" "$BASH_COMMAND" >&2; echo "Update berhenti. Kirim output ke Claude — jangan ulangi berkali-kali." >&2' ERR

PHP="${PHP:-/usr/bin/php8.4}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.4-fpm}"
export COMPOSER_ALLOW_SUPERUSER=1
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"

say() { printf '\n\033[1;32m==> %s\033[0m\n' "$*"; }

[ "$(id -u)" -eq 0 ] || { echo "Jalankan sebagai root." >&2; exit 1; }

# a database copy right before migrating: cheap insurance for a rollback
say "Cadangan database sebelum update"
bash deploy/backup.sh

say "git pull"
git pull --ff-only

say "composer install"
"$PHP" "$(command -v composer)" install --no-dev --optimize-autoloader --no-interaction --prefer-dist

say "Build frontend"
npm ci --no-audit --no-fund
npm run build

say "Migrasi"
"$PHP" artisan migrate --force

say "Cache + izin file"
"$PHP" artisan config:cache
"$PHP" artisan view:cache
"$PHP" artisan event:cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

say "Muat ulang worker dan php-fpm (graceful)"
systemctl restart ama-queue
# reload (not restart): new PHP files are picked up without dropping the other sites' requests
systemctl reload "$PHP_FPM_SERVICE"

say "Selesai — versi sekarang: $(git log --oneline -1)"
