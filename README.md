# smartTrade POS Pro

Multi-location point of sale for retail and hospitality: sales, stock, purchasing, kitchen, and ZRA Smart Invoice. The name in the navbar is the `business_name` setting. This repository ships as the enterprise license (`license_tier` = `enterprise` in the seed database).

Requests enter `public/index.php`, which loads `app/bootstrap.php`. Pages are `index.php?page=…` (see `app/Config/routes.php`).

How to use the till, kitchen, stock, and ZRA screens: [user_guide.md](user_guide.md).

## Stack

- PHP 8.3 (Apache, document root `public/`)
- MySQL 8.3
- Bootstrap 5, SweetAlert2, Chart.js
- Docker Compose: POS, MySQL, ZRA VSDC (Tomcat 9 / Java 8), phpMyAdmin, Adminer

## Run

1. Copy `.env.example` to `.env` and set the database passwords, ports, and V-SIAS URLs. The ZRA TPIN, branch, and device serial are entered later on **ZRA invoices** by the developer. Leave the VSDC address as `http://vsdc:8080/sandboxvsdc`.
2. Put the ZRA VSDC `.war` in `api/`. Leave the archive intact; Tomcat explodes it.
3. From the project root:

```bash
docker compose up -d --build
```

4. Open the POS at `http://localhost:8090` (or `APP_PORT` in `.env`).

On Windows, run Compose from WSL Ubuntu. The shop containers are `smartTradePOS-PRO_app`, `smartTradePOS_db`, and `vsdc`.

| Service | Container | Default URL |
|---|---|---|
| POS | `smartTradePOS-PRO_app` | http://localhost:8090 |
| POS (HTTPS) | same | https://localhost:8443 |
| MySQL | `smartTradePOS_db` | localhost:8091 |
| VSDC | `vsdc` | http://localhost:8080/sandboxvsdc |
| phpMyAdmin | `smartTradePOS_pma` | http://localhost:8092 |
| Adminer | `smartTradePOS_adminer` | http://localhost:8093 |

The first start loads `docker/mysql/smartTradePOS_db.sql` only while the `db_data` volume is empty. Later starts keep the existing database.

`ZRA_VSDC_URL` stays `http://vsdc:8080/sandboxvsdc`. That address is inside Docker. From the shop browser, probe Tomcat at `http://localhost:8080/sandboxvsdc`. Day-to-day work stays in the POS.

## License tiers

`LICENSE_TIER` comes from the `license_tier` setting.

| Tier | Adds |
|---|---|
| lite | POS, products, categories, stock levels, users, locations, reports, shifts, Z-Read |
| pro | Members, audit, purchasing, vendors, receiving bay, transfers, pickup |
| pro+ | Kitchen, KDS, menu builder, table settings |
| enterprise | ZRA Smart Invoice, plus the tiers above |

Roles are `admin`, `manager`, `dev`, `cashier`, `chef`, and `head_chef`. Admins and developers can switch workstation from the navbar. Cashiers stay on POS and stock requests. Chefs on Pro+ or Enterprise land on the kitchen.

A past `lockout_date` blocks changes until the license is renewed. Developers can still sign in.

## Shop modules

- **POS** — cart, split tenders, partial payments, on-hand checks, table tabs, and receipt refunds tied to the original invoice
- **Shifts** — X-Reads and end-of-day Z-Read
- **Inventory** — products and menu, categories, UNSPSC classes, stock by location, stock history
- **Purchasing** — inquiries, purchase orders, vendors, goods received, receiving bay
- **Transfers** — move stock between locations; V-SIAS Pro can hand an approved inspection back into the POS
- **Kitchen** — meal production (on-hand at the selected POS), recipe consumption, KDS, pickup
- **ZRA Smart Invoice** — register items, test the device, and fiscalize paid sales through the local VSDC

Critical actions ask for a SweetAlert confirm. Adding to the cart, quantity changes, and navigation do not.

## ZRA Smart Invoice

The POS posts to the local VSDC container. VSDC signs the invoice and talks to ZRA. Smart Invoice does not file the VAT return.

Device keys are created once, when you click **Initialize**, and stored in the Docker volume `vsdc_webapps` (on disk: `smarttrade-pos-pro_vsdc_webapps`). The SDC ID and MRC number saved in MySQL are copies for receipts. ZRA will not issue a second keystore for the same serial.

- Initialize a serial once, on the PC that will keep selling.
- Keep `docker compose down` without `-v`. The `-v` flag deletes `vsdc_webapps` and `db_data`.
- Restart, rebuild of the PHP image, and `docker compose up -d --force-recreate vsdc` keep the keys when the WAR bytes are unchanged.
- A byte-different WAR makes Tomcat redeploy and can delete the keystore.
- One live machine per serial. A second PC restored from the same backup is the same device and will collide on receipt numbers.

Sandbox setup: [zra_setup_guide.md](zra_setup_guide.md). Production checklist: [ZRA_GO_LIVE.md](ZRA_GO_LIVE.md).

## V-SIAS Pro

V-SIAS is a separate app (default browser port 8095). The POS opens approved inspections from Management → Site inspection.

- `VSIAS_URL` is what the browser uses. `localhost` is rewritten to the host you used to open the POS, so a phone on the LAN does not iframe itself.
- `VSIAS_INTERNAL_URL` is the server-to-server address (`http://host.docker.internal:8095` when V-SIAS runs on the Windows host).
- Off localhost, inspection opens in the same tab. On localhost it opens in the inspection modal.

## Backup and restore

Back up while the shop is up. From the project root in WSL:

```bash
./scripts/backup-shop.sh
```

Optional copy onto a Windows drive (the script mounts the letter with sudo when WSL cannot see it):

```bash
BACKUP_COPY_TO=/mnt/f/mando/smartTrade-backups ./scripts/backup-shop.sh
```

Each stamp under `backups/YYYYMMDD-HHMM/` contains the VSDC webapps archive, a MySQL dump, a raw datadir archive, `.env`, and the WAR. `backups/` is gitignored. Copy the folder off the PC. ZRA cannot reissue that keystore.

Check a restore without writing volumes:

```bash
./scripts/restore-shop.sh YYYYMMDD-HHMM
```

Live restore replaces the VSDC volume and the database:

```bash
./scripts/restore-shop.sh YYYYMMDD-HHMM --yes
```

Use that on the replacement PC, with the same `.env` serial and the same WAR. Then open ZRA Smart Invoice and use **Test connection** only. An SDC ID that matches the old PC means the keystore is back. Leave **Initialize** alone.

## Security headers

The app sends `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, and a Content-Security-Policy that allows the V-SIAS frame. HSTS is sent only on HTTPS. Login checks a session CSRF token. Apache `mod_headers` and `mod_rewrite` stay enabled in the web container.

---

Copyright © 2026 Mando N. Chishimba. All rights reserved.
