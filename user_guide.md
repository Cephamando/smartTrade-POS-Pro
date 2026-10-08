# smartTrade POS Pro — user guide

This guide is for people who sell, cook, count stock, buy, and close the day. Installation, Docker, and backups are in [README.md](README.md). ZRA go-live is in [ZRA_GO_LIVE.md](ZRA_GO_LIVE.md). Step-by-step training exercises, one per role, are in [TRAINING_MANUAL.md](TRAINING_MANUAL.md).

The name in the navbar is the business name from System Settings. The badge next to it is the license tier (`LITE`, `PRO`, `PRO+`, or `ENTERPRISE`). This shop is seeded as **enterprise**.

Open the POS in a browser at `http://localhost:8090` on the shop PC, or at the PC’s address on the local network (for example `http://192.168.x.x:8090`). Phones and other tills use that network address, not `localhost`.

---

## 1. Sign in and the shell

1. Open the address above. The login page asks for username and password.
2. After a successful login:
   - **Administrator, manager, and developer** land on the Dashboard.
   - **Chef and head chef** (Pro+ or Enterprise) land on Meal Production.
   - **Cashier, bartender, and waiter** land on the POS.
3. If the account is flagged to change its password, the Change Password screen blocks everything else until the new password is saved. New passwords must be at least 6 characters.
4. **Logout** is on the right of the navbar (or **Exit** on the POS for staff who are not managers).

The navbar (managers and above) has Dashboard, Inventory, Management, Kitchen Display (Pro+ and Enterprise), and Launch POS. The page you are on is highlighted in yellow: the top-level link gets an underline, the parent menu (Inventory or Management) lights up, and the exact entry inside the menu is marked. The chip with a pin is the current workstation. The chip with a bell is pending V-SIAS approvals and opens the approvals panel inside the page. The chip with a badge is your username and role.

**On a phone or tablet** (screens narrower than 1200px) the navbar collapses. A green **POS** button sits next to **Menu** so a cashier reaches the till in one tap. **Menu** opens a scrolling panel: Inventory and Management expand like an accordion (only one open at a time), and the panel closes itself after you pick a page. Workstation, user, and Logout become full-width chips at the bottom of the panel.

Critical actions open a confirm dialog (SweetAlert) before they run: deleting a user, ending a shift, sending a batch to the POS, pushing a sale to ZRA, clearing the cart, and similar steps. Adding an item, changing quantity, and moving between pages do not ask.

---

## 2. License tiers

The tier is stored in System Settings. Only a **developer** can change it. A higher tier includes everything below it.

| Tier | What the shop can use |
|---|---|
| **Lite** | POS, products, categories, stock levels, users, locations, reports, shifts, Z-Read. Food is marked collected at sale. No split pay, tabs, pickup, purchasing, or kitchen. |
| **Pro** | Lite, plus members, inventory audit, purchasing, vendors, receiving bay, transfers, pickup, open tabs, split pay, and “Got it / Later” on cart lines. |
| **Pro+** | Pro, plus kitchen display, meal production, menu and recipes, and table floor plan. Food and meal categories can be sent to the kitchen. |
| **Enterprise** | Pro+, plus ZRA Smart Invoice on the Management menu, ZRA tax class on products, Web Orders on the POS, and the Online payment method. Administrators and developers can also open ZRA on a lower tier. |

Opening a locked page shows “This feature requires Pro or higher” or “This feature requires Pro+ or Enterprise” and returns you to the Dashboard or the POS.

### License lockout

A **lockout date** in the past (set by a developer) puts the shop in read-only mode:

- Cashiers, waiters, bartenders, and chefs see a full-screen “License expired” page and can only sign out.
- Administrators and managers can still open reports, Z-Read, receipts, and printed shift reports. Saving changes is blocked.
- The developer account is not locked out.

Leave the lockout date blank for ongoing access.

---

## 3. Roles

Roles are chosen on Users & Staff. They control the home page and which menus exist. They are separate from the license tier: a chef on Lite still cannot open the kitchen.

| Role | Home | What they can do |
|---|---|---|
| **Developer** | Dashboard | Everything an administrator can do, plus System Settings (business name, receipt text, colours, license tier, lockout date), approving location edits, and editing other developer accounts. Location create, edit, and delete by anyone else needs this person’s username and password. |
| **Administrator** | Dashboard | Full shop menus: POS, inventory, purchasing, transfers, reports, Z-Read, ZRA, users, locations, shifts. Can switch workstation from the navbar. Can approve a shift start and authorize payouts and refunds. Cannot change the license. |
| **Manager** | Dashboard | Same shop menus as an administrator. Can approve shifts, payouts, and refunds. Location changes they save are queued until a developer approves them. Navbar workstation switch is for administrators and developers; managers change station from the POS location button when they are on a sellable till. |
| **Head chef** | Meal Production (Pro+ / Enterprise) | Kitchen display, meal production, menu builder, stock levels (view). Recipe consumption report. No POS, no purchasing, no user admin. |
| **Chef** | Meal Production (Pro+ / Enterprise) | Same kitchen screens as head chef. Recipe consumption is limited to administrators, managers, developers, and head chefs. |
| **Cashier** | POS | Sell, request a shift, stock requests (transfers), pickup. No dashboard. |
| **Bartender** | POS | Same screens as a cashier. Counted as a money role on the shift list (drawer expected). |
| **Waiter** | POS | Same screens as a cashier. Can be assigned on the pickup screen. Not treated as a drawer role on the shift money list. |

“Manager authorization” on payouts, refunds, and shift approval accepts an **administrator, manager, or developer** username and password. The person at the till types those credentials; they do not have to leave the screen.

Deleting a user removes the account when it has no history. If the person already has sales or stock movements, the account is deactivated instead. You cannot delete the account you are signed in with. Developer profiles can be edited only by a developer.

**Primary workstation** is the location that user belongs to. **All Locations (HQ)** means they are not pinned to one station. That choice is stored as no location, which is valid. Pick a real station when the person should always start at one bar, kitchen, or store.

---

## 4. Workstations (locations)

Locations are the physical places stock and sales belong to: Main Bar, Kitchen, warehouse, and so on.

**Inventory →** is not where you edit them. Open **Management → Locations**.

Each location has:

- **Name** and optional address or notes.
- **Type:** Store (sales point), Kitchen (production), or Warehouse (storage).
- **POS enabled:** the till can ring sales here. If this is off, Launch POS is disabled and the button reads “POS Disabled”.
- **Goods receiver:** this place can take vendor GRVs.

Adding, editing, or deleting a location requires a developer password unless you are already signed in as the developer. Edits made by an administrator or manager sit in a pending state (highlighted row) until the developer opens **Review Pending Edits** and approves or rejects them. The Management menu shows a warning badge while any edit is waiting.

The navbar pin (administrators and developers) switches the workstation used by the dashboard and stock screens. On the POS, the pencil next to the location name switches only among locations that can sell. If the POS has no station yet, it forces a choice before the till opens.

---

## 5. A selling day

### 5.1 Open a shift

The POS will not take sales until a shift is open at that workstation. The product area stays blurred.

1. Enter the **opening float** (cash in the drawer, in ZMW).
2. Confirm **Request approval**.
3. A manager, administrator, or developer enters their username and password and confirms **Approve**.

Until that approval, the screen says the shift is pending. **Go to Dashboard** is available from the start-shift box for people who have a dashboard.

### 5.2 Ring a sale

1. Search, or tap a category, then a product. Nested categories drill down; **Back** returns to the parent.
2. Out-of-stock sellable items are grey and cannot be added. The till will not sell more than this station’s on-hand quantity. Recipe meals and raw materials follow their own rules (see Kitchen).
3. In the cart, use **+** and **−** for quantity. **Clear** asks before wiping the order.
4. **Charge**, enter the amount tendered, and **Complete transaction**. Change is shown when cash tendered is higher than the total.
5. The receipt opens for printing. It shows the business name, optional header and footer, line items, payments, and — after ZRA fiscalizes the sale — SDC ID, receipt number, and QR.

**Customer name** defaults to Walk-in. Type a name when the receipt should say who it was.

**On-screen keyboard** is on by default (the yellow Keyboard button). Turn it off on a till that has a hardware keyboard.

**In-stock** filter hides products that have no quantity at this station, so the grid shows only what you can actually sell.

