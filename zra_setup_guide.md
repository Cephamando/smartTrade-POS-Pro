# smartTrade-POS ZRA Client Setup & Initialization Guide

This guide outlines the end-to-end process for deploying smartTrade-POS for a new client, specifically covering database sanitization, environment configuration, and ZRA Virtual Sales Data Controller (VSDC) initialization.

**1. Clone and start the containers**
After cloning the repository, copy `.env.example` to `.env` and set the database passwords and ports. Leave the ZRA lines at their defaults. Put the VSDC `.war` in `api/`, then start the containers. The device address stays `http://vsdc:8080/sandboxvsdc` when the `vsdc` container is on the same Docker network.

**2. Enter the device on ZRA invoices**
Sign in as the developer. Open **Office → ZRA invoices**. Enter the TPIN, branch, and device serial from ZRA Device Management, then click **Save device**. Tick **Turnover tax** only when that TPIN is not VAT-registered. For a live shop, set the receipt portal to `https://www.zra.org.zm`. You do not open `.env` for these values. A saved value on this page is what Initialize and the sync buttons use.

**3. Database sanitization (crucial for a copied database)**
If deploying a pre-populated database (e.g., migrating a catalog from another branch or test environment), you **must** purge all old transactional data, reset product sync flags, standardize unit codes to ZRA formats (e.g., `EA`), and wipe the old SDC keystore locks. Failing to do this will trigger cryptographic mismatches (Error 901) or routing errors (Error 904). Run this combined SQL script against the database container:

```bash
docker exec -i smartTradePOS_db mysql -f -usmartTradePOSUser -psmartTradePOS123! smartTradePOS_db << 'SQL_END'
SET FOREIGN_KEY_CHECKS = 0;

-- A. Clear Sales & Refunds
TRUNCATE TABLE sale_items;
TRUNCATE TABLE sales;
TRUNCATE TABLE refunds;
TRUNCATE TABLE refund_requests;
TRUNCATE TABLE pickup_notifications;

-- B. Clear Inventory & Stock Movements
TRUNCATE TABLE inventory;
TRUNCATE TABLE inventory_logs;
TRUNCATE TABLE location_stock;
TRUNCATE TABLE inventory_transfers;
TRUNCATE TABLE stock_transfers;
TRUNCATE TABLE stock_transfer_items;

-- C. Clear Purchasing & V-SIAS Receiving
TRUNCATE TABLE grv_items;
TRUNCATE TABLE grvs;
TRUNCATE TABLE receiving_dock_items;
TRUNCATE TABLE receiving_docks;
TRUNCATE TABLE vsias_sync_log;
TRUNCATE TABLE purchase_orders;
TRUNCATE TABLE purchase_order_items;
TRUNCATE TABLE purchase_inquiries;
TRUNCATE TABLE purchase_inquiry_items;

-- D. Reset Product Sync Status and Fix Invalid ZRA Unit Codes
UPDATE products SET zra_synced = 0, zra_item_cd = NULL;
UPDATE products SET unit = 'EA' WHERE unit IS NULL OR unit IN ('', 'unit', 'Units', 'PLATE', 'EACH');

-- Optional: Bulk assign a default UNSPSC code if catalog lacks them (e.g., Acoholic Drinks:5020220(1-10), Non-Alcoholic drinks:50202(	300-807), Meat:5011(1-2)xxx  Vegies..:5040xxxx)
UPDATE products SET zra_synced = 0, unspsc_code = '50202201' WHERE unspsc_code IS NULL OR unspsc_code = '';

-- Reset UNSPSC code
-- UPDATE products SET zra_synced = 0, unspsc_code = '', WHERE unspsc_code IS NULL OR unspsc_code = '';

-- E. Wipe Old ZRA Initialization Locks
UPDATE settings SET setting_value='' WHERE setting_key IN ('vsdc_sdcId', 'vsdc_mrcNo', 'vsdc_device_installed');

SET FOREIGN_KEY_CHECKS = 1;
SQL_END

```

**4. ZRA VSDC UI Initialization Workflow**
Once the database is clean, sign in as the developer and open **ZRA invoices**. Perform the following steps in this order:

* **Save device:** TPIN, branch, and device serial. Do this before Initialize.
* **Test connection:** The POS can reach the local Tomcat container.
* **Initialize device:** Fetches the cryptographic keys (SDC ID and MRC No) for the serial you just saved. **Do this only once.** Re-initializing or wiping the Tomcat `vsdc_webapps` Docker volume will permanently burn the device serial.
* **Sync standard codes:** Downloads the latest ZRA dictionaries (taxes, units, payments).
* **Sync UNSPSC classes:** Downloads the valid product classification hierarchy.
* **Register products:** Pushes the local product catalog to the ZRA Gateway.

**4. Common Error Codes & Resolutions**

* **Error 904 (Unknown Action):** Usually indicates a routing failure in the POS payload mapping (e.g., targeting `item/saveItem` instead of `items/saveItem`), or attempting to register a product lacking an 8-digit Level-4 UNSPSC code.
* **Error 913 (Parameter Error):** Commonly caused by sending VAT category codes when the TPIN is registered for turnover tax. Tick **Turnover tax** on ZRA invoices and save the device, then retry.
* **Error 901/899 (Invalid Device / Client Error):** The local keystore inside the Tomcat container has been lost or does not match ZRA's records. Register a new serial on the ZRA portal, enter it on ZRA invoices, save the device, clear the old SDC lock (step 3E), and initialize once.
* **Error 931 (Duplicate/Excess Credit):** Thrown when attempting to issue a credit note that exceeds the original fiscal invoice amount or if the original ZRA receipt number and SDC ID are missing from the payload.

**4. GitHub Configuration - CLI**
```bash





-- ignore large filetypes and folders
echo "*Zone.Identifier" >> .gitignore
echo "*.war" >> .gitignore
echo "api/sandboxvsdc-BU/" >> .gitignore
git rm --cached -r api/sandboxvsdc-BU/

-- Delete all existing identifier files from directories:
echo find . -name "*Zone.Identifier" -type f -delete

# Optional - If not already done, Find and remove all Zone.Identifier files from Git's tracking and the local drive:
find . -name "*zone.Identifier" -type f -exec git rm -f {} +

-- Add your updated .gitignore file so it gets committed
git add .gitignore

git commit -m "chore: remove large .war binaries from tracking and Remove Windows Zone.Identifier junk files"
git push
```

```
```