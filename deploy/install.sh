#!/usr/bin/env bash
#
# First-time install of the AMA backend + Web Admin on an Ubuntu server that
# ALREADY has nginx, php-fpm, PostgreSQL+PostGIS and Redis (see
# DEPLOYMENT.md steps 1-2). Written for a SHARED, LIVE server: it never edits
# an existing nginx site, never restarts anything global, and stops at the
# first surprise instead of pushing on.
#
#   git clone https://github.com/warastraadhiguna/ama.git /var/www/ama/html
#   cd /var/www/ama/html && bash deploy/install.sh
#
# Safe to re-run: an existing .env, vhost, admin user etc. are left alone.
#
set -Eeuo pipefail

trap 'echo; printf "\033[1;31mGAGAL di baris %s (perintah: %s)\033[0m\n" "$LINENO" "$BASH_COMMAND" >&2; echo "Tidak ada langkah berikutnya yang dijalankan. Kirim seluruh output di atas ke Claude." >&2' ERR

# ---- settings (override with env vars if needed) ---------------------------
APP_DOMAIN="${APP_DOMAIN:-ama.wan-client.com}"
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP="${PHP:-/usr/bin/php8.4}"                       # explicit: this server runs 5.6 ... 8.4 side by side
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.4-fpm}"
PHP_FPM_SOCK="${PHP_FPM_SOCK:-/run/php/php8.4-fpm.sock}"
DB_NAME="${DB_NAME:-ama}"
DB_USER="${DB_USER:-ama}"
OTHER_SITE_CHECK="${OTHER_SITE_CHECK:-fitbull.id}"  # an existing site we re-test at the end
export COMPOSER_ALLOW_SUPERUSER=1

say()  { printf '\n\033[1;32m==> %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m!! %s\033[0m\n' "$*"; }
die()  { printf '\n\033[1;31mBERHENTI: %s\033[0m\n' "$*" >&2; exit 1; }
pgsu() { (cd /tmp && sudo -u postgres "$@"); }

cd "$APP_DIR"
# chmod +x on the scripts below would otherwise show up as local changes and make `git pull` fuss
git config core.fileMode false 2>/dev/null || true

# ---- 0. preflight: look before touching anything ---------------------------
say "0/9  Pengecekan awal (belum mengubah apa pun)"

[ "$(id -u)" -eq 0 ] || die "Jalankan sebagai root (su, lalu ulangi)."
[ -f artisan ] && [ -f deploy/nginx-vhost.conf.template ] || die "Jalankan dari dalam folder repo AMA yang sudah di-clone."
grep -q "evidence-photos" routes/web.php \
  || die "Kode di server belum yang terbaru (routes/web.php tidak punya evidence-photos). Di komputer Anda jalankan 'git push', lalu di sini 'git pull'."

[ -x "$PHP" ] || die "$PHP tidak ditemukan."
"$PHP" -r 'exit(PHP_VERSION_ID >= 80401 ? 0 : 1);' || die "PHP terlalu lama: $("$PHP" -v | head -1). Butuh 8.4.1+."
mods="$("$PHP" -m)"
for ext in pdo_pgsql pgsql redis gd zip mbstring xml bcmath curl fileinfo; do
  grep -qix "$ext" <<<"$mods" || die "Ekstensi PHP '$ext' belum ada di $PHP."
done
echo "PHP     : $("$PHP" -v | head -1)"

COMPOSER_BIN="$(command -v composer || true)"
[ -n "$COMPOSER_BIN" ] || die "composer tidak ditemukan."
# we run it as "php8.4 composer": that only works if it is the phar, not a shell wrapper
head -c 200 "$COMPOSER_BIN" | head -1 | grep -q 'php' || die "$COMPOSER_BIN bukan phar composer (baris pertama: $(head -1 "$COMPOSER_BIN"))."
echo "Composer: $("$PHP" "$COMPOSER_BIN" --version 2>/dev/null | head -1)"
command -v node >/dev/null && command -v npm >/dev/null || die "node/npm tidak ditemukan."
NODE_MAJOR="$(node -p 'process.versions.node.split(".")[0]')"
[ "$NODE_MAJOR" -ge 20 ] || die "Node $(node -v) terlalu lama (butuh 20+)."
echo "Node    : $(node -v) / npm $(npm -v)"

