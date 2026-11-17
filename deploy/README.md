# GWM / HAGBES — Production Deployment

Deploys the Dockerised WordPress site (GWM experience built on the `astra-child`
theme) to a Linux server.

## Contents

| File | Purpose |
|------|---------|
| `install.sh` | One-shot production installer |
| `db-latest.sql.gz` | Current database export (includes the GWM theme activation, page-template assignments, price fields and URL state) |
| `../compose.yml` | Production compose file — bind-mounts `/opt/website/html` and `/opt/website/db` |
| `../html/` | WordPress files, including `wp-content/themes/astra-child` |

> **Note:** `../compose.override.yml` is for **local Windows development only**
> (it repoints the volumes to `./html` and a named volume). The installer uses
> `docker compose -f compose.yml` explicitly so the override is never applied,
> and deletes any stray copy from the server.

## Prerequisites (on the server)

- Docker Engine + Docker Compose v2 (`docker compose version`)
- Root access (or `sudo`)
- The whole project folder transferred to the server, e.g.:
  ```bash
  rsync -az ./website/ user@server:/srv/gwm-site/
  ```

## Install

```bash
cd /srv/gwm-site
cp .env.example .env           # then edit .env: SITE_URL, passwords, admin, ports
chmod +x deploy/install.sh
sudo ./deploy/install.sh        # reads .env (inline VARS still override it)
```

The script will:

1. Create `${APP_DIR}/{html,db}`
2. Sync `html/` into `${APP_DIR}/html` and set ownership to `www-data` (33:33)
3. `docker compose up -d` (WordPress + MariaDB + phpMyAdmin)
4. Wait for the DB, import `db-latest.sql.gz`
5. Rewrite the site URL from `http://localhost:8080` to your `SITE_URL`
   (serialisation-safe, via WP-CLI), then flush permalinks + caches
6. Ensure the `WP_ADMIN_USER` exists (create if missing; reset its password
   only when `WP_ADMIN_RESET=1`)

### Configuration (`.env`)

All tunable values live in `.env` (copied from `.env.example`): `SITE_URL`,
host ports, `APP_DIR`, the `MYSQL_*` credentials, `WP_ADMIN_*`, and PHP limits.
`.env` is git-ignored; `.env.example` is the committed template.

```bash
# Inline vars override .env for a one-off run, e.g. a different dump:
sudo DB_DUMP=/path/to/other.sql.gz ./deploy/install.sh

# Import the DB exactly as-is (skip the URL rewrite):
sudo SKIP_URL_REWRITE=1 ./deploy/install.sh
```

## After install — important

1. **TLS / reverse proxy.** `compose.yml` exposes WordPress on host port **8080**
   (HTTP only). Put nginx / Caddy / Traefik with a certificate in front of it and
   proxy `443 → 127.0.0.1:8080`. `wp-config.php` already honours
   `X-Forwarded-Proto`, so HTTPS behind a proxy works out of the box. Example
   nginx location:
   ```nginx
   location / {
       proxy_pass http://127.0.0.1:8080;
       proxy_set_header Host $host;
       proxy_set_header X-Real-IP $remote_addr;
       proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
       proxy_set_header X-Forwarded-Proto $scheme;
   }
   ```

2. **Set strong passwords in `.env` BEFORE the first run.** `MYSQL_ROOT_PASSWORD`
   and `MYSQL_PASSWORD` are written into the MySQL data volume on first init only —
   changing them afterwards does not re-key an existing database.

3. **phpMyAdmin.** It is exposed on host port `${PHPMYADMIN_PORT}` (default 8180).
   For a public server, firewall it, bind it to `127.0.0.1`, or remove the
   `phpmyadmin` service from `compose.yml` entirely.

4. **Backups.** The database lives in the bind mount `${APP_DIR}/db`. Snapshot
   it, or schedule `mysqldump`:
   ```bash
   docker exec db mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction wordpress \
     | gzip > backup-$(date +%F).sql.gz
   ```

## Updating later

- **Code/theme changes:** re-sync `html/` and restart:
  `rsync -a html/ /opt/website/html/ && docker compose -f compose.yml restart wordpress`
- **Fresh DB from another environment:** drop a new `*.sql.gz` and re-run the
  installer with `DB_DUMP=…` (the dump drops/recreates tables, so it overwrites).

## Teardown

```bash
docker compose -f compose.yml down          # stop (keeps /opt/website/db data)
docker compose -f compose.yml down -v        # also removes anonymous volumes
```