**Lost stock** (Pro and above) writes off what is in the cart as waste instead of selling it: breakages, spoiled food, a spilt drink. It needs manager authorization. Stock goes down, no sale is recorded, and the audit trail shows a waste row.

**Payout** (while a shift is open) records cash taken from the drawer. It needs an amount, a reason, and manager authorization. The amount comes off the expected drawer total immediately.

**X-Read** prints the current shift without closing it.

**End** closes the shift. You count the drawer; the station cannot sell again until a new shift is approved.

### 5.3 Payments

Single pay methods:

- Cash, Card, MTN Money, Airtel Money, Zamtel Money
- **Pending** (Pro and above) — put the order on a tab instead of taking money now
- **Online** (Enterprise) — online delivery app

**Split pay** (everything above Lite) lets you add several methods. One row can be **Other** with a name you type (bank transfer, cheque, voucher).

**Tip** accepts an amount or 5%, 10%, or 15% of the total.

### 5.4 Members (Pro and above)

Register people under **Management** is not the member list. Open the members screen (linked from the dashboard staff area and from `index.php?page=members`).

- Register with name and phone. The phone is the member’s ID and cannot be duplicated.
- Edit the profile, or open **History** for past activity.
- Points balance is shown on the list.

On the POS, attaching a member fills the customer name and offers a **10% off** switch at checkout.

### 5.5 Tabs, tables, and pickup (Pro and above)

**Tabs** holds an unpaid order. From the cart, add the order to an existing tab or a new one. You can name it and, on Pro+ and Enterprise, assign a table. **Transfer / merge** moves selected lines from one tab to another.

**Tables** (Pro+ and Enterprise) opens the floor plan. Tables are green when free and red when occupied. A waiter can open their own table; a manager can open any occupied table. Define tables first under **Management → Table Settings**: location, zone (Patio, Inside), table name, and seat count.

**Got it / Later** appears on food and meal lines when the tier is Pro+. **Got it** means the guest has the item. **Later** sends it toward the kitchen and the pickup queue. On Lite, those lines are simply marked collected.

**Pickup** (POS button, dashboard button, or the pickup screen) lists items that the kitchen marked ready and that are still uncollected. Mark them collected there. The red badge counts ready, uncollected items at this station.

### 5.6 Web orders (Enterprise)

**Web Orders** lists online tabs waiting at this till. Open one to pull it into the sale flow. The badge counts how many are waiting.

### 5.7 Returns

Use **Return** on the POS only against the original paid invoice. The return must name the original sale line. A manager authorizes it and you pick a **ZRA refund reason**. The system records a credit note (a negative sale) and will not credit more quantity than the original invoice still has left, including earlier credits that already cite that receipt.

Empty or zero-amount tickets are not invoices and are not sent to ZRA.

Managers can also refund from **Master Reports → Sales Ledger** by opening the sale. That path is limited to administrators, managers, and developers.

---

## 6. Kitchen (Pro+ and Enterprise)

### 6.1 Categories that drive the kitchen

**Inventory → Categories.** Each category has a routing type:

| Type | Effect |
|---|---|
| Drink / Beverage | Sold as a drink. Does not go to the kitchen pass as food. |
| Food / Meal | Can be fired to the kitchen and can show Got it / Later. |
| Raw Ingredients | Stock used in recipes. Hidden from a normal sell if the product is also marked raw. |
| Other / General | General goods. |

Nest a category under a parent (Lagers under Beverages) to build the POS drill-down. A category that still has products or subcategories cannot be deleted.

### 6.2 Menu and recipes

**Menu & Recipe Builder** (chefs, and managers on the POS Menu button):

1. Add the sellable dish: name, food category, selling price.
2. Save the recipe: each ingredient, and how much one portion uses.
3. Delete a menu item only when you mean to remove that dish.

Raw food cannot be produced straight onto a till. Put it in a recipe, then produce the finished dish.

### 6.3 Meal production

**Meal Production** (Recipe/Prep Log for chefs):

1. Pick the finished dish.
2. Choose the POS workstation that should receive it.
3. The card shows how many portions are **already on hand** at that station (green) or **None on hand** (red). Use that to decide the batch size.
4. Enter the quantity and confirm **Produce & send**.

