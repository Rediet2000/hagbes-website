#!/usr/bin/env bash
#
# GWM / HAGBES WordPress — production installer
# =============================================
# Brings up the Dockerised WordPress stack on a Linux production server:
#   - creates the data directories the compose file bind-mounts
#   - syncs the WordPress files (incl. the astra-child GWM theme)
#   - starts WordPress + MariaDB (+ phpMyAdmin) via docker compose
#   - imports the database, rewrites the site URL, ensures the admin user
#
# Configuration comes from .env (see .env.example). Any value can also be
# overridden inline, e.g.  sudo SITE_URL=https://gwm.hagbes.com ./deploy/install.sh
#
# Run it ONCE on a fresh server. Re-running is safe (re-syncs files and
# re-imports the DB — the dump drops/recreates tables).
#
# Prerequisites: Docker Engine + Docker Compose v2, run as root (or sudo),
# with the project folder transferred to the server first.
#
set -euo pipefail

# --- resolve paths relative to this script ---
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

# --- load .env (inline env vars still win) ---
if [ -f "$PROJECT_DIR/.env" ]; then
	set -a; . "$PROJECT_DIR/.env"; set +a
fi

# --- config (defaults match .env.example) ---
APP_DIR="${APP_DIR:-/opt/website}"
SITE_URL="${SITE_URL:?Set SITE_URL in .env, e.g. SITE_URL=https://gwm.example.com}"
OLD_URL="${OLD_URL:-http://localhost:8080}"
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-strong_root_password}"
MYSQL_USER="${MYSQL_USER:-wordpress}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-wordpress_password}"
MYSQL_DATABASE="${MYSQL_DATABASE:-wordpress}"
WORDPRESS_TABLE_PREFIX="${WORDPRESS_TABLE_PREFIX:-wp_}"
DOCKER_NETWORK="${DOCKER_NETWORK:-wordpress_network}"
WP_ADMIN_USER="${WP_ADMIN_USER:-}"
WP_ADMIN_PASSWORD="${WP_ADMIN_PASSWORD:-}"
WP_ADMIN_EMAIL="${WP_ADMIN_EMAIL:-}"
WP_ADMIN_RESET="${WP_ADMIN_RESET:-0}"
DB_DUMP="${DB_DUMP:-$SCRIPT_DIR/db-latest.sql.gz}"
SRC_HTML="${SRC_HTML:-$PROJECT_DIR/html}"
COMPOSE_FILE="${COMPOSE_FILE:-$PROJECT_DIR/compose.yml}"

log(){ printf '\n\033[1;32m==>\033[0m %s\n' "$*"; }
die(){ printf '\n\033[1;31mERROR:\033[0m %s\n' "$*" >&2; exit 1; }

# wp-cli in a throwaway container sharing the WordPress container's files/DB
wpcli(){
	docker run --rm --network "$DOCKER_NETWORK" --volumes-from wordpress \
		-e WORDPRESS_DB_HOST=db:3306 \
		-e WORDPRESS_DB_USER="$MYSQL_USER" \
		-e WORDPRESS_DB_PASSWORD="$MYSQL_PASSWORD" \
		-e WORDPRESS_DB_NAME="$MYSQL_DATABASE" \
		-e WORDPRESS_TABLE_PREFIX="$WORDPRESS_TABLE_PREFIX" \
		wordpress:cli "$@"
}

# --- preflight ---
[ "$(id -u)" -eq 0 ] || die "Run as root (or via sudo)."
command -v docker >/dev/null 2>&1 || die "Docker is not installed."
docker compose version >/dev/null 2>&1 || die "Docker Compose v2 is not available."
[ -d "$SRC_HTML" ] || die "WordPress files not found at: $SRC_HTML"
[ -f "$COMPOSE_FILE" ] || die "compose.yml not found at: $COMPOSE_FILE"
[ -f "$DB_DUMP" ] || die "Database dump not found at: $DB_DUMP"

log "Installing GWM/HAGBES into $APP_DIR  (site URL: $SITE_URL)"