[ "$(pgsu psql -tAc "select 1 from pg_database where datname='${DB_NAME}'")" = "1" ] || die "Database '${DB_NAME}' belum ada (Langkah 3)."
[ "$(pgsu psql -d "$DB_NAME" -tAc "select 1 from pg_extension where extname='postgis'")" = "1" ] || die "PostGIS belum aktif di database '${DB_NAME}'."
echo "Postgres: database '${DB_NAME}' + PostGIS OK"

[ "$(redis-cli ping 2>/dev/null)" = "PONG" ] || die "Redis tidak menjawab PONG."
echo "Redis   : OK"

[ -S "$PHP_FPM_SOCK" ] || die "Socket $PHP_FPM_SOCK tidak ada (php-fpm belum jalan?)."

nginx -t >/dev/null 2>&1 || { nginx -t || true; die "Konfigurasi nginx SUDAH bermasalah sebelum kita menyentuhnya — perbaiki itu dulu, jangan lanjut."; }
echo "Nginx   : konfigurasi sekarang valid"

# refuse to clash with another site that already claims this domain
escaped="${APP_DOMAIN//./\\.}"
clash="$(grep -RlE "server_name[^;]*[[:space:]]${escaped}([[:space:];])" /etc/nginx/sites-enabled/ /etc/nginx/conf.d/ 2>/dev/null \
         | grep -vxF "/etc/nginx/sites-enabled/${APP_DOMAIN}" || true)"
[ -z "$clash" ] || die "Domain ${APP_DOMAIN} sudah dipakai di nginx oleh: ${clash}"

avail_kb="$(df --output=avail -k "$APP_DIR" | tail -1 | tr -d ' ')"
[ "$avail_kb" -gt 3000000 ] || die "Ruang disk kurang dari 3 GB."
echo "Disk    : $((avail_kb / 1024 / 1024)) GB kosong"
echo "Memori  : $(free -m | awk '/^Mem:/ {print $7}') MB tersedia"