Ingredients are deducted from kitchen stock. Finished portions are added at the chosen till.

### 6.4 Kitchen display

**Kitchen Display** is a dark full-screen board. It polls for tickets.

- Red header: new (pending).
- Yellow header: at least one line is cooking.
- **Cook** moves a line from pending to cooking.
- **Done** marks it ready for pickup.
- Each of those asks you to confirm.
- The ticket shows the order number, who sent it, and how many minutes it has been up.
- “All orders clear” means nothing is waiting.

### 6.5 Recipe consumption

**Management → Recipe Consumption** (administrators, managers, developers, head chefs). Filter by date range and location. It shows ingredients used by production in that period, which is separate from the sales ledger.

---

## 7. Products and stock

### 7.1 Product catalog

**Inventory → Products & Menu.**

- Add or edit a product: name, optional SKU, category, unit, type (**Item** or **Service**), cost, selling price.
- **Sellable on POS** shows it on the till. **Raw material (hidden)** keeps it off the till for use in recipes and purchasing.
- **Active / Disabled** hides a product without deleting it.
- Enterprise shows **ZRA tax class**: A standard 16%, B zero-rated, C exempt, D excise. Other tiers store class A.
- **UNSPSC** is the 8-digit class ZRA expects. Search by title or code and pick a level-4 code. Family codes are rejected by ZRA. A blank code is not a safe default for a new kind of product.
- **Import CSV** loads many products at once.
- Filters cover category, type, tax class (enterprise), UNSPSC, and status. **Print** prints the catalog.
- Price `0.00` items can be open-price: the till asks for the amount when you tap them.

### 7.2 UNSPSC codes

**Inventory → UNSPSC Codes.** Import the classification workbook (or the bundled file) and assign codes to products. You can also assign a code from the product form. Sync from ZRA is covered in section 11.

### 7.3 Stock levels

**Inventory → Stock Levels.**

- Filter by location and search.
- **Total stock value** is cost times quantity for the rows on screen.
- Enter a **physical count** and a reason, then **Finish & update stock**. The adjustment is applied to live stock, so sales that happen during the count are kept. Quantity is not taken below zero.
- **Print stock** makes a count sheet. **Download CSV** exports the grid.
- Chefs can open this screen to look. They do not post the count.
- **Add product** jumps back to the catalog.

### 7.4 Inventory audit trail

**Management** does not hide this; the page is **Inventory Audit Trail** (`index.php?page=audit`, Pro and above).

Filter by date and action type (sale, produce, transfer, GRV, stock take, and the other types stored on the log). Print the log. This is the stock history. Each row is who changed which product, at which location, by how much, and what the quantity was afterwards.

---

## 8. Purchasing (Pro and above)

**Inventory → Vendors** first: name, contact person, phone. Deleting a vendor asks for confirmation.

Purchasing has three tabs.

### 8.1 Purchase inquiry

A request before you commit to a supplier.

- Location, optional vendor, needed-by date, status (draft and the other statuses on the form), notes, and lines (product and quantity).
- **Open** an existing inquiry to edit it.
- **Convert** turns it into a purchase order.
- **Cancel** marks it cancelled.

### 8.2 Purchase orders

- Vendor (required), location, your reference number, notes, and lines.
- Save, or cancel an order that should not be received.
- **Receive** opens Goods Received with that order loaded.

### 8.3 Goods received (GRV)

Posting a GRV adds the accepted quantities to stock at the receiving location and writes a stock log. You need a location that is allowed to receive goods.

- Receive against a purchase order, or receive lines that arrived without one.
- Enter the supplier reference.
- If the consignment came from the receiving bay, the dock is attached and cleared when the GRV posts.

### 8.4 Receiving bay

V-SIAS inspections that are ready for the shop land here as docked consignments.

- The list shows tracking code, source, line count, and status. The navbar and this page badge show how many are waiting.
- Open a dock, check the lines, and flag anything that is not a normal catalog item.
- **Send to GRV** continues into Goods Received so stock is actually booked.
- **Cancel dock** drops the consignment from the bay. Both actions ask you to confirm.

---

## 9. Transfers and V-SIAS

**Inventory → Transfer Stock** (cashiers see this as **Stock request**).

