#!/usr/bin/env bash
#
# Daily backup (installed into /etc/cron.d/ama by deploy/install.sh):
#   - PostgreSQL dump (custom format), kept KEEP_DAYS days
#   - evidence photos mirrored with rsync (never deletes: a photo removed by
#     mistake in the app stays recoverable here)
#   - the .env (holds APP_KEY; a restore needs it), last 5 copies
#
# This is a copy on the SAME server: it protects against mistakes and a bad
# deploy, NOT against losing the server. Copy /var/backups/ama off-site
# (another server / object storage) before real data depends on it.
#
set -Eeuo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/ama}"
KEEP_DAYS="${KEEP_DAYS:-14}"
DB_NAME="${DB_NAME:-ama}"
STAMP="$(date +%Y%m%d-%H%M%S)"

umask 077
mkdir -p "$BACKUP_DIR/db" "$BACKUP_DIR/photos" "$BACKUP_DIR/env"

# --output-free: written by root, the dump itself is produced by the postgres user
(cd /tmp && sudo -u postgres pg_dump -Fc "$DB_NAME") > "$BACKUP_DIR/db/${DB_NAME}-${STAMP}.dump"
[ -s "$BACKUP_DIR/db/${DB_NAME}-${STAMP}.dump" ] || { echo "Dump kosong!" >&2; exit 1; }
find "$BACKUP_DIR/db" -name "${DB_NAME}-*.dump" -mtime +"$KEEP_DAYS" -delete

if [ -d "$APP_DIR/storage/app/private" ]; then
  if command -v rsync >/dev/null; then
    rsync -a "$APP_DIR/storage/app/private/" "$BACKUP_DIR/photos/"
  else
    cp -au "$APP_DIR/storage/app/private/." "$BACKUP_DIR/photos/"
  fi
fi

cp "$APP_DIR/.env" "$BACKUP_DIR/env/env-${STAMP}"
ls -1t "$BACKUP_DIR"/env/env-* | tail -n +6 | xargs -r rm -f

echo "$(date -Is) backup OK: $(du -sh "$BACKUP_DIR" | cut -f1) total"