# ---- ask everything now, so the long part runs unattended ------------------
say "Data untuk admin pertama"
read -r -p "Email admin pertama (juga untuk notifikasi sertifikat HTTPS): " ADMIN_EMAIL
[[ "$ADMIN_EMAIL" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]] || die "Format email tidak valid."
read -r -p "Nama admin pertama: " ADMIN_NAME
[ -n "$ADMIN_NAME" ] || die "Nama tidak boleh kosong."
read -r -p "Nama perusahaan klien untuk halaman login (boleh kosong, bisa diubah nanti di .env): " COMPANY_NAME
# drop characters that would break the double-quoted .env line
COMPANY_NAME="$(printf '%s' "$COMPANY_NAME" | sed 's/["$`\]//g')"

# ---- 1. .env -----------------------------------------------------------------
say "1/9  File .env"
if [ -f .env ]; then
  warn ".env sudah ada — dipakai apa adanya."
else
  DB_PASS="$(openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | cut -c1-32)"
  pgsu psql -v ON_ERROR_STOP=1 -q -c "ALTER USER ${DB_USER} WITH PASSWORD '${DB_PASS}';"
  PGPASSWORD="$DB_PASS" psql -h 127.0.0.1 -U "$DB_USER" -d "$DB_NAME" -tAc "select 1" | grep -q 1 \
    || die "Login database '${DB_USER}' lewat password gagal (cek pg_hba.conf)."
  cat > .env <<EOF
APP_NAME="Agro Marketing App"
APP_COMPANY_NAME="${COMPANY_NAME}"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://${APP_DOMAIN}

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD='${DB_PASS}'

SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
CACHE_STORE=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_FROM_ADDRESS="no-reply@${APP_DOMAIN}"
MAIL_FROM_NAME="\${APP_NAME}"

VITE_APP_NAME="\${APP_NAME}"
EOF
  chown root:www-data .env
  chmod 640 .env
  echo "Password database dibuat acak dan langsung ditulis ke .env (tidak ditampilkan)."
fi

# ---- 2. PHP dependencies ------------------------------------------------------
say "2/9  composer install (beberapa menit)"
"$PHP" "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# ---- 3. frontend --------------------------------------------------------------
say "3/9  Build frontend (npm ci + vite build, beberapa menit)"
npm ci --no-audit --no-fund
npm run build
[ -f public/build/manifest.json ] || die "Build frontend tidak menghasilkan public/build/manifest.json."

# ---- 4. app key, migrations, seed --------------------------------------------
say "4/9  Kunci aplikasi, migrasi, data awal"
grep -q '^APP_KEY=.\+' .env || "$PHP" artisan key:generate --force
"$PHP" artisan migrate --force
"$PHP" artisan db:seed --force      # roles, permissions, master data only — no test accounts outside local/testing

# ---- 5. first admin -----------------------------------------------------------
say "5/9  Akun admin pertama"
users="$(pgsu psql -d "$DB_NAME" -tAc "select count(*) from users")"
if [ "$users" = "0" ]; then
  echo "Anda akan diminta mengetik password (tidak terlihat) dua kali."
  for attempt in 1 2 3; do
    if "$PHP" artisan ama:create-admin "$ADMIN_EMAIL" --name="$ADMIN_NAME"; then break; fi
    warn "Belum berhasil (percobaan $attempt/3) — ulangi."
  done
  [ "$(pgsu psql -d "$DB_NAME" -tAc "select count(*) from users")" != "0" ] || die "Admin belum terbentuk."
else
  warn "Sudah ada $users pengguna — tidak membuat admin baru."
fi

# ---- 6. caches + permissions --------------------------------------------------
say "6/9  Cache dan izin file"
mkdir -p storage/app/private storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
# no route:cache on purpose: routes/web.php has closure routes, which cannot be cached.
"$PHP" artisan config:cache
"$PHP" artisan view:cache
"$PHP" artisan event:cache
# Everything above ran as root; php-fpm runs as www-data. Photos are written AND read
# by www-data — root-owned files here make photos 404 with no other symptom.
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

# ---- 7. nginx vhost (new file only; existing sites untouched) ----------------
say "7/9  Nginx: situs baru ${APP_DOMAIN}"
VHOST="/etc/nginx/sites-available/${APP_DOMAIN}"
ENABLED="/etc/nginx/sites-enabled/${APP_DOMAIN}"
BACKUP="/root/nginx-backup-$(date +%Y%m%d-%H%M%S).tar.gz"
tar czf "$BACKUP" -C / etc/nginx && echo "Cadangan konfigurasi nginx: $BACKUP"

if [ -e "$VHOST" ]; then
  warn "$VHOST sudah ada — tidak ditimpa."
else
  sed -e "s|__DOMAIN__|${APP_DOMAIN}|g" \
      -e "s|__APP_DIR__|${APP_DIR}|g" \
      -e "s|__PHP_FPM_SOCK__|${PHP_FPM_SOCK}|g" \
      deploy/nginx-vhost.conf.template > "$VHOST"
fi
ln -sfn "$VHOST" "$ENABLED"
if nginx -t; then
  systemctl reload nginx        # graceful: existing connections keep being served
else
  rm -f "$ENABLED"              # take our site back out so the other sites are untouched
  nginx -t || true
  die "Konfigurasi nginx baru ditolak. Situs AMA dicabut lagi; situs lain tidak terpengaruh (nginx belum di-reload)."
fi

# ---- 8. HTTPS -----------------------------------------------------------------
say "8/9  HTTPS (Let's Encrypt)"
SERVER_IP="$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="src") print $(i+1)}' | head -1 || true)"
DNS_IP="$(getent ahostsv4 "$APP_DOMAIN" 2>/dev/null | awk '{print $1; exit}' || true)"
echo "IP server : ${SERVER_IP:-?}"
echo "DNS ${APP_DOMAIN} -> ${DNS_IP:-belum ada}"
CERTBOT_CMD="certbot --nginx -d ${APP_DOMAIN} --agree-tos --no-eff-email -m ${ADMIN_EMAIL} --redirect"
if [ -n "$DNS_IP" ] && [ "$DNS_IP" = "$SERVER_IP" ]; then
  if command -v certbot >/dev/null; then
    certbot --nginx -d "$APP_DOMAIN" --non-interactive --agree-tos --no-eff-email -m "$ADMIN_EMAIL" --redirect \
      || warn "certbot gagal. Situs tetap jalan lewat HTTP. Ulangi nanti: $CERTBOT_CMD"
  else
    warn "certbot belum terpasang. Pasang: apt install -y certbot python3-certbot-nginx, lalu: $CERTBOT_CMD"
  fi
else
  warn "DNS ${APP_DOMAIN} belum mengarah ke server ini, jadi HTTPS dilewati (login Web Admin baru bisa setelah HTTPS aktif: cookie sesi di produksi wajib HTTPS)."
  warn "Setelah record A ${APP_DOMAIN} -> ${SERVER_IP:-IP-server} aktif, jalankan:  $CERTBOT_CMD"
fi

# ---- 9. background worker, scheduler, backups ----------------------------------
say "9/9  Queue worker, scheduler, backup harian"
sed -e "s|__APP_DIR__|${APP_DIR}|g" -e "s|__PHP__|${PHP}|g" deploy/ama-queue.service.template > /etc/systemd/system/ama-queue.service
systemctl daemon-reload
systemctl enable --now ama-queue
sleep 3

chmod +x deploy/backup.sh 2>/dev/null || true
cat > /etc/cron.d/ama <<EOF
# AMA — installed by deploy/install.sh
* * * * * www-data ${PHP} ${APP_DIR}/artisan schedule:run >> /dev/null 2>&1
30 2 * * * root ${APP_DIR}/deploy/backup.sh >> /var/log/ama-backup.log 2>&1
EOF
chmod 644 /etc/cron.d/ama

# ---- verification ---------------------------------------------------------------
say "Pemeriksaan akhir"
code() { curl -s -o /dev/null -m 30 -w '%{http_code}' -H "Host: $1" "http://127.0.0.1$2" || true; }
echo "AMA  /login  (lewat nginx): HTTP $(code "$APP_DOMAIN" /login)   [harus 200 atau 301 jika HTTPS aktif]"
echo "AMA  /up     (health)     : HTTP $(code "$APP_DOMAIN" /up)"
echo "Situs lain (${OTHER_SITE_CHECK})   : HTTP $(code "$OTHER_SITE_CHECK" /)   [harus sama seperti sebelum instalasi, mis. 200/301/302]"
echo "Queue worker              : $(systemctl is-active ama-queue)"
if ss -ltn 2>/dev/null | awk '$4 ~ /:(5432|6379)$/ && $4 !~ /^(127\.0\.0\.1|\[::1\]|::1)/ {found=1} END {exit !found}'; then
  warn "Postgres/Redis mendengarkan di alamat selain localhost — periksa firewall/konfigurasi!"
else
  echo "Postgres & Redis          : hanya localhost (aman)"
fi

cat <<EOF

$(printf '\033[1;32m')Selesai.$(printf '\033[0m')
  Web Admin : https://${APP_DOMAIN}   (login dengan ${ADMIN_EMAIL})
  Log aplikasi   : ${APP_DIR}/storage/logs/
  Cadangan harian: /var/backups/ama  (mulai 02:30)
  Update nanti   : cd ${APP_DIR} && bash deploy/update.sh
EOF