Three tabs:

| Tab | Meaning |
|---|---|
| Incoming (To Receive) | Stock dispatched toward your station. Badge counts rows waiting. |
| Outgoing (To Dispatch) | Requests other stations sent you. You still have to send the goods. |
| My Pending Requests | Requests you created that have not been dispatched. |

**New requisition:** destination is your need, source is who should supply it, product, and quantity. Stock is not moved at request time.

**Dispatch** (outgoing): you confirm the goods leaving your stock. On a V-SIAS dispatch, the inspection asks for **Issuer** and **Checked by**, plus a dispatch note and seal photo. Deliverer and receiver are not collected at dispatch.

**Accept stock** (incoming): opens the V-SIAS inspection in the page (on the shop PC) or in the same browser tab (on a phone or another device). Receive stays locked while the inspection is waiting for approval. After it is signed, the receiver confirms and stock is added at the destination. Receive collects **Deliverer** and **Receiver**.

**Cancel** stops a transfer that should not continue.

**Approvals** (bell, dashboard button, and Management → Site inspection) opens V-SIAS Pro’s pending list in a panel over the current page for administrators, managers, and developers. The badge is the number still unsigned. The first time, V-SIAS asks you to sign in with your V-SIAS account. If the panel stays blank or says the content is blocked, use **Open in new tab**.

V-SIAS itself runs separately. On the shop PC it is on port 8095; on the hosted system it is `vsias.odeliazm.com`. The POS only opens it and receives the approved handoff.

---

## 10. Shifts, X-Reads, and Z-Read

### 10.1 Shifts & X-Reads

**Management → Shifts & X-Reads.**

- Money roles (cashier, bartender, manager, administrator, developer) see their own shift: opening float, sales, payouts, expected drawer.
- Managers also get **Master Shift Reports**: every shift, filterable by date and staff member. Open a shift to see its paid sales.
- Starting and ending a shift can also be done from this page, with the same approval rules as the POS.
- **Print shift** is the paper X-Read for one shift.

### 10.2 End of day (Z-Read)

**Management → End of Day (Z-Read).**

Pick the date, time from, time to, and one workstation, then **Filter**. The report is the day’s takings for that window: totals by payment method, sales, and the figures you print for the close. **Print Z-Read** prints only the report.

Z-Read is the shop’s end-of-day record. It is not the ZRA VAT return.

---

## 11. ZRA Smart Invoice (Enterprise)

**Management → ZRA Smart Invoice.** The POS talks to a local fiscal device (VSDC) on this PC. That device signs each paid sale and sends it to ZRA. Filing the VAT return is still done later on the ZRA portal.

The top of the page shows whether the local service answers, how many paid sales are still unfiscalized, how many are fiscalized, and how many products are not registered.

### 11.1 Device card

You will see TPIN, branch ID, device serial (from the shop’s configuration, matching ZRA Device Management), taxpayer name, **SDC ID**, **MRC number**, last invoice number, standard-code count, and UNSPSC count.

| Control | When to use it |
|---|---|
| **Test connection** | Any time you need to prove the device still has keys and can reach ZRA. Safe to repeat. |
| **Initialize device** | Once, on a brand-new serial, when the badge says **Not initialized**. The button is disabled after an SDC ID exists. Initializing again, or doing it on a second PC with the same serial, burns the device. |
| **Sync standard codes** | After the first successful handshake, before you register items. |
| **Sync UNSPSC classes** | After standard codes. You can also import the UNSPSC workbook from the UNSPSC page. |
| **Register products** | After every sellable item has a level-4 UNSPSC and the correct tax class. Posts the catalog to the device. |
| **Retry failed / pending** | Sends the unfiscalized queue again. |
| **Push** on one row | Fiscalizes that invoice now. Use it for a sale that failed. Do not push a sale that was already accepted under another receipt. |

A successful test with the same SDC ID you had before means the keys are still there. An empty SDC ID after the device was previously initialized means the local keystore is gone. Do not click Initialize on that serial. Restore the backup or register a new serial (see the go-live guide).

**Reading the status card and the red/amber message**

