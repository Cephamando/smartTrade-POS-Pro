# ZRA Smart Invoice — production go-live

This is the checklist for taking **smartTradePOS-Pro** live with ZRA Smart Invoice.

Smart Invoice does **not** file your VAT return. It sends each paid sale to ZRA as a signed invoice (SDC ID, signature, QR). You still file the return later on the taxpayer portal.

---

## What you are installing

Three separate things, on the **shop PC that will stay in the shop**:

| Piece | What it is | Where it lives |
|---|---|---|
| POS app | smartTrade (PHP + MySQL) | Docker `web` + `db` |
| VSDC | ZRA’s local tax box (Tomcat + WAR) | Docker `vsdc` |
| Keystore | API keys created at first **Initialize** | Docker volume `vsdc_webapps` |

The POS talks to VSDC on the Docker network at `http://vsdc:8080/sandboxvsdc`.  
VSDC talks to ZRA over the internet.  
`https://www.zra.org.zm` is the **public portal** (QRs and returns). It is **not** the VSDC URL.

`vsdc` is not a website you open in Chrome. From the shop PC browser use `http://localhost:8080/sandboxvsdc` if you need to probe Tomcat. Day-to-day, use the POS only.

---

## Do this on sandbox first (dress rehearsal)

Do **not** skip this if the catalogue is new (software, consulting, medicines, lab supplies, etc.).

1. Keep this lab/sandbox machine as sandbox. Do not flip it to production.
2. Create every sellable line in Products (goods) or Services.
3. Set **ZRA tax class** (usually A = 16%, unless you have a real zero-rate or exemption).
4. Set a **level-4 UNSPSC** from **Sync UNSPSC classes**. Blank codes fall back to beer (`50202201`) — wrong for a service or a drug.
5. ZRA Smart Invoice → Register products.
6. Ring a paid sale and a refund from that receipt. Scan the QR.

When that is clean, go live on a **different** machine (or a **new** volume + **new** production serial). Sandbox keys and serials do not carry over.

---

## Production go-live

### 1. On the ZRA production portal

1. Log in as the **client taxpayer** (production, not sandbox).
2. Under Device Management, register a **new device serial** for this shop PC.  
   Do **not** reuse the sandbox serial (`STPOS001V2` or any other sandbox device).
3. Note exactly:
   - TPIN (10 digits)
   - Branch ID (usually `000` for HQ)
   - Device serial (as shown on the portal)
4. Download the **production VSDC package**.

One serial = one Initialize = one keystore. That serial is spent on the PC you initialize. Initialize on the shop PC, not on your laptop, unless you will migrate that volume with the PC.

### 2. Shop PC — software

1. Install **Docker Desktop** (or Docker Engine + Compose on Linux).
2. Copy this project onto the PC (code, `docker/`, `.env`, and `api/`).
3. Put the production WAR in `api/` or `api/sandboxvsdc/`:
   - If ZRA gave a **zip**, unzip the zip.
   - Copy the **`.war` file** as-is into `api/`.
   - Do **not** explode/unzip the `.war` itself. Tomcat needs the file.

The container deploys any `.war` it finds as `/sandboxvsdc`. The local path stays the same even in production.

### 3. Shop PC — `.env`

Edit `.env`. Keep VSDC local. Point the **QR portal** at production.

```env
ZRA_TPIN=the_production_tpin
ZRA_BHF_ID=000
ZRA_DVC_SRL_NO=the_production_serial_exactly_as_on_the_portal

# Local Tomcat — never https://www.zra.org.zm
ZRA_VSDC_URL=http://vsdc:8080/sandboxvsdc

# Receipt QR + verification links
ZRA_PORTAL_URL=https://www.zra.org.zm
```

If you change `.env` after containers are already up:

```bash
docker compose up -d
```

### 4. First start (empty keystore)

On the shop PC, from the project folder:

```bash
cd ~/smartTrade-POS-Pro
docker compose up -d --build
```

Wait until `web`, `db`, and `vsdc` are up. Confirm VSDC from the shop browser:

`http://localhost:8080/sandboxvsdc`

The shop PC needs **internet** so VSDC can reach ZRA.

**Do not** restore this sandbox machine’s `vsdc_webapps` volume onto production.  
**Do not** run `docker compose down -v` (the `-v` deletes the keystore).

### 5. Handshake (once)

In the POS, open **ZRA Smart Invoice**:

1. **Test connection** — local VSDC must be reachable.
2. **Initialize device** — **once**. Wait for success and an SDC ID / MRC.
3. **Sync standard codes**
4. **Sync UNSPSC classes**
5. **Register products** until unsynced products is 0
6. Ring **one live paid sale** (not 0.00, not an empty cart)
7. Check the receipt: TPIN, SDC ID, signature, QR
8. Scan the QR — it must open the **production** portal, not sandbox

