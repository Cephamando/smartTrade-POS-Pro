# Offline Sealed Edge Deployment & Live Synchronization Runbook

This guide outlines the exact steps to set up two paired apps per client:
1. **Online HQ (`SYNC_NODE_ROLE=online`)**: Runs on your live server, hosts customer web ordering, holds the master catalog, and runs the single active **ZRA VSDC**.
2. **Offline Shop Edge (`SYNC_NODE_ROLE=edge`)**: Runs the sealed OPcache bytecode container (`smarttradepos-pro-client:sealed`) on the shop PC (`http://localhost:8090`). It works with zero internet dependency during outages, pulls products/staff/settings/web orders from Online HQ, and automatically pushes offline sales, shifts, tips, expenses, and stock deductions back to Online HQ (which fiscalizes them on the Master ZRA VSDC) as soon as connectivity returns.

---

## Phase 1: Prepare & Push the Sealed Image (Developer PC)

1. Ensure your local code is up to date on `kamatera-production`:
   ```bash
   git checkout kamatera-production
   git pull --ff-only
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

4. In GitLab (**Settings ➔ Repository ➔ Deploy tokens**), create a **Read-Only Deploy Token** with **only** the `read_registry` scope enabled. Keep the generated username and token handy for Phase 4.

---

## Phase 2: Generate the Edge Sync Token on Online HQ

1. Sign in to the client's **Online HQ** back-office (`https://smartrade.odeliazm.com`) as an `admin` or `dev`.
2. Open **Office ➔ Settings ➔ Edge Sync**.
3. Ensure **This Node Role** is set to **Online HQ (Master Server & ZRA VSDC)**.
4. Click **Generate New Token**, copy the 64-character token, and click **Save Edge Sync**.

*(Optional for existing live clients)* Export a baseline SQL snapshot of their live database so the shop PC starts with all historical records immediately:
```bash
docker exec smartTradePOS_db mysqldump -u root -p"<DB_ROOT_PASS>" pos_db > /tmp/client_live_db.sql
```

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
│   ├── edge-sync-cron.sh
│   └── local-down.sh
├── api/                                 <-- ZRA VSDC war/config directory
└── docker/
    ├── vsdc/
    │   └── entrypoint.sh
    └── mysql/
        └── smartTradePOS_db.sql         <-- Baseline schema or exported client_live_db.sql
```

---

## Phase 4: Configure `.env` with One-Time Bootstrap & Sync Credentials

On the client machine, create `/opt/smarttrade-pos/.env` (copied from `client/client.env`) and fill in the database passwords, the one-time GitLab Deploy Token, and the Online HQ sync token:

```ini
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_NAME=pos_db
DB_USER=posUse
DB_PASS=<strong-local-db-password>
DB_ROOT_PASS=<strong-local-root-password>

APP_ENV=production
APP_DEBUG=false
TZ=Africa/Lusaka

APP_PORT=8090
CLIENT_BIND_ADDR=127.0.0.1
CLIENT_APP_IMAGE=registry.gitlab.com/cephamando/smarttrade-pos-pro:sealed

# ONE-TIME BOOTSTRAP CREDENTIALS (auto-shredded from .env on first run)
REGISTRY_USER=<gitlab-deploy-token-username>
REGISTRY_TOKEN=<gitlab-deploy-token-secret>

# LIVE + OFFLINE EDGE SYNCHRONIZATION
SYNC_NODE_ROLE=edge
SYNC_ONLINE_URL=https://smartrade.odeliazm.com
SYNC_NODE_TOKEN=<64-char-token-from-online-hq-settings>
SYNC_LOCATION_ID=1
ZRA_VSDC_MODE=remote
```

> **How ZRA Fiscalization Works Across Both Apps:** With `ZRA_VSDC_MODE=remote`, the Offline Shop PC never conflicts with the Online HQ's ZRA VSDC device serial. When internet is up, checkout on the shop PC immediately fiscalizes the receipt through Online HQ's VSDC and prints the ZRA QR code. If internet is down, the sale completes locally in milliseconds (`ZRA: pending sync`), and once internet returns, `EdgeSync` pushes the sale to Online HQ, fiscalizes it on the master VSDC, and updates the receipt signature on both databases.

---

## Phase 5: First Boot, Token Auto-Scrub & Initial Sync

1. Run the update/startup script on the client machine:
   ```bash
   chmod +x /opt/smarttrade-pos/client/client-registry-update.sh /opt/smarttrade-pos/client/edge-sync-cron.sh
   sudo /opt/smarttrade-pos/client/client-registry-update.sh
   ```
   **What happens automatically:**
   - Elevates to `root` and locks `.env` to `0600`.
   - Logs `root` into `registry.gitlab.com` using `--password-stdin` and locks `/root/.docker` (`0700`) and `/root/.docker/config.json` (`0600`).
   - **Scrubs `REGISTRY_USER` and `REGISTRY_TOKEN` out of `.env`** before starting the containers so the registry token never enters the PHP container environment.
   - Pulls the compiled bytecode image and starts the local stack.

2. Trigger an immediate initial sync (pulls all products, categories, recipes, staff accounts, settings, and product photos from Online HQ):
   ```bash
   sudo /opt/smarttrade-pos/client/edge-sync-cron.sh
   ```

3. Open `http://localhost:8090` and verify the green **Synced** badge appears in the top bar.

---

## Phase 6: Schedule Background Sync & Daily Image Updates (`root` Crontab)

While the POS is open in a browser, it automatically syncs in the background every 20 seconds. Adding `edge-sync-cron.sh` to `root`'s crontab ensures synchronization continues every minute even when all browser windows are closed, alongside the daily bytecode image update:

```bash
sudo crontab -e
```

Add these two lines:

```cron
# Sync offline sales, stock & catalog with Online HQ every minute
* * * * * /opt/smarttrade-pos/client/edge-sync-cron.sh

# Check GitLab Container Registry daily at 03:15 AM for new compiled bytecode layers
15 3 * * * /opt/smarttrade-pos/client/client-registry-update.sh >> /var/log/smarttrade-update.log 2>&1
```
