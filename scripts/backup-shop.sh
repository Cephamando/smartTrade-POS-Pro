#!/usr/bin/env bash
# Complete shop backup: VSDC webapps (API keys), MySQL dump, MySQL volume, .env, and WAR.
# Run in WSL Ubuntu from any directory:
#   ~/smartTrade-POS-Pro/scripts/backup-shop.sh
# Optional: BACKUP_COPY_TO=/mnt/f/mando/smartTrade-backups
# If WSL cannot see that Windows letter, the script mounts it with sudo.
# Optional: BACKUP_STAMP=YYYYMMDD-HHMM
# Shop stays up. This script never runs docker compose stop/down.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if docker compose version >/dev/null 2>&1; then
    COMPOSE=(docker compose)
else
    COMPOSE=(docker-compose)
fi

STAMP="${BACKUP_STAMP:-$(date +%Y%m%d-%H%M)}"
DEST="$ROOT/backups/$STAMP"
VSDC_TGZ="$DEST/vsdc_webapps-${STAMP}.tgz"
DB_SQL="$DEST/db-${STAMP}.sql.gz"
DB_TGZ="$DEST/db_data-${STAMP}.tgz"

die() {
    echo "ERROR: $*" >&2
    exit 1
}

need_container() {
    docker inspect -f '{{.State.Running}}' "$1" 2>/dev/null | grep -q true \
        || die "Container $1 is not running. From $ROOT run: ${COMPOSE[*]} up -d"
}

load_env() {
    [ -f "$ROOT/.env" ] || return 0
    DB_NAME="$(grep -E '^DB_NAME=' "$ROOT/.env" | tail -n1 | cut -d= -f2- | tr -d '\r')"
    DB_USER="$(grep -E '^DB_USER=' "$ROOT/.env" | tail -n1 | cut -d= -f2- | tr -d '\r')"
    DB_PASS="$(grep -E '^DB_PASS=' "$ROOT/.env" | tail -n1 | cut -d= -f2- | tr -d '\r')"
    DB_ROOT_PASS="$(grep -E '^DB_ROOT_PASS=' "$ROOT/.env" | tail -n1 | cut -d= -f2- | tr -d '\r')"
}

echo "Shop backup $STAMP"
echo "Project: $ROOT"
need_container vsdc
need_container smartTradePOS_db
load_env
mkdir -p "$DEST"

echo "Packing VSDC webapps (the Initialize keys live here)…"
docker run --rm --volumes-from vsdc \
    -v "$DEST:/backup" alpine \
    tar czf "/backup/vsdc_webapps-${STAMP}.tgz" -C /usr/local/tomcat/webapps .

[ -s "$VSDC_TGZ" ] || die "VSDC archive is empty: $VSDC_TGZ"
# Process substitution avoids pipefail/SIGPIPE when grep -q exits early.
grep -q 'sandboxvsdc' < <(tar tzf "$VSDC_TGZ") \
    || die "VSDC archive does not contain sandboxvsdc. Do not use this backup."

echo "Dumping MySQL (consistent snapshot, shop stays up)…"
if [ -n "${DB_ROOT_PASS:-}" ]; then
    docker exec smartTradePOS_db mysqldump -uroot -p"$DB_ROOT_PASS" \
        --single-transaction --routines --events --triggers --hex-blob \
        --databases "${DB_NAME:-smartTradePOS_db}" \
        | gzip -c > "$DB_SQL"
elif [ -n "${DB_USER:-}" ] && [ -n "${DB_PASS:-}" ]; then
    docker exec smartTradePOS_db mysqldump -u"$DB_USER" -p"$DB_PASS" \
        --single-transaction --routines --events --triggers --hex-blob \
        --databases "${DB_NAME:-smartTradePOS_db}" \
        | gzip -c > "$DB_SQL"
else
    die "No DB_ROOT_PASS or DB_USER/DB_PASS in .env"
fi
[ -s "$DB_SQL" ] || die "MySQL dump is empty: $DB_SQL"
gzip -t "$DB_SQL" || die "MySQL dump is not a valid gzip file."

echo "Packing MySQL data directory as a second copy…"
docker run --rm --volumes-from smartTradePOS_db \
    -v "$DEST:/backup" alpine \
    tar czf "/backup/db_data-${STAMP}.tgz" -C /var/lib/mysql .
