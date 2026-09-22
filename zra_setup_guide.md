# smartTrade-POS ZRA Client Setup & Initialization Guide

This guide outlines the end-to-end process for deploying smartTrade-POS for a new client, specifically covering database sanitization, environment configuration, and ZRA Virtual Sales Data Controller (VSDC) initialization.

**1. Clone and Configure the Environment**
After cloning the repository and setting up the Docker containers, configure the client's `.env` file with their specific ZRA API credentials from the Sandbox/Live portal.

```ini
# ZRA Smart Invoice Configuration
ZRA_TPIN=client_TPIN_Here
ZRA_BHF_ID=Device_Brach_Code_Here
ZRA_DVC_SRL_NO=Device_Serial_Here
ZRA_VSDC_URL=http://vsdc:8080/sandboxvsdc

# Set 'ZRA_NON_VAT' to true for Turnover Tax (TOT) clients, false for standard VAT
ZRA_NON_VAT=true 

```

*Note: If `ZRA_NON_VAT` is changed after containers are running, use `docker-compose up -d` to refresh the environment variables without destroying volumes.*

**2. Database Sanitization (Crucial for Pre-populated Databases)**
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

**3. ZRA VSDC UI Initialization Workflow**
Once the database is clean and the `.env` is loaded, log into the POS web interface and navigate to the **ZRA Smart Invoice** module. Perform the following steps in exact order:

* **Test Connection:** Ensures the POS can reach the local Tomcat container.
* **Initialize Device:** Fetches the cryptographic keys (SDC ID and MRC No) for the active `ZRA_DVC_SRL_NO`. **Do this only once.** Re-initializing or wiping the Tomcat `vsdc_webapps` Docker volume will permanently burn the device serial.
* **Sync Standard Codes:** Downloads the latest ZRA dictionaries (taxes, units, payments).
* **Sync UNSPSC Classes:** Downloads the valid product classification hierarchy.
* **Register Products:** Pushes the local product catalog to the ZRA Gateway.

**4. Common Error Codes & Resolutions**

* **Error 904 (Unknown Action):** Usually indicates a routing failure in the POS payload mapping (e.g., targeting `item/saveItem` instead of `items/saveItem`), or attempting to register a product lacking an 8-digit Level-4 UNSPSC code.
* **Error 913 (Parameter Error):** Commonly caused by sending `vatCatCd` strings (like 'A' or 'B') when the TPIN is strictly registered for Non-VAT/Turnover Tax. Ensure `ZRA_NON_VAT=true` is applied.
* **Error 901/899 (Invalid Device / Client Error):** The local SQLite keystore inside the Tomcat container has been lost or doesn't match ZRA's records. Generate a new `STPOS001...` serial on the ZRA portal, update the `.env`, clear the `settings` table locks (Step 2E), and re-initialize.
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