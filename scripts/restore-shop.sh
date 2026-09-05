#!/usr/bin/env bash
# Restore shop from backups/$STAMP.
# Safe check (does not touch this app):
#   ./scripts/restore-shop.sh YYYYMMDD-HHMM
#   ./scripts/restore-shop.sh YYYYMMDD-HHMM --dry-run
# Live restore (overwrites VSDC keys and the database):
#   ./scripts/restore-shop.sh YYYYMMDD-HHMM --yes
# After a live restore: do not Initialize. Use Test connection only.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if docker compose version >/dev/null 2>&1; then
    COMPOSE=(docker compose)
else
    COMPOSE=(docker-compose)
fi

die() {
    echo "ERROR: $*" >&2
    exit 1
}

usage() {
    die "Usage: ./scripts/restore-shop.sh YYYYMMDD-HHMM [--dry-run|--yes]"
}

[ "${1:-}" != "" ] || usage
STAMP="$1"
MODE="${2:-"--dry-run"}"
case "$MODE" in
    --dry-run|--yes) ;;
    *) usage ;;
esac

SRC="$ROOT/backups/$STAMP"
VSDC_TGZ="$SRC/vsdc_webapps-${STAMP}.tgz"
DB_SQL="$SRC/db-${STAMP}.sql.gz"
DB_TGZ="$SRC/db_data-${STAMP}.tgz"

[ -d "$SRC" ] || die "Backup folder not found: $SRC"
[ -s "$VSDC_TGZ" ] || die "Missing VSDC archive: $VSDC_TGZ"
if [ ! -s "$DB_SQL" ] && [ ! -s "$DB_TGZ" ]; then
    die "Need $DB_SQL or $DB_TGZ"
fi

dry_run() {
    echo "Dry run — nothing will be written to vsdc or MySQL."
    echo "Backup: $SRC"
    echo
    ls -lh "$SRC"
    echo

    echo "Checking VSDC archive…"
    gzip -t "$VSDC_TGZ" || die "VSDC archive is not valid gzip."
    grep -q 'sandboxvsdc' < <(tar tzf "$VSDC_TGZ") \
        || die "VSDC archive does not contain sandboxvsdc."
    echo "  gzip ok, sandboxvsdc present"

    echo "Extracting VSDC archive into a throwaway folder…"
    local tmp
    tmp="$(mktemp -d /tmp/restore-shop-test-XXXXXX)"
    tar xzf "$VSDC_TGZ" -C "$tmp"
    if [ ! -d "$tmp/sandboxvsdc" ] && [ ! -d "$tmp/./sandboxvsdc" ]; then
        rm -rf "$tmp"
        die "Extracted tree has no sandboxvsdc directory."
    fi
    echo "  extract ok ($(du -sh "$tmp" | cut -f1))"
    rm -rf "$tmp"

    if [ -s "$DB_SQL" ]; then
        echo "Checking MySQL dump…"
        gzip -t "$DB_SQL" || die "MySQL dump is not valid gzip."
        grep -E -q 'CREATE DATABASE|INSERT INTO' < <(gunzip -c "$DB_SQL") \
            || die "MySQL dump does not look like a mysqldump."
        echo "  gzip ok, dump contains CREATE/INSERT"
    fi

    if [ -s "$DB_TGZ" ]; then
        echo "Checking MySQL volume archive…"
        gzip -t "$DB_TGZ" || die "MySQL volume archive is not valid gzip."
        grep -E -q 'ibdata|mysql/' < <(tar tzf "$DB_TGZ") \
            || die "MySQL volume archive does not look like a datadir."
        echo "  gzip ok, datadir files present"
    fi

    if [ -f "$SRC/.env" ]; then
        echo "  .env present ($(wc -c < "$SRC/.env") bytes)"
    else
        echo "  WARNING: .env missing from this backup folder"
    fi

    echo
    echo "Dry run passed. This app was not changed."
    echo "A live restore (overwrites this shop) would be:"
    echo "  ./scripts/restore-shop.sh $STAMP --yes"
}

if [ "$MODE" != "--yes" ]; then
    dry_run
    exit 0
fi

load_env() {
    [ -f "$ROOT/.env" ] || die "Missing $ROOT/.env"
    DB_NAME="$(grep -E '^DB_NAME=' "$ROOT/.env" | tail -n1 | cut -d= -f2- | tr -d '\r')"
    DB_USER="$(grep -E '^DB_USER=' "$ROOT/.env" | tail -n1 | cut -d= -f2- | tr -d '\r')"
    DB_PASS="$(grep -E '^DB_PASS=' "$ROOT/.env" | tail -n1 | cut -d= -f2- | tr -d '\r')"
    DB_ROOT_PASS="$(grep -E '^DB_ROOT_PASS=' "$ROOT/.env" | tail -n1 | cut -d= -f2- | tr -d '\r')"
}

mysql_client() {
    if [ -n "${DB_ROOT_PASS:-}" ]; then
        docker exec -i smartTradePOS_db mysql -uroot -p"$DB_ROOT_PASS"
    elif [ -n "${DB_USER:-}" ] && [ -n "${DB_PASS:-}" ]; then
        docker exec -i smartTradePOS_db mysql -u"$DB_USER" -p"$DB_PASS"
    else
        die "No DB_ROOT_PASS or DB_USER/DB_PASS in .env"
    fi
}

mysql_ping() {
    if [ -n "${DB_ROOT_PASS:-}" ]; then
        docker exec smartTradePOS_db mysqladmin ping -h localhost -uroot -p"$DB_ROOT_PASS" --silent
    elif [ -n "${DB_USER:-}" ] && [ -n "${DB_PASS:-}" ]; then
        docker exec smartTradePOS_db mysqladmin ping -h localhost -u"$DB_USER" -p"$DB_PASS" --silent
    else
        return 1
    fi
}

wait_for_mysql() {
    local i
    for i in $(seq 1 60); do
        if mysql_ping >/dev/null 2>&1; then
            echo "MySQL is up."
            return 0
        fi
        sleep 2
    done
    die "MySQL did not become ready (mysqladmin ping)."
}

load_env

echo "Restoring shop from $SRC"
echo "Creating containers without starting extra services…"
"${COMPOSE[@]}" up --no-start

echo "Restoring VSDC webapps (keys)…"
"${COMPOSE[@]}" stop vsdc >/dev/null 2>&1 || true
docker run --rm --volumes-from vsdc \
    -v "$SRC:/backup" alpine \
    sh -c "rm -rf /usr/local/tomcat/webapps/* && tar xzf /backup/vsdc_webapps-${STAMP}.tgz -C /usr/local/tomcat/webapps"

if [ -s "$DB_SQL" ]; then
    echo "Restoring MySQL from logical dump…"
    "${COMPOSE[@]}" start db
    wait_for_mysql
    gunzip -c "$DB_SQL" | mysql_client
else
    echo "Restoring MySQL data directory from volume archive…"
    "${COMPOSE[@]}" stop db >/dev/null 2>&1 || true
    docker run --rm --volumes-from smartTradePOS_db \
        -v "$SRC:/backup" alpine \
        sh -c "rm -rf /var/lib/mysql/* && tar xzf /backup/db_data-${STAMP}.tgz -C /var/lib/mysql"
    "${COMPOSE[@]}" start db
    wait_for_mysql
fi

echo "Starting vsdc, then web…"
"${COMPOSE[@]}" start vsdc
"${COMPOSE[@]}" start web

echo
echo "Restore finished from $STAMP"
echo "Do not Initialize. On ZRA Smart Invoice use Test connection only."
echo "SDC ID should match the old PC."
