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
4. **Log out** is in the account menu under your name (or **Exit** on the POS for staff who are not managers).

The navbar for managers is four words: **Dashboard**, **Stock**, **Kitchen** (Pro+ and Enterprise), and **Office**, plus a green **POS** button. Stock, Kitchen, and Office open short grouped lists (Catalog, On hand, Buying, and so on) instead of one long menu. The page you are on is highlighted in yellow: the top-level link gets an underline, the parent menu lights up, and the exact entry inside the menu is marked. The pin chip is the current workstation. A bell, when V-SIAS is on, opens pending approvals. Your name opens the account menu: role, Training, and Log out.

**On a phone or tablet** (screens narrower than 1200px) the navbar collapses. A green **POS** button sits next to **Menu** so the till is one tap away. **Menu** opens a scrolling panel. Stock, Kitchen, and Office expand like an accordion (only one open at a time), and the panel closes itself after you pick a page. Workstation and the account menu sit at the bottom of the panel.

Chefs see **Kitchen display**, **Prep**, **Menu**, and **Stock**. A head chef also sees **Recipe use**. Cashiers and waiters see **Stock request** when they are not on the POS.

Critical actions open a confirm dialog (SweetAlert) before they run: deleting a user, ending a shift, sending a batch to the POS, pushing a sale to ZRA, clearing the cart, and similar steps. Adding an item, changing quantity, and moving between pages do not ask.

---

## 2. License tiers

The tier is stored in System Settings. Only a **developer** can change it. A higher tier includes everything below it.

| Tier | What the shop can use |
|---|---|
| **Lite** | POS, products, categories, stock levels, users, locations, reports, shifts, Z-Read. Food is marked collected at sale. No split pay, tabs, pickup, purchasing, or kitchen. |
| **Pro** | Lite, plus members, inventory audit, purchasing, vendors, receiving bay, transfers, pickup, open tabs, split pay, and “Got it / Later” on cart lines. |
| **Pro+** | Pro, plus kitchen display, meal production, menu and recipes, and table floor plan. Food and meal categories can be sent to the kitchen. |
| **Enterprise** | Pro+, plus ZRA invoices on the Office menu, ZRA tax class on products, Web Orders on the POS, and the Online payment method. Administrators and developers can also open ZRA on a lower tier. |

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

Till approval on payouts, refunds, voids, and shift approval accepts an **administrator, manager, or developer** username and **approval code**. A head chef can approve a shift with their own code. The code is not the login password. Set it on **Change Password**, or when the account is created under the account menu → **Staff**. An office account (administrator, manager, or developer) also enters a 6-digit code from an authenticator app after the password. That sign-in code is set the first time they sign in. Recovery codes are shown once. The account menu → **Staff → Reset code** clears it so the next sign-in starts again. An office session ends after 30 minutes with no activity. A till session ends after 8 hours. Changing a password signs out every other browser still using that account.

Deleting a user removes the account when it has no history. If the person already has sales or stock movements, the account is deactivated instead. You cannot delete the account you are signed in with. Developer profiles can be edited only by a developer.

**Primary workstation** is the location that user belongs to. **All Locations (HQ)** means they are not pinned to one station. That choice is stored as no location, which is valid. Pick a real station when the person should always start at one bar, kitchen, or store.

---

## 4. Workstations (locations)

Locations are the physical places stock and sales belong to: Main Bar, Kitchen, warehouse, and so on.

**Stock →** is not where you edit them. Open **Office → Locations**.

Each location has:

- **Name** and optional address or notes.
- **Type:** Store (sales point), Kitchen (production), or Warehouse (storage).
- **POS enabled:** the till can ring sales here. If this is off, the POS button is disabled and reads “Disabled”.
- **Goods receiver:** this place can take vendor GRVs.

Adding, editing, or deleting a location requires a developer password unless you are already signed in as the developer. Edits made by an administrator or manager sit in a pending state (highlighted row) until the developer opens **Review Pending Edits** and approves or rejects them. The Office menu shows a warning badge while any edit is waiting.

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

- **Cash**
- **Flutterwave (Card / Mobile Money)** when Payments is switched on. Card and mobile money are taken here, not as separate buttons.
- **Online Delivery App** (Enterprise)
- **Put on Tab** (Pro and above) — the order waits instead of taking money now

**Split pay** (everything above Lite) starts with a cash line and a Flutterwave line. Add another line for cash, Flutterwave, the delivery-app method, or **Other** with a name you type (bank transfer, cheque, voucher). The status beside the heading shows whether the lines are short, cover the bill, or leave change. The Flutterwave panel under the lines charges only the Flutterwave amount, and **Complete transaction** stays locked until Flutterwave confirms that portion.

**Tip** accepts an amount or 5%, 10%, or 15% of the total.

**Surcharge** adds an extra charge to the bill with a reason — for example a guest who finishes a meal and then asks for it to be packed. Pick the reason from the drop-down in the payment window; each reason carries its own default amount or percentage, which you can change for this sale. **Other** lets you type a reason. The surcharge prints on the receipt as its own line, is included in the ZRA invoice, and is listed by reason in Master Reports. Administrators maintain the list under **Office → Settings**, on the POS surcharges tab (name, default amount or percent, order, active).