| Message | Meaning | What to do |
|---|---|---|
| Local VSDC is reachable (green) | The fiscal device answers. | Nothing. |
| Tomcat is up, but the Smart Invoice app is not deployed (404) | The device software is running but the Smart Invoice program inside it is missing. | Call support. The WAR file must be placed in the `api` folder and the `vsdc` container restarted. Do not click Initialize. |
| Local VSDC unreachable / CURL error (999) | The device container is stopped. | Call support to start the `vsdc` container. |
| 902 This device is installed | ZRA already knows this serial. Normal after the first handshake. | Carry on. If **Sync standard codes** then gives 899, see the go-live guide. |
| 899 | The device has no API keys for this serial. | A new serial must be registered on the ZRA portal. Do not retry Initialize. |
| 910 Parameter error | A field ZRA did not like, most often a customer TPIN or a class code. | Read the hint under the message. Fix the product or the customer, then Push again. |
| 931 | A credit note would exceed the original invoice. | Do not retry. Refund against the right receipt. |

### 11.2 What fiscalizing does

Paid sales fiscalize on their own. The unfiscalized list is the exception queue: pending or failed, with the last error under the status badge.

Recent fiscal receipts show sale number, ZRA receipt number, SDC ID, total, a receipt link, and the QR when one was stored.

Credit notes must point at the original fiscalized receipt. The QR on a fiscal receipt opens the ZRA portal page for that invoice.

### 11.3 One device

One serial is one keystore and one receipt sequence. Keep a single live till PC on that serial. A second machine restored from the same backup is the same device and will collide with the first on receipt numbers.

---

## 12. Reports

**Management → Master Reports.**

**Filter bar** (top of the page):

- **Date range.** Tap the dates to open a calendar and pick a start and end day. The **‹** and **›** arrows shift the whole range one day back or forward. The report reloads as soon as the range changes.
- **All stores** or one workstation.
- **All employees** or one cashier. Receipts and Taxes match on the cashier who rang the sale; Items and Categories match on the user who owns the sale.
- **Export** prints the current tab with a header (category, period, generated time).

**Summary tiles** under the filter bar: **Gross sales**, **Refunds**, **Discounts**, **Net sales**, **Gross profit**. They recalculate from the rows the filter returned. Net sales is paid sales; refunds are credit notes and refunded tickets; gross sales is the two added back together. Gross profit is an estimate at 45% of net sales when product cost is not in the figures, so treat it as a guide, not an accounting number.

**Tabs**

| Tab | What it lists | Tap a row |
|---|---|---|
| **Receipts** | Each sale in the period: receipt number, date and time, employee, store, total. Refunds carry a red **Refund** badge. | Opens the line breakdown (item, qty, total; voided and refunded lines are labelled). **View** opens the printable receipt. The red arrow icon starts a refund (administrators, managers, developers; paid sales with a positive total only). |
| **Items** | Each product sold: name, SKU, items sold, gross sales. Sorted by revenue. | — |
| **Categories** | Each category: items sold, gross sales. Products without a category show as Uncategorized. | — |
| **Taxes** | VAT at 16%: taxable sales and tax amount, worked back from paid totals. | Opens the tax drilldown: every receipt in the period with its taxable amount and tax. **Print Breakdown** prints only that list. |

Each table has a **Filter records** search box and pages of 50 rows.

**On a phone** the filter bar stacks, the five tiles form a two-column grid, the tab pills scroll sideways, and each table row becomes a card with labelled values and full-width buttons. The receipt and breakdown windows open full screen.

Reports show the shop’s sales ledger. They are not the ZRA VAT return; that is filed on the ZRA portal from the invoices Smart Invoice already sent.

The dashboard charts (sales over recent days, payment mix, active shifts) are a short view of the same shop. Today’s sales and transaction counts link here.

---

## 13. Dashboard

Administrators, managers, and developers.

- **Today’s sales** and **transactions** for the workstation you are on. Both open Master Reports.
- **Unpaid tabs** (Pro and above). Opens the POS when this station can sell.
- **Low stock** opens Stock Levels.
- **Inventory** shortcuts: Receiving Bay, Goods Received, Manage Products, Stock Audit.
- **Kitchen & menu** shortcuts on Pro+ and Enterprise: KDS, Meal Production, Menu Builder.
- **Staff & users** shortcuts: Manage Users, View Shifts, Members.
- **7-day sales trend** and **payment mix** charts.
- **Active shifts**: who is open, their role, and a button into that shift’s sales.
- **Pickup screen** and **Approvals** when your role and tier include them.
- **Go to POS** when this location can sell.