If Initialize succeeds, **never click it again**.

### 6. Backup the keys (same day)

The keys are **not** in `.env` or MySQL. They are in Docker volume `vsdc_webapps`. Back that up **with** the database, **off this PC**.

```bash
cd ~/smartTrade-POS-Pro
mkdir -p backups
docker compose stop web vsdc db

STAMP=$(date +%Y%m%d-%H%M)

docker run --rm --volumes-from vsdc \
  -v "$(pwd)/backups:/backup" alpine \
  tar czf "/backup/vsdc_webapps-${STAMP}.tgz" -C /usr/local/tomcat/webapps .

docker run --rm --volumes-from smartTradePOS_db \
  -v "$(pwd)/backups:/backup" alpine \
  tar czf "/backup/db_data-${STAMP}.tgz" -C /var/lib/mysql .

docker compose start db vsdc web
```

Copy off the PC:

- `backups/vsdc_webapps-*.tgz` (cannot be re-downloaded from ZRA)
- `backups/db_data-*.tgz`
- `.env`
- the production `.war` in `api/`

Then start selling.

---

## Day-to-day

- Sell as normal. Paid sales fiscalize automatically.
- Refund from the **original fiscalized receipt**, not a blank walk-in return.
- Empty or 0.00 tickets are skipped — they are not invoices.
- App updates (`git pull`, rebuild `web`) are safe. They do not wipe VSDC.
- `docker compose restart` and `docker compose up -d --force-recreate vsdc` are safe **if you do not use `-v`**.
- Chrome `http://vsdc:8080/...` will fail (`NXDOMAIN`). That is expected. Use `http://localhost:8080/sandboxvsdc`.
- File the **VAT return** on the ZRA production portal for the period. Smart Invoice already sent the invoices; the return is the declaration and payment.

---

## If the shop PC dies

On the new PC: install Docker, copy the project, `.env` (same serial), WAR, and the two `.tgz` files. **Do not Initialize.**

```bash
cd ~/smartTrade-POS-Pro
docker compose up --no-start
docker compose stop

docker run --rm --volumes-from vsdc \
  -v "$(pwd)/backups:/backup" alpine \
  sh -c 'rm -rf /usr/local/tomcat/webapps/* && tar xzf /backup/vsdc_webapps-YYYYMMDD-HHMM.tgz -C /usr/local/tomcat/webapps'

docker run --rm --volumes-from smartTradePOS_db \
  -v "$(pwd)/backups:/backup" alpine \
  sh -c 'rm -rf /var/lib/mysql/* && tar xzf /backup/db_data-YYYYMMDD-HHMM.tgz -C /var/lib/mysql'

docker compose start db
docker compose start vsdc
docker compose start web
```

Replace `YYYYMMDD-HHMM` with your backup stamp. On **ZRA Smart Invoice**, SDC ID should match the old PC. Use **Test connection** only.

- **902** + SDC ID still present → keystore restored. Good.
- **899** or empty SDC ID → VSDC tar did not restore. Do **not** Initialize that serial again. Fix the restore or register a **new** production serial.

---

## Never do this

| Action | What happens |
|---|---|
| Set `ZRA_VSDC_URL=https://www.zra.org.zm` | POS cannot reach VSDC |
| Initialize production on your laptop, then send a blank VSDC to the shop | Serial is installed in the cloud; shop has no keys |
| Reuse the sandbox serial or sandbox `vsdc_webapps` volume | Wrong environment / dead serial |
| `docker compose down -v` | Deletes keystore and database volumes |
| Drop a new WAR on top of an initialized VSDC (byte-different file) | Tomcat redeploys and can wipe keys |
| Click Initialize again, or Headquarter modify/suspend/deactivate the device | Can kill the device on ZRA’s side |
| Sell without registering products | VSDC rejects the invoice |

---

## Several apps, one taxpayer

If smartTrade, a billing app, and a pharmacy app belong to the **same TPIN**, they should share **one** VSDC (`ZRA_VSDC_URL` pointing at that host). Two VSDC containers with the **same serial** will fight over the keystore.

Different companies / TPINs each need their own serial, WAR, volume, and one Initialize.

---

## Quick “am I live?” checks

1. `.env` serial matches **production** Device Management.
2. `ZRA_VSDC_URL` is still `http://vsdc:8080/sandboxvsdc`.
3. `ZRA_PORTAL_URL` is `https://www.zra.org.zm`.
4. ZRA admin shows SDC ID and MRC.
5. A real paid sale prints a QR that opens the **production** receipt page.
6. `backups/vsdc_webapps-*.tgz` exists somewhere that is not only that PC.