**Flutterwave.** Choose it, pick **MTN**, **Airtel**, or **Zamtel**, type the number the guest will pay from, and tap **Send MoMo prompt**. Or tap **Card / other** to open the Flutterwave window. The sale completes once Flutterwave confirms. When test keys are in use the panel shows **TEST MODE**.

The receipt then prints the Flutterwave detail: the **network**, the **number** paid from, and the **network transaction ID** (the reference on the customer's phone message). If the network does not send its own ID, the receipt shows Flutterwave's transaction ID instead. A card payment prints the card type and last four digits with that transaction ID. The same lines appear on the **X-Read**, the **Z-Read**, and **Master Reports** (the Payment column on Receipts, and the breakdown when you open a receipt).

**Foreign currency** — tap **Paying with foreign cash?** in the payment window, pick USD, ZAR, EUR, GBP (or any currency enabled under Currency settings), and type the amount the guest hands over. The kwacha equivalent fills in as the tendered amount and change is shown in ZMW. Rates come from a live feed and are refreshed on the schedule set in **Payments & Currency**; the amount due is also shown in the enabled currencies for reference.

**Tip after payment.** When a guest leaves a tip after the bill has already been paid, use the green **Tip** button in the POS toolbar (also **Tip a receipt** in the POS menu; waiters use the same POS screen). Search for the receipt by number, ZRA invoice number, customer, amount, or the staff member who served, tap it, enter the tip (or 5% / 10% / 15% / round up), choose how it was paid and who it goes to, and save. The receipt and its ZRA invoice do not change. Cash tips are counted in the shift's drawer; all tips show on the X-Read, the Z-Read, the shift print, and the **Tips & Surcharges** report tab.

### 5.4 Members (Pro and above)

Register people under **Management** is not the member list. Open the members screen (linked from the dashboard staff area and from `index.php?page=members`).

- Register with name and phone. The phone is the member’s ID and cannot be duplicated.
- Edit the profile, or open **History** for past activity.
- Points balance is shown on the list.

On the POS, attaching a member fills the customer name and offers a **10% off** switch at checkout.

### 5.5 Tabs, tables, and pickup (Pro and above)

**Tabs** holds an unpaid order. From the cart, add the order to an existing tab or a new one. You can name it and, on Pro+ and Enterprise, assign a table. **Transfer / merge** moves selected lines from one tab to another.

**Tables** (Pro+ and Enterprise) opens the floor plan. Tables are green when free and red when occupied. A waiter can open their own table; a manager can open any occupied table. Define tables first under **Office → Tables**: location, zone (Patio, Inside), table name, and seat count. The same page sets the reservation fee, how many minutes a paid table hold lasts, and what percentage of that fee is refundable (0 means non-refundable). It also prints one QR code for the online menu, `https://queenbeesrestaurants.com/index.php?page=shop&view=menu`. In the bag, the guest picks a store and then a free table from the dropdown. Choosing a table asks them to add the reservation surcharge. Accepting adds it to the bill and turns delivery off. They can remove the fee before paying, or clear the table if the order should be delivered. The web order is filed against that table.

**Got it / Later** appears on food and meal lines when the tier is Pro+. **Got it** means the guest has the item. **Later** sends it toward the kitchen and the pickup queue. On Lite, those lines are simply marked collected.

**Pickup** (POS button, dashboard button, or the pickup screen) lists items that the kitchen marked ready and that are still uncollected. Mark them collected there. The red badge counts ready, uncollected items at this station.

When the kitchen presses **Done**, every open POS and back-office screen at that station shows a green bar at the top: the order number, the table or customer, and the dishes. A three-note chime repeats for up to 60 seconds. **Quiet** stops the sound and leaves the bar. **Dismiss** hides that order until something new on it is ready. Tap the order name to open Pickup, or Web Orders when the ticket is an online order. A refresh does not sound the same dishes again. The kitchen display itself stays silent, because that is the screen that just marked the food ready.

### 5.6 Web orders (Enterprise)

**Web Orders** lists online orders only after Flutterwave has confirmed the payment. An order that is still waiting for payment stays on the customer’s phone and does not reach the kitchen. **Cancel order** stops a pending web order. A paid order needs an approval code. The list then shows **Cancelled by customer** or **Cancelled by till**, and that order stays until Flutterwave pays the funds out. Cancelling a paid order also files a ZRA credit note. If that posting does not go through, **Post ZRA credit note** tries again. A table reservation has no **Hand Over** button. It leaves the list when the hold time saved under **Office → Tables** runs out, unless it was cancelled and the funds are not paid out yet. The badge counts how many are waiting.

### 5.7 Returns

Use **Return** on the POS only against the original paid invoice. The return must name the original sale line. A manager authorizes it and you pick a **ZRA refund reason**. The system records a credit note (a negative sale) and will not credit more quantity than the original invoice still has left, including earlier credits that already cite that receipt.

Empty or zero-amount tickets are not invoices and are not sent to ZRA.

Managers can also refund from **Master Reports → Sales Ledger** by opening the sale. That path is limited to administrators, managers, and developers.

---

## 6. Kitchen (Pro+ and Enterprise)

### 6.1 Categories that drive the kitchen

**Stock → Categories.** Each category has a routing type:

| Type | Effect |
|---|---|
| Drink / Beverage | Sold as a drink. Does not go to the kitchen pass as food. |
| Food / Meal | Can be fired to the kitchen and can show Got it / Later. |
| Raw Ingredients | Stock used in recipes. Hidden from a normal sell if the product is also marked raw. |
| Other / General | General goods. |

Nest a category under a parent (Lagers under Beverages) to build the POS drill-down. A category that still has products or subcategories cannot be deleted.

### 6.2 Menu and recipes

**Kitchen → Menu & recipes** (chefs open **Menu**; managers can also use the POS Menu button):

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

**Kitchen → Kitchen display** opens a dark full-screen board. Chefs open the same screen from **Kitchen display** in their navbar. It polls for tickets.

- Red header: new (pending).
- Yellow header: at least one line is cooking.
- **Cook** moves a line from pending to cooking.
- **Done** marks it ready for pickup. The pass and the tills then show the ready bar and play the chime described under Pickup. The kitchen display does not chime.
- Each of those asks you to confirm.
- The ticket shows the order number, who sent it, and how many minutes it has been up.
- “All orders clear” means nothing is waiting.

### 6.5 Recipe consumption

**Kitchen → Recipe use** (administrators, managers, developers, and head chefs). Filter by date range and location. It shows ingredients used by production in that period, which is separate from the sales ledger. **Explain these figures** opens a window that reads the highest ingredient quantities.

---

## 7. Products and stock

### 7.1 Product catalog

**Stock → Products.**

- Add or edit a product: name, optional SKU, category, unit, type (**Item** or **Service**), cost, selling price.
- **Sellable on POS** shows it on the till. **Raw material (hidden)** keeps it off the till for use in recipes and purchasing.
- **Active / Disabled** hides a product without deleting it.
- Enterprise shows **ZRA tax class**: A standard 16%, B zero-rated, C exempt, D excise. Other tiers store class A.
- **UNSPSC** is the 8-digit class ZRA expects. Search by title or code and pick a level-4 code. Family codes are rejected by ZRA. A blank code is not a safe default for a new kind of product.
- **Import CSV** loads many products at once.
- Filters cover category, type, tax class (enterprise), UNSPSC, and status. **Print** prints the catalog.
- Price `0.00` items can be open-price: the till asks for the amount when you tap them.

### 7.2 UNSPSC codes

**Stock → UNSPSC codes.** Import the classification workbook (or the bundled file) and assign codes to products. You can also assign a code from the product form. Sync from ZRA is covered in section 11.

### 7.3 Stock levels

**Stock → Stock levels.**

- Filter by location and search.
- **Total stock value** is cost times quantity for the rows on screen.
- **Explain these figures** writes a short note from the lines on screen (counts, zeros, and the lowest quantities), using the Gemini key under Settings → Guide. The note treats the value it is given as selling price times quantity. It is not a forecast.
- Enter a **physical count** and a reason, then **Finish & update stock**. The grid updates in place. The adjustment is applied to live stock, so sales that happen during the count are kept. Quantity is not taken below zero.
- The pencil saves that row's quantity and selling price in place. The price belongs to the product, so every location of that item on the screen updates.
- **Print stock** makes a count sheet. **Download CSV** exports the grid.
- Chefs can open this screen to look. They do not post the count.
- **Add product** jumps back to the catalog.

### 7.4 Inventory audit trail

Open it from **Stock → History** (Pro and above). The page title is Inventory Audit Trail.

Filter by date and **Action type**. The list is the real stock movements: Sale, Online sale, Refund, Goods received, Transfer in, Transfer out, Adjustment, Stock take, Production, Recipe deduction, Waste, plus any other type that has been written to the log. Print the log. Each row is who changed which product, at which location, by how much, and what the quantity was afterwards.

---

## 8. Purchasing (Pro and above)

**Stock → Vendors** first: name, contact person, phone. Deleting a vendor asks for confirmation.

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
- Enter the supplier reference and, if needed, a note.
- If the consignment came from the receiving bay, the dock is attached and cleared when the GRV posts.

Each line has **Product**, **Unit** (filled from the product, editable), **Pack size**, **Packs**, **Qty (units)**, **Pack price**, **Unit price**, and **Line total**. The numbers calculate each other: type 3 packs of 24 at K240 a pack and the line shows 72 units at K10.00 each, total K720. Type a unit quantity instead and the pack count follows. Change the unit price and the pack price updates. The footer totals the units and the amount. Stock moves in units; the unit price becomes the product's new cost price. The same table is used for purchase orders.

Below the form, **Recent goods received** lists past GRVs; tap one to see its lines (pack, unit, cost and totals) and who posted it.

### 8.4 Receiving bay

V-SIAS inspections that are ready for the shop land here as docked consignments.

- The list shows tracking code, source, line count, and status. The navbar and this page badge show how many are waiting.
- Open a dock, check the lines, and flag anything that is not a normal catalog item.
- **Send to GRV** continues into Goods Received so stock is actually booked.
- **Cancel dock** drops the consignment from the bay. Both actions ask you to confirm.

---

## 9. Transfers and V-SIAS

**Stock → Transfers** (cashiers see this as **Stock request**).

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

The bell, the dashboard button, and **Office → Inspections** open V-SIAS Pro’s pending list in a panel over the current page for administrators, managers, and developers. The badge is the number still unsigned. The first time, V-SIAS asks you to sign in with your V-SIAS account. If the panel stays blank or says the content is blocked, use **Open in new tab**.

V-SIAS itself runs separately. On the shop PC it is on port 8095; on the hosted system it is `vsias.odeliazm.com`. The POS only opens it and receives the approved handoff.

---

## 10. Shifts, X-Reads, and Z-Read

### 10.1 Shifts & X-Reads

**Office → Shifts.**

- Money roles (cashier, bartender, manager, administrator, developer) see their own shift: opening float, sales, payouts, expected drawer.
- Managers also get **Master Shift Reports**: every shift, filterable by date and staff member. Open a shift to see its paid sales. **Explain these figures** opens a window that reads the shift totals for that day (count, open shifts, sales, cash, payouts). Staff names are not sent.
- Starting and ending a shift can also be done from this page, with the same approval rules as the POS.
- **Print shift** is the paper X-Read for one shift. Under the sales totals, **Flutterwave** lists each card or mobile-money payment with the receipt number, network, number paid from, and transaction ID.

### 10.2 End of day (Z-Read)

**Office → End of day.**

Pick the date, time from, time to, and one workstation, then **Filter**. The report is the day’s takings for that window: totals by payment method, a **Flutterwave payments** table (network, number, and transaction ID for each receipt), sales, and the figures you print for the close. **Print Z-Read** prints only the report. **Explain these figures** opens a window that reads those totals. It does not send the Flutterwave numbers or names.

Z-Read is the shop’s end-of-day record. It is not the ZRA VAT return.

### 10.3 Books of account

**Office → Books of account.**

Pick a book from the cards or the list, then the dates and a store (or all stores). The figures update on the page as soon as you change any of those. Books that have entries for those dates are listed first. The seven books are the daily sales register, the three-column cash book, the petty cash book, the recipe and yield book, the stock register, the wastage log, and the purchases day book. Each one is built from sales, payouts, stock movements, recipes, and goods received already on file. **Print** prints the book on screen.

The cash book puts cash in the cash columns and card, mobile money, and online payments in the bank columns. A discount is shown on its own. Flutterwave payouts stay on the sales register as “Paid out” and are not added a second time in the bank column.

---

## 11. ZRA Smart Invoice (Enterprise)

**Office → ZRA invoices.** The POS talks to a local fiscal device (VSDC) on this PC. That device signs each paid sale and sends it to ZRA. Filing the VAT return is still done later on the ZRA portal.

The top of the page shows whether the local service answers, how many paid sales are still unfiscalized, how many are fiscalized, and how many products are not registered.

### 11.1 Device card

You will see TPIN, branch ID, device serial, taxpayer name, **SDC ID**, **MRC number**, last invoice number, standard-code count, and UNSPSC count.

A developer enters the TPIN, branch, and device serial on this page and clicks **Save device** before the first handshake. Those three are not typed into the server file. Leave the VSDC address as `http://vsdc:8080/sandboxvsdc` when the device container is the usual one. Tick **Turnover tax** for a non-VAT taxpayer. For a live shop, set the receipt portal to `https://www.zra.org.zm`. Saving does not initialize the device.

| Control | When to use it |
|---|---|
| **Save device** | Developer only, before the first handshake, and again if the TPIN, branch, or serial changes. |
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

**Office → Reports.** The page itself is still titled Master Reports.

**Filter bar** (top of the page):

- **Date range.** Tap the dates to open a calendar and pick a start and end day. The **‹** and **›** arrows shift the whole range one day back or forward. The report reloads as soon as the range changes.
- **All stores** or one workstation.
- **All employees** or one cashier. Receipts and Taxes match on the cashier who rang the sale; Items and Categories match on the user who owns the sale.
- **Export** prints the current tab with a header (category, period, generated time).

**Summary tiles** under the filter bar: **Gross sales**, **Refunds**, **Discounts**, **Net sales**, **Gross profit**. They recalculate from the rows the filter returned. Net sales is paid sales; refunds are credit notes and refunded tickets; gross sales is the two added back together. Gross profit is an estimate at 45% of net sales when product cost is not in the figures, so treat it as a guide, not an accounting number.

**Tabs**

| Tab | What it lists | Tap a row |
|---|---|---|
| **Receipts** | Each sale in the period: receipt number, date and time, employee, store, payment, total. Flutterwave rows also show the network, the number paid from, and the transaction ID. Refunds carry a red **Refund** badge. | Opens the line breakdown (item, qty, total; voided and refunded lines are labelled). A Flutterwave payment shows its network, number, and transaction ID above the lines. **View** opens the printable receipt. The red arrow icon starts a refund (administrators, managers, developers; paid sales with a positive total only). |
| **Items** | Each product sold: name, SKU, items sold, gross sales. Sorted by revenue. | — |
| **Categories** | Each category: items sold, gross sales. Products without a category show as Uncategorized. | — |
| **Tax & statutory** | VAT at 16%: taxable sales and tax amount, worked back from paid totals. Under that, one line each for PAYE, NAPSA, and NHIMA from paid pay runs whose pay date falls in the period. NAPSA is the percentage of gross set under Payroll → Statutory rules, charged to the worker and again to the business, added into one amount. NHIMA is the percentage of basic, added the same way. These payroll amounts are not sent to Smart Invoice. | VAT opens every receipt in the period with its taxable amount and tax. A payroll row opens the payslips that make up that amount. NAPSA and NHIMA list the worker’s share and the business share on each payslip. The worker’s name opens that payslip in a window, and the run name opens the run’s payslips in a window. |
| **Tips & Surcharges** | Tiles for tips on receipts, tips added after payment (with the cash part), all tips, and surcharges. A table of surcharges by reason, then one row per staff member: paid receipts, tips on receipts, tips after payment (count and cash), and the total credited to them. | — |
| **Finance** | Every money movement in the period: each paid sale and refund, paid event deposit or balance, expense (a paid payroll run is not listed twice), each paid payslip (net pay plus PAYE, NAPSA, and NHIMA), each goods-received note, and each Flutterwave service fee. Tiles show money in, money out, payroll, purchases, and the result. A bank transfer is listed and marked “not in result”, because that money was already counted as a sale. Tips, surcharges, discounts, and tax are inside the sale and are not added again. Choosing one employee leaves out event payments and bank transfers. The time-of-day filter does not apply on this tab. | — |

Each table has a **Filter records** search box and pages of 50 rows. **Explain these figures** opens a window and writes a short note from the totals on the current tab, using the Gemini key under Settings → Guide. It does not forecast, and it does not send customer or staff names.

**Office → Business analysis** (administrators, managers, and developers). Pick a period of up to 120 days and, if you want, one store. Three charts show daily takings, how receipts were paid, and money in against refunds, expenses, payroll, purchases, and fees. The next 7 days on the takings chart are a projection: each day is the average of that weekday in the period. **Discuss these figures** opens a window that explains those totals and answers follow-up questions. The questions stay on this server. The reply can use only the totals behind the charts, and it will not give names, passwords, or keys. A projection is an estimate, not a promise. Changing the dates starts a fresh conversation.

**On a phone** the filter bar stacks, the five tiles form a two-column grid, the tab pills scroll sideways, and each table row becomes a card with labelled values and full-width buttons. The receipt and breakdown windows open full screen.

Reports show the shop’s sales ledger. They are not the ZRA VAT return; that is filed on the ZRA portal from the invoices Smart Invoice already sent.

The dashboard sales trend can show today, the last 7 days, this month, this year, or dates you choose. Pick items to draw each one with the total, as a line, as bars, or as both. Click the chart to open the same view larger. Today’s sales and transaction counts link here.

---

## 13. Dashboard

Administrators, managers, and developers.

- **Today’s sales** and **transactions** for the workstation you are on. Both open Master Reports.
- **Unpaid tabs** (Pro and above). Opens the POS when this station can sell.
- **Low stock** opens Stock Levels.
- **Inventory** shortcuts: Receiving bay, Goods received, products, and History.
- **Kitchen & menu** shortcuts on Pro+ and Enterprise: KDS, Meal Production, Menu Builder.
- **Staff & users** shortcuts: Manage Users, View Shifts, Members.
- **Sales trend.** Day, week, month, year, or a custom range. **Line**, **Bar**, or **Both**. **Items** picks up to 8 products sold in that period; each is drawn with the total. **Explain** writes a short note from the points on the chart, using the Gemini key under Settings → Guide. It does not forecast. Click the chart, or **Expand**, for a larger copy with the same button. **Payment mix** is today only.
- **Active shifts**: who is open, their role, and a button into that shift’s sales.
- **Settlements & Payouts**: the second tab. See below.
- **Pickup screen** and **Approvals** when your role and tier include them.
- **Go to POS** when this location can sell.

On a phone the stat tiles scroll sideways and the buttons stretch to full width.

### Settlements & Payouts

**Dashboard → Settlements & Payouts.** These figures are for the Flutterwave subaccount saved under Settings → Payments, not the main wallet that owns the API keys. The workstation pin does not filter them. Paste the subaccount ID (`RS_…`) before the totals can be read.

- **Due to the subaccount** is that subaccount’s share of confirmed charges, using the split saved on the subaccount.
- **Fees on subaccount payouts** come from the subaccount’s own settlement batches.
- **Waiting on Flutterwave** is the share not yet in a completed payout to the subaccount.
- **Paid to the subaccount** is the share Flutterwave has already matched to a completed subaccount batch.
- **Subaccount payout account** names the bank Flutterwave pays for that subaccount, and lists its batches.
- The charge table shows when, reference, receipt, method, what the customer paid, and the amount due to the subaccount. **Pending** or **Settled** means that subaccount payout.
- **Update from Flutterwave** reads the subaccount’s settlement batches from the last 90 days and marks matching charges settled. A pending or flagged batch stays pending.

A developer can send an emergency transfer from that subaccount’s payout balance to the account saved under Settings → Payments. Only a developer sees it. Enter the amount (not more than the subaccount payout balance), a short reason, and the developer username and password. Sandbox keys do not pay a real bank. Flutterwave must whitelist this server’s IP before a live transfer is accepted.

---

## 14. Users, passwords, and appearance

### 14.1 Users & Staff

Open the account menu, then **Staff**.

Add a person with full name, username, role, primary workstation, and password (at least 6 characters). A manager, administrator, developer, or head chef also needs a till approval code, 4 to 12 characters and different from the password. Edit the same fields later. Leave the password or the approval code blank on edit to keep the current one. **Reset code** on an office account clears the authenticator sign-in and signs that person out everywhere. Delete asks for confirmation (see section 3 for deactivate versus delete).

### 14.2 Change password

Any signed-in user can open Change Password. Enter the current password, the new one, and a confirmation. The new password must be at least 6 characters and must not be the till approval code. Saving it signs out every other browser still using that account. When the system forced the change, Cancel is hidden until it is updated. A manager, administrator, developer, or head chef can save a till approval code on the same page. It asks for the current password, and it must not be the login password.

### 14.3 System Settings (administrator and developer)

**Office → Settings.** The page has these tabs.

**General**

- **Business name** — navbar and receipt title.
- **Receipt header** — extra lines (TPIN, slogan, address).
- **Receipt footer** — the thank-you line.
- **Colours** — main header, accent (borders and icons), and cart header. The POS picks these up on the next load.
- **License tier** and **lockout date** (developer only) — see section 2. A lockout date in the past turns on read-only mode as soon as you save.

**POS surcharges** — the list the POS offers. Add a name, a default amount and/or percent, a sort order, and whether it is active. Edit, switch off, or delete from the table. Deleting does not change past sales.

**Payments (Flutterwave)** — developer login only. Switch the gateway off to stop new card and mobile-money charges on the till, the shop, and event pages. A charge that was already sent can still be recorded. Switch it on and paste the **public key**, **secret key**, and **webhook secret hash** from Flutterwave → Settings → API. Keys that start with `FLWPUBK_TEST` or `FLWSECK_TEST` are sandbox; the other keys are live. **Test keys** checks that Flutterwave accepts them. Paste the **subaccount ID** (`RS_…`). Card and mobile-money payments use the split saved on that subaccount, and Settlements & Payouts follows that subaccount rather than the main Flutterwave wallet. Copy the **webhook URL** and the same secret hash into Flutterwave → Settings → Webhooks. A confirmed charge marks the shop order paid even when the guest closes the browser before the return page loads. The order stays on Web Orders until Hand Over, and it is not filed with ZRA from the webhook. The same webhook also updates an emergency transfer when Flutterwave finishes it.

**Emergency payout account** — where a developer’s transfer from the subaccount payout balance sends money. Choose **Bank account** or **Mobile money**. For a bank, pick it from the Flutterwave list, then the account number and the name on the account. A company name may include & and commas. If that bank has branches and Flutterwave returns them, choose the branch. For mobile money, choose **MTN**, **Airtel**, or **Zamtel** and the number (for example 0971234567). The transfer always pays this saved account; it is not typed again at send time. Leave the bank or the mobile number blank and save to remove it. Flutterwave must whitelist this server’s IP before a live transfer is accepted.

**Currency** — switch the converter on, choose which currencies the POS shows (USD, ZAR, EUR, GBP, and others), and how often rates refresh. Live rates are pulled against the kwacha and cached. You may fix a **house rate** for any currency; it overrides the live rate. **Refresh live rates now** fetches immediately. The quick converter on the tab converts any amount between enabled currencies.

**Customer ordering** — the customer portal (section 16): switch it on, pick the location that fulfils web orders, title and intro text, contact phone, minimum order, delivery fee, Flutterwave payment, and delivery. Online orders are paid with Flutterwave only (card or Zambia mobile money). **Open page** shows the live portal.

**Order webhook** — an https address that receives a POST when an online order is ready (the kitchen has finished it, or it was drinks only) and again when the till presses Hand Over. The JSON names the order, the sale, the status (`ready` or `handed_over`), whether it is paid, the customer, and the time. If you set a secret, the header `X-SmartTrade-Signature` is the HMAC-SHA256 of that raw body. Leave the address blank and nothing is sent.

**Logo & banners** — the brand logo, shop landing page photo, promo slideshow pictures, and menu item photos. See the Branding guide.

**Email** — SMTP host, port, user, password, and sender address for system emails (like event invitations).

**Guide** — The Gemini API key and model for the AI staff guide. To speed up answers and save tokens, tap **Sync Documentation** after saving the key to upload the manuals into Gemini's context cache. Sync again if the manuals change.

**Developer** (the developer login only) — **Activity log** lists each staff sign-in (successful or failed), sign-out, workstation change, and sale or refund, with the location. Stock movements stay on stock history. **Clear sales and stock** removes transactions and quantities and leaves the products and UNSPSC classes. **Wipe products and UNSPSC** does that and then deletes the catalogue. Both ask you to type a confirmation. On **ZRA invoices**, the same login can set the TPIN, branch, device serial, the local VSDC address, whether the taxpayer is on turnover tax, and the receipt portal. Saving those does not initialize the device again.

### 14.4 Searching in drop-downs

Every drop-down in the system can be searched. Click or tap it and type: the list narrows as you type and matches more than the visible label — products match on SKU and barcode, vendors on contact name and phone, ingredients on unit and SKU, staff on username and full name. Use the arrow keys and Enter, or tap the match. Clear the box to see the full list again.

---

## 15. Payroll (administrator and manager)

Open the account menu, then **Payroll**. Four tabs.

**Employees.** Choose the person and their role from the staff list (the same people and roles as Staff). The name is taken from that staff record, and tips are taken from that login. Then NRC, NAPSA and NHIMA numbers, TPIN, location, **pay type** (monthly, daily rate, or hourly rate) and the rate, bank account or mobile money number, start date, and any standing allowances or deductions with their amounts. PAYE, NAPSA and NHIMA are ticked by default and calculated on each pay run. Untick one when a worker is not registered for it. Switch people off instead of deleting them when they leave.

**Components.** Earnings (housing, transport, overtime, commission…) and deductions (loan, advance, uniform…). Each is a fixed amount or a percent of basic pay, taxable or not, and active or not. Active components appear on every employee form. On a percent component the number you enter is the percent: 10 means 10% of that payslip’s basic. The payslip prints the percent, the basic, and the kwacha. A fixed component is the kwacha amount you type.

**Statutory rules.** Zambian PAYE bands (editable upper limits and rates), NAPSA rate and monthly cap, NHIMA rate, standard working days a month, and whether tips are included in pay and whether they are taxed. A quick PAYE check lets you type a gross figure and see the tax.

**Pay runs.**

1. **New run**: title, period start and end, pay date. Every active employee gets a slip. Shifts in that period fill the hours worked for the linked staff login. A shift still open counts as 8 hours for each day from the day it opened through today, or through the end of the pay period. On a daily rate each of those days counts as 1 day. A closed shift longer than 16 hours is left out and named on the run. Hourly basic is the hourly rate times the hours. Tips the staff member earned on the POS in that period (on receipts and after payment) are pulled in automatically when tips are enabled.
2. Open the run and edit **days**, **hours**, **basic**, or **tips** per person. **One-off adjustments** sets the hours, the days, a subtraction (with a reason), and any extra earning or deduction for this run only. **Recalculate** reworks every slip. Clear Hours or Days to read the shifts again. A basic figure you type is kept.
3. **Approve** locks the figures. **Mark paid** books the net pay as an expense and stamps the pay date. **Reopen** returns an approved run to draft. Draft runs can be deleted.
4. **Print payslips** prints the whole run or a single slip: earnings, deductions, employer NAPSA and NHIMA, net pay. A percent allowance shows the rate and the basic next to the kwacha, and NAPSA and NHIMA show their percentage of gross or basic.
5. **Explain these figures**, on an open run, writes a short note from the run totals (headcount, gross, PAYE, NAPSA, NHIMA, net) using the Gemini key under Settings → Guide. Employee names are not sent. It is not payroll advice and not a forecast.

How a slip is worked out: basic (pro-rated for days or hours when the pay type calls for it) + allowances (+ tips if included) = gross. An allowance set to percent of basic is that percent of the basic on the slip. NAPSA employee share is 5% of gross up to the cap, and is deducted before tax. PAYE is applied to gross less NAPSA (and less non-taxable allowances) using the bands. NHIMA is 1% of basic. Net = gross − NAPSA − NHIMA − PAYE − other deductions. The employer's matching NAPSA and NHIMA appear separately and are not deducted from the worker.

---

## 16. Customer ordering portal and events

The storefront runs on `index.php?page=shop` and does not need a staff login.

- **Menu & Checkout:** Customers browse items by category, add them to a basket, and check out. They register with a name, Zambian mobile number (which becomes their PIN login), and PIN. Online orders are paid with Flutterwave only, by card or Zambia mobile money. The order reaches POS **Web Orders** and the kitchen only after that payment succeeds. Until then the order page says **Waiting for payment**. On the order page, **Cancel this order** stops it while it is still pending. If it was already paid, the store refunds that payment separately. **Reserve a table** on the shop home holds a free table for the fee saved under **Office → Tables**, with no food on the order. The hold starts when payment succeeds and lasts for the minutes saved there. The refundable percentage on that same screen is what the till should return if the hold is cancelled; **0** means the fee is non-refundable. The money is refunded in Flutterwave. A QR code from **Office → Tables** opens this menu. In the bag, the guest picks a store and then a free table from the dropdown. Choosing a table asks them to add the reservation surcharge. Accepting adds it to the bill and turns delivery off. They can remove the fee before paying, or clear the table if the order should be delivered.
- **How was your visit:** The electronic menu and the bag both link to the same question. A finished, paid online order shows it once on the order page. **It was lovely** opens the Google review saved for that store on **Office → Locations**. If that store has no link, the page thanks them and stops. **Something was off** asks what happened, with an optional phone number, and saves a private note for **Office → Guest notes**. The Google link stays on that page as quiet text. Everyone is asked the same way, and nothing is offered in exchange for a review. A sale can be answered once. A table visit without an order can be answered once per browser. When email is already set up and the online customer has an email, handing over the paid web order sends one plain message with the same link. The POS shows a short line when this station’s store has an open unhappy note from the last day.
- **Promo strip:** A scrolling bar shows promotional images. If no events are published, it cycles the promo-folder slides. If published events exist, it advertises the events instead.
- **Events:** If the event planner (Office → Events) has published an open event, customers can view its page, read the agenda, and sign up or buy a ticket. Tiers can have limits and custom waitlist logic. The shop home keeps its banner. Each open event floats on that photo as a card, using the picture set on the event. When more than one is open, the card slides to the next. Leave the end date blank when the event does not finish at a set time.

Staff can set up and publish these events on the back office:

- **Office → Events:** Create, edit, publish, and close events. The end date can be left blank when there is no finish time. Define meal/seat packages and payment tiers.
- **Invitations:** Upload lists of guests, accept/waitlist/decline them, or send a mass email with secret RSVP links. (SMTP must be configured in System Settings → Email).
- **Check-in:** A day-of staff board tracks arrived guests.

---

## 17. Who uses which screen

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
| Guest notes | Yes | Yes | Yes | | | |
| Payroll | Yes | Yes | Yes | | | |
| System settings (surcharges, payments, currency, portal) | Yes | Yes | | | | |
| License tier | Yes | | | | | |
| Tip after payment | Yes | Yes | Yes | | | Yes |
| Members, audit trail | Yes | Yes | Yes | | | |
| Pickup | Yes | Yes | Yes | | | Yes |

Empty cells mean that role is sent back to their home page if they open the address directly. Kitchen rows also require Pro+ or Enterprise. Purchasing, members, audit, transfers, and pickup require Pro or above. ZRA’s menu entry is Enterprise, except administrators and developers, who can open it on any tier.

---

## 18. Common sequences

**Guest tips after paying**

1. The bill is paid and the receipt printed.
2. Guest hands the waiter K20. Waiter taps **Tip**, searches the receipt, enters 20, Cash, credits themselves, saves.
3. The X-Read and Z-Read show the tip; the Tips & Surcharges report credits it to the waiter; payroll can pull it into the pay run.

**Guest asks to pack after eating**

1. Before charging, open the payment window and pick the surcharge reason **Take-away packaging** (or whatever the administrator named it). Adjust the amount if needed.
2. Charge as usual. The receipt shows the surcharge line.

**Online order, paid online**

1. Customer orders and pays on their phone. The POS Web Orders badge goes up only after Flutterwave confirms the payment; the order shows **PAID ONLINE**.
2. Kitchen prepares; items are marked ready.
3. Cashier opens the order and taps **Hand Over (Paid)**.

**Month-end payroll**

1. Payroll → Pay runs → New run for the month.
2. Correct days worked and tips, add any one-off adjustments, Recalculate.
3. Approve, print payslips, pay the staff, Mark paid.

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

**Guest calls the guide**

The Guide button on the back-office shared pages (Dashboard, Management, Settings, etc.) answers how-to questions from this manual. Ask a short question. The Guide answers with steps and a link to the screen.

---

## 19. Screen Index

| Screen Name | Location in App |
|---|---|
| Dashboard | `index.php?page=dashboard` |
| Point of sale | `index.php?page=pos` |
| Customer shop | `index.php?page=shop` |
| Products | `index.php?page=products` |
| Categories | `index.php?page=categories` |
| Stock levels | `index.php?page=inventory` |
| Purchase inquiry | `index.php?page=purchasing&tab=inquiry` |
| Purchase orders | `index.php?page=purchasing&tab=orders` |
| Goods received | `index.php?page=purchasing&tab=received` |
| Receiving bay | `index.php?page=receiving_bay` |
| Vendors | `index.php?page=vendors` |
| Reports | `index.php?page=reports` |
| Business analysis | `index.php?page=analysis` |
| End of day | `index.php?page=z_read` |
| Books of account | `index.php?page=books` |
| Shifts | `index.php?page=shifts` |
| Users | `index.php?page=users` |
| Payroll | `index.php?page=payroll` |
| Locations | `index.php?page=locations` |
| Tables | `index.php?page=tables` |
| Events | `index.php?page=events` |
| Settings | `index.php?page=settings` |
| Kitchen | `index.php?page=kitchen` |
| Menu | `index.php?page=menu` |
| Kitchen display | `index.php?page=kds` |
| Stock history | `index.php?page=audit` |
| Members | `index.php?page=members` |
| Transfers | `index.php?page=transfers` |