On a phone the stat tiles scroll sideways and the buttons stretch to full width.

---

## 14. Users, passwords, and appearance

### 14.1 Users & Staff

**Management → Users & Staff.**

Add a person with full name, username, role, primary workstation, and password. Edit the same fields later. Leave the password blank on edit to keep the current one. Delete asks for confirmation (see section 3 for deactivate versus delete).

### 14.2 Change password

Any signed-in user can open Change Password. Enter the current password, the new one, and a confirmation. When the system forced the change, Cancel is hidden until it is updated.

### 14.3 System Settings (developer)

**Management → System Settings.**

- **Business name** — navbar and receipt title.
- **Receipt header** — extra lines (TPIN, slogan, address).
- **Receipt footer** — the thank-you line.
- **Colours** — main header, accent (borders and icons), and cart header. The POS picks these up on the next load.
- **License tier** and **lockout date** — see section 2. A lockout date in the past turns on read-only mode as soon as you save.

---

## 15. Who uses which screen

| Screen | Developer | Admin | Manager | Head chef | Chef | Cashier, bartender, waiter |
|---|---|---|---|---|---|---|
| Dashboard | Yes | Yes | Yes | | | |
| POS | Yes | Yes | Yes | | | Yes |
| Products, categories, UNSPSC | Yes | Yes | Yes | | | |
| Stock levels | Yes | Yes | Yes | View | View | |
| Purchasing, vendors, receiving bay, transfers | Yes | Yes | Yes | | | Transfers only (stock request) |
| Kitchen, KDS, menu | Yes | Yes | Yes | Yes | Yes | |
| Tables setup | Yes | Yes | Yes | | | |
| Reports, Z-Read, shifts | Yes | Yes | Yes | | | Own shift actions from the POS |
| Recipe consumption | Yes | Yes | Yes | Yes | | |
| ZRA | Yes | Yes | Enterprise menu | | | |
| Users, locations | Yes | Yes | Yes | | | |
| System settings, license | Yes | | | | | |
| Members, audit trail | Yes | Yes | Yes | | | |
| Pickup | Yes | Yes | Yes | | | Yes |

Empty cells mean that role is sent back to their home page if they open the address directly. Kitchen rows also require Pro+ or Enterprise. Purchasing, members, audit, transfers, and pickup require Pro or above. ZRA’s menu entry is Enterprise, except administrators and developers, who can open it on any tier.

---

## 16. Common sequences

**New cashier, first sale**

1. Administrator creates the user as Cashier on the correct station.
2. Cashier signs in, enters the float, and waits.
3. Manager approves the shift.
4. Cashier sells. Stock on that station goes down. The receipt prints.
5. On Enterprise, the paid sale fiscalizes. If it stays on the ZRA pending list, a manager uses Push or Retry after reading the error.

**Lunch service**

1. Head chef checks on-hand at the bar, produces the short dishes, and sends them.
2. Waiter rings food as **Later**. The KDS shows the ticket.
3. Cook taps Cook, then Done.
4. Waiter opens Pickup and collects the plate.
5. Waiter charges the tab. Split pay if the table pays two ways.

**Stock from a supplier**

1. Inquiry, then convert to a purchase order, or start the order directly.
2. When the goods arrive, Receive on that order, or send a V-SIAS dock from the receiving bay into a GRV.
3. Stock levels at the receiving location increase. The audit trail shows the GRV.

**Move stock to the bar**

1. Bar cashier creates a requisition from the store to the bar.
2. Store dispatches it (Issuer and Checked by, if V-SIAS is in the path).
3. Bar accepts it after the inspection is approved. Bar on-hand goes up. Store on-hand already went down at dispatch.

**Close of day**

1. Each station ends its shift and counts the drawer.
2. Manager prints the Z-Read for the date and workstation.
3. Manager checks ZRA for a clear unfiscalized list.
4. VAT is declared later on the ZRA portal. Smart Invoice has already sent the invoices.
