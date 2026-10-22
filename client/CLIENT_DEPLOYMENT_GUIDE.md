# Offline Sealed Edge Deployment Runbook

This guide outlines the exact steps to migrate a live online client onto an offline, local edge machine running the sealed OPcache bytecode container (`smarttradepos-pro-client:sealed`). Zero readable PHP source code is stored on the client machine, and registry tokens are automatically shredded from `.env` on first boot.

---

## Phase 1: Prepare & Push the Sealed Image (Developer PC)

1. Ensure your local code is up to date with `kamatera-production` and on the sealed branch:
   ```bash
   git checkout cursor/client-opcache-sealed-image-38c5
   git merge origin/kamatera-production
   ```

2. Authenticate your development PC with GitLab Container Registry (one-time setup):
   ```bash
   docker login registry.gitlab.com
   ```

3. Build the OPcache-compiled sealed image and push it to GitLab Container Registry:
   ```bash
   ./client/push-registry.sh
   ```
   *(By default, this pushes to `registry.gitlab.com/cephamando/smarttrade-pos-pro:sealed`)*

4. In GitLab (**Settings ➔ Repository ➔ Deploy tokens**), create a **Read-Only Deploy Token** with **only** the `read_registry` scope enabled. Keep the generated username and token handy for Phase 3.

---

## Phase 2: Export Live Data & Media from the Online Server

Because the client is already operating online, export their live database and product/branding images so the local machine starts with their exact catalog, staff accounts, and historical records.

1. **Export the live MySQL database** on the online server:
   ```bash
   docker exec smartTradePOS_db mysqldump -u root -p"<DB_ROOT_PASS>" pos_db > /tmp/client_live_db.sql
   ```

2. **Archive the uploaded images** (product photos, logos, library):
   ```bash
   tar -czf /tmp/client_uploads.tar.gz -C /root/smartTrade-POS-Pro/public/uploads .
   ```

3. Copy `/tmp/client_live_db.sql` and `/tmp/client_uploads.tar.gz` to your PC or a USB drive to bring to the client machine (and remove the `/tmp` copies from the server afterward).

---

## Phase 3: Prepare the Minimal Client Bundle (No Source Code)

**Do NOT `git clone` the repository onto the client's machine** (cloning puts all PHP source code into `.git` history and working files). Copy only the minimal runtime launcher structure onto the client machine (e.g., `/opt/smarttrade-pos/`):

```text
/opt/smarttrade-pos/
├── .env                                 <-- Created from client/client.env
├── client/
│   ├── docker-compose.yml
│   ├── docker-compose.env.yml
│   ├── docker-compose.local.yml
│   ├── client-registry-update.sh
│   └── local-down.sh
├── api/                                 <-- ZRA VSDC war/config directory
└── docker/
    ├── vsdc/
    │   └── entrypoint.sh
    └── mysql/
        └── smartTradePOS_db.sql         <-- Put your exported client_live_db.sql here!
```

> **Tip:** Placing `client_live_db.sql` at `docker/mysql/smartTradePOS_db.sql` before the first start allows MySQL to automatically import the client's live database when initializing the volume.

---

## Phase 4: Configure `.env` with One-Time Bootstrap Credentials

On the client machine, create `/opt/smarttrade-pos/.env` (copied from `client/client.env`) and fill in the database passwords, ZRA details, and the one-time GitLab Deploy Token:

```ini
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_NAME=pos_db
DB_USER=posUser
DB_PASS=<strong-local-db-password>
DB_ROOT_PASS=<strong-local-root-password>

APP_ENV=production
APP_DEBUG=false
TZ=Africa/Lusaka

APP_PORT=8090
CLIENT_BIND_ADDR=127.0.0.1
CLIENT_APP_IMAGE=registry.gitlab.com/cephamando/smarttrade-pos-pro:sealed

# ONE-TIME BOOTSTRAP CREDENTIALS (auto-shredded on first run)
REGISTRY_USER=<gitlab-deploy-token-username>
REGISTRY_TOKEN=<gitlab-deploy-token-secret>

# ZRA VSDC Settings
ZRA_TPIN=<client-tpin>
ZRA_BHF_ID=000
ZRA_DVC_SRL_NO=<client-device-serial>
ZRA_VSDC_URL=http://vsdc:8080/sandboxvsdc
ZRA_PORTAL_URL=https://www.zra.org.zm
ZRA_NON_VAT=false
```

> **Note on ZRA VSDC:** Do **not** copy the online server's VSDC volume to the local PC if the online server is still active with ZRA—two machines sharing one initialized VSDC device key will cause signature counter conflicts. Either initialize a dedicated branch/device serial for the local machine, or migrate the existing VSDC volume only after decommissioning the online VSDC instance.

---

## Phase 5: First Boot, Token Auto-Scrub & Media Restore

1. Run the update/startup script on the client machine:
   ```bash
   chmod +x /opt/smarttrade-pos/client/client-registry-update.sh
   sudo /opt/smarttrade-pos/client/client-registry-update.sh
   ```
   **What happens automatically:**
   - Elevates to `root` and locks `.env` to `0600`.
   - Logs `root` into `registry.gitlab.com` using `--password-stdin` and locks `/root/.docker` (`0700`) and `/root/.docker/config.json` (`0600`).
   - **Scrubs `REGISTRY_USER` and `REGISTRY_TOKEN` out of `.env`** before starting the containers so the token never enters the PHP container environment.
   - Pulls the compiled bytecode image and starts `smarttrade-client-app`, `smarttrade-client-db`, and `smarttrade-client-vsdc`.

2. **Restore the client's uploaded product images** into the running container volume:
   ```bash
   docker cp /path/to/client_uploads.tar.gz smarttrade-client-app:/tmp/uploads.tar.gz
   docker exec smarttrade-client-app sh -c "tar -xzf /tmp/uploads.tar.gz -C /var/www/html/public/uploads && chown -R www-data:www-data /var/www/html/public/uploads && rm -f /tmp/uploads.tar.gz"
   ```

3. Verify the POS opens at `http://localhost:8090` and confirm `.env` no longer contains `REGISTRY_TOKEN`:
   ```bash
   sudo grep -E 'REGISTRY_' /opt/smarttrade-pos/.env || echo "Clean: Token shredded from .env"
   ```

---

## Phase 6: Schedule Silent Daily Updates (`root` Crontab)

Schedule `client-registry-update.sh` in `root`'s crontab so the client machine checks for new compiled layers once a day in the background:

```bash
sudo crontab -e
```

Add the following line (runs daily at 03:15 AM and logs output to `/var/log/smarttrade-update.log`):

```cron
15 3 * * * /opt/smarttrade-pos/client/client-registry-update.sh >> /var/log/smarttrade-update.log 2>&1
```