# --- 1. data directories (bind-mounted by compose.yml) ---
log "Creating data directories"
mkdir -p "$APP_DIR/html" "$APP_DIR/db"

# --- 2. sync WordPress files ---
log "Syncing WordPress files -> $APP_DIR/html"
if command -v rsync >/dev/null 2>&1; then
	rsync -a --delete \
		--exclude 'wp-content/tf_cache_config' \
		--exclude 'wp-content/upgrade-temp-backup' \
		"$SRC_HTML/" "$APP_DIR/html/"
else
	cp -a "$SRC_HTML/." "$APP_DIR/html/"
fi
# Never let the local dev override reach production (it repoints the volumes).
rm -f "$APP_DIR/compose.override.yml" "$APP_DIR/html/compose.override.yml" 2>/dev/null || true

# --- 3. permissions (container runs as www-data = uid/gid 33) ---
log "Setting ownership to www-data (33:33)"
chown -R 33:33 "$APP_DIR/html"

# --- 4. start the stack (explicit -f so the dev override is NOT auto-loaded) ---
log "Starting containers"
docker compose -f "$COMPOSE_FILE" up -d

# --- 5. wait for the database ---
log "Waiting for MariaDB to accept connections"
for i in $(seq 1 60); do
	if docker exec db mysqladmin ping -h localhost -uroot -p"$MYSQL_ROOT_PASSWORD" --silent >/dev/null 2>&1; then
		ok=1; break
	fi
	sleep 2
done
[ "${ok:-}" = "1" ] || die "Database did not become ready in time."

# --- 6. import the database ---
log "Importing database ($(basename "$DB_DUMP"))"
zcat "$DB_DUMP" | docker exec -i db mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"

# --- 7. rewrite the site URL to production ---
if [ "${SKIP_URL_REWRITE:-0}" != "1" ] && [ "$OLD_URL" != "$SITE_URL" ]; then
	log "Rewriting site URL: $OLD_URL -> $SITE_URL"
	wpcli wp search-replace "$OLD_URL" "$SITE_URL" \
		--all-tables-with-prefix --skip-columns=guid --precise
	log "Flushing permalinks and caches"
	wpcli sh -c 'wp rewrite flush --hard; wp cache flush' || true
fi

# --- 8. ensure the WordPress admin user ---
if [ -n "$WP_ADMIN_USER" ]; then
	if wpcli wp user get "$WP_ADMIN_USER" --field=ID >/dev/null 2>&1; then
		if [ "$WP_ADMIN_RESET" = "1" ] && [ -n "$WP_ADMIN_PASSWORD" ]; then
			log "Resetting password for admin '$WP_ADMIN_USER'"
			wpcli wp user update "$WP_ADMIN_USER" --user_pass="$WP_ADMIN_PASSWORD"
		else
			log "Admin '$WP_ADMIN_USER' already exists — left unchanged (set WP_ADMIN_RESET=1 to reset)"
		fi
	elif [ -n "$WP_ADMIN_PASSWORD" ] && [ -n "$WP_ADMIN_EMAIL" ]; then
		log "Creating admin '$WP_ADMIN_USER'"
		wpcli wp user create "$WP_ADMIN_USER" "$WP_ADMIN_EMAIL" \
			--role=administrator --user_pass="$WP_ADMIN_PASSWORD"
	fi
fi

log "Done. The stack is up:"
docker compose -f "$COMPOSE_FILE" ps
cat <<EOF

  WordPress   : $SITE_URL
  phpMyAdmin  : host port ${PHPMYADMIN_PORT:-8180}   (block/remove it publicly — see README)
  GWM landing : $SITE_URL/gwm-ethiopia/

  Config lives in .env (copied from .env.example). Next steps:
    * Put a reverse proxy (nginx / Caddy / Traefik) with TLS in front of the WordPress port.
    * Set strong MYSQL_* passwords in .env BEFORE the first run (they key the DB volume once).
    * Firewall or remove the phpMyAdmin service for a public server.
EOF