[ -s "$DB_TGZ" ] || die "Database volume archive is empty: $DB_TGZ"

if [ -f "$ROOT/.env" ]; then
    cp -a "$ROOT/.env" "$DEST/.env"
    echo "Copied .env"
else
    echo "WARNING: .env not found. The new PC will need the same ZRA serial."
fi

WAR_COUNT=0
if [ -d "$ROOT/api" ]; then
    while IFS= read -r -d '' war; do
        cp -a "$war" "$DEST/"
        WAR_COUNT=$((WAR_COUNT + 1))
    done < <(find "$ROOT/api" -type f -iname '*.war' -print0)
fi
if [ "$WAR_COUNT" -eq 0 ]; then
    echo "WARNING: no .war under api/. Keep a copy of the VSDC WAR with this backup."
else
    echo "Copied $WAR_COUNT WAR file(s)"
fi

{
    echo "smartTradePOS-Pro shop backup"
    echo "stamp=$STAMP"
    echo "created=$(date -Iseconds)"
    echo "host=$(hostname)"
    echo
    echo "files:"
    ls -lh "$DEST"
    echo
    echo "vsdc listing (first 20):"
    head -n 20 < <(tar tzf "$VSDC_TGZ")
    echo
    echo "Restore on a new PC (do not Initialize):"
    echo "  ./scripts/restore-shop.sh $STAMP --yes"
} > "$DEST/MANIFEST.txt"

# FAT/exFAT USB sticks reject Unix timestamps. Copy file contents only.
copy_tree() {
    local src="$1"
    local dst="$2"
    mkdir -p "$dst" || return 1
    if cp --help 2>/dev/null | grep -q -- '--no-preserve'; then
        cp -R --no-preserve=mode,ownership,timestamps "$src/." "$dst/"
    else
        cp -R "$src/." "$dst/"
    fi
}

ensure_windows_drive() {
    local letter="$1"
    local mountpoint="/mnt/${letter}"
    local win="${letter^^}:"

    if mountpoint -q "$mountpoint" 2>/dev/null; then
        return 0
    fi

    echo "WSL cannot see Windows drive ${letter^^}: at $mountpoint. Mounting…"
    echo "Enter your Ubuntu password if sudo asks."
    if ! sudo mkdir -p "$mountpoint"; then
        echo "Could not create $mountpoint."
        return 1
    fi
    if ! sudo mount -t drvfs "$win" "$mountpoint" \
        -o "metadata,uid=$(id -u),gid=$(id -g),umask=022"; then
        echo "Could not mount $win on $mountpoint."
        echo "Plug in the USB and confirm Windows shows it as ${letter^^}:."
        return 1
    fi
    echo "Mounted $win at $mountpoint."
}

copy_off_box() {
    local dest_root="$1"
    local letter mountpoint
    if [[ "$dest_root" =~ ^/mnt/([a-zA-Z])(/|$) ]]; then
        letter="${BASH_REMATCH[1],,}"
        mountpoint="/mnt/$letter"
        ensure_windows_drive "$letter" || return 1
        if [ ! -w "$mountpoint" ]; then
            echo "Mounted $mountpoint but it is not writable."
            return 1
        fi
    fi
    copy_tree "$DEST" "$dest_root/$STAMP" || return 1
    echo "Also copied to $dest_root/$STAMP"
}

if [ -n "${BACKUP_COPY_TO:-}" ]; then
    if ! copy_off_box "$BACKUP_COPY_TO"; then
        echo "WARNING: extra copy to $BACKUP_COPY_TO failed. The backup on disk is still good: $DEST"
    fi
fi

echo
echo "Backup ready: $DEST"
echo "Take this whole folder off the PC (USB, another computer, or cloud):"
echo "  vsdc_webapps-${STAMP}.tgz   ← cannot be re-downloaded from ZRA"
echo "  db-${STAMP}.sql.gz          ← preferred database restore"
echo "  db_data-${STAMP}.tgz        ← raw MySQL files (spare copy)"
echo "  .env"
echo "  any .war copied from api/"
echo
echo "Example copy to a USB already mounted as F:"
echo "  mkdir -p /mnt/f/mando/smartTrade-backups"
echo "  cp -R --no-preserve=mode,ownership,timestamps \"$DEST\" /mnt/f/mando/smartTrade-backups/"
echo
echo "Restore later:"
echo "  ./scripts/restore-shop.sh $STAMP --yes"
