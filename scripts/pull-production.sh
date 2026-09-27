#!/usr/bin/env bash
# Replace the LOCAL database with a copy of production (production is the source of truth,
# see docs/adr/0001). Needs your own SSH access to Infomaniak; set in your local .env:
#   PRODUCTION_SSH=user@xxxx.ftp.infomaniak.com   (or an ~/.ssh/config alias)
#   PRODUCTION_PATH=/home/clients/<hash>/sites/<domain>
set -euo pipefail
cd "$(dirname "$0")/.."

env_value() { grep -E "^$1=" "${2:-.env}" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }

SSH_TARGET=$(env_value PRODUCTION_SSH)
REMOTE_PATH=$(env_value PRODUCTION_PATH)
: "${SSH_TARGET:?Set PRODUCTION_SSH in .env}" "${REMOTE_PATH:?Set PRODUCTION_PATH in .env}"

LOCAL_DB=$(env_value DB_DATABASE)
LOCAL_HOST=$(env_value DB_HOST)
LOCAL_PORT=$(env_value DB_PORT)
LOCAL_USER=$(env_value DB_USERNAME)
export MYSQL_PWD=$(env_value DB_PASSWORD)

read -r -p "This REPLACES the local database '$LOCAL_DB' with production. Continue? [y/N] " answer
[[ "$answer" == [yY] ]] || exit 1

mkdir -p storage/app/private/db-backups
stamp=$(date +%Y%m%d-%H%M%S)
backup="storage/app/private/db-backups/local-$stamp.sql.gz"
echo "Backing up local database to $backup"
mysqldump -h "$LOCAL_HOST" -P "$LOCAL_PORT" -u "$LOCAL_USER" --single-transaction "$LOCAL_DB" | gzip > "$backup"

dump=$(mktemp -t budget-prod.XXXXXX)
trap 'rm -f "$dump"' EXIT
echo "Dumping production over SSH…"
# Credentials are read from the server's .env and passed via MYSQL_PWD (not visible in ps).
ssh "$SSH_TARGET" "cd '$REMOTE_PATH' && v() { grep -E \"^\$1=\" .env | tail -1 | cut -d= -f2- | sed -e 's/^\"//' -e 's/\"\$//'; } && \
  MYSQL_PWD=\"\$(v DB_PASSWORD)\" mysqldump -h \"\$(v DB_HOST)\" -u \"\$(v DB_USERNAME)\" \
  --single-transaction --no-tablespaces \"\$(v DB_DATABASE)\" | gzip" > "$dump"

echo "Importing into local '$LOCAL_DB'…"
gunzip -c "$dump" | mysql -h "$LOCAL_HOST" -P "$LOCAL_PORT" -u "$LOCAL_USER" "$LOCAL_DB"
php artisan cache:clear >/dev/null
echo "Done. Previous local data kept in $backup"
