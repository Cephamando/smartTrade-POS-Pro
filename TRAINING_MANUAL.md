# smartTrade POS Pro — training manual

This manual teaches the system by doing. Every section is a set of **scenarios**: a short situation, the exact clicks, and what you should see when it worked. At the end of each role there is an **assessment** the trainer can run to confirm the trainee is ready for a live shift.

Use it together with [user_guide.md](user_guide.md), which describes every screen in reference form. This manual does not repeat those descriptions; it tells you what to do and how to check it.

A printable Word version with screenshots is in `TRAINING_MANUAL.docx`. Rebuild it after editing this file with `python3 docs/training_manual/build_docx.py` (needs `pip install python-docx`).

---

## How to use this manual

**For trainers**

1. Set up a training copy of the shop (section 0). Never train on the live database or the live ZRA device.
2. Pick the role chapter for the trainee. Work through the scenarios in order; later ones depend on earlier ones.
3. Run the assessment at the end of the chapter. Record pass/fail against each checkpoint.
4. Sign off on the competency sheet (section 11).

**For trainees**

- Read the scenario, do it yourself, then compare your screen with the **Expected result**.
- If the result is different, read **If it goes wrong** before asking.
- Do not skip the scenarios marked **Must pass**.

**Conventions**

- **Bold** is something you click or type exactly.
- `Code` is an address, a setting, or a value shown on screen.
- *Expected result* is what the screen must show when the step is correct.
- ZMW figures in the scenarios are examples. Use the training catalog prices.

---

## 0. Trainer set-up (before the first session)

### 0.1 Training environment

| Item | Setting for training |
|---|---|
| Database | A copy restored from backup, or the seeded demo database. Never the live shop. |
| License tier | **Enterprise** so every feature is visible. Drop to Lite or Pro at the end to show lockouts. |
| ZRA | Leave the training copy **Not initialized**, or point `ZRA_VSDC_URL` at a sandbox device with a sandbox serial. Never the production serial. |
| V-SIAS | The training V-SIAS instance, or skip the inspection scenarios. |
| Printer | Any printer, or print to PDF. |

### 0.2 Training accounts

Create these on **Management → Users & Staff** before the session. Use simple passwords for training and delete the accounts afterwards.

| Username | Role | Primary workstation |
|---|---|---|
| `train_admin` | Administrator | All Locations (HQ) |
| `train_mgr` | Manager | Main Bar |
| `train_cashier` | Cashier | Main Bar |
| `train_bar` | Bartender | Main Bar |
| `train_waiter` | Waiter | Main Bar |
| `train_chef` | Head chef | Kitchen |
| `train_cook` | Chef | Kitchen |

The **developer** account is the one seeded with the system. Only the trainer uses it.

### 0.3 Training catalog

Make sure the catalog has at least:

- Three drinks with stock at Main Bar (one at **0** stock to show the grey tile).
- One product with price `0.00` (open price).
- One food dish with a recipe of two raw ingredients, and those ingredients in stock at Kitchen.
- One raw ingredient marked **Raw material (hidden)**.
- One product with **no** UNSPSC code (to demonstrate the ZRA rejection).
- One member registered with a phone number.
- Two locations that can sell (Main Bar, Restaurant), one Kitchen, one Warehouse that is a **Goods receiver**.
- One vendor.
- Two tables in **Table Settings** (Pro+).

### 0.4 Devices for the session

At least one PC and one phone or tablet on the same network. Half of the scenarios in this manual have a mobile variant; the phone is how waiters, chefs, and managers on the floor will use the system.

---

## 1. Everyone: signing in, the shell, and the phone

### Scenario 1.1 — Sign in and find your home page

1. Open `http://<shop-pc>:8090` on the PC.
2. Sign in as `train_cashier`.

*Expected result:* the POS opens. There is no Dashboard. The top bar shows the station name, **Payout**, **X-Read**, **End**, **Exit**.

3. **Exit**, then sign in as `train_mgr`.

*Expected result:* the Dashboard opens. The navbar shows Dashboard, Inventory, Management, Kitchen Display, and a green **Launch POS**.

4. **Logout**, then sign in as `train_chef`.

*Expected result:* Meal Production opens. The navbar shows Live KDS Screen, Recipe/Prep Log, Menu Builder, Stock Levels. There is no POS button.

**If it goes wrong:** a chef landing on the POS means the tier is Lite or Pro. Kitchen roles need Pro+ or Enterprise.

### Scenario 1.2 — Forced password change

1. As `train_admin`, edit `train_cashier` on **Users & Staff** and set a new password.
2. Sign in as `train_cashier`.

*Expected result:* if the account is flagged to change password, the Change Password screen appears and **Cancel** is hidden. Enter the current password, a new one of at least 6 characters, confirm, and save. The POS then opens.

3. Try a 4-character password.

*Expected result:* it is rejected.

### Scenario 1.3 — Know where you are (active page)

1. As `train_mgr`, open **Inventory → Products & Menu**.

*Expected result:* **Inventory** in the navbar is highlighted yellow with an underline. Open the Inventory menu: **Products & Menu** has a yellow background.

2. Open **Management → Master Reports**.

*Expected result:* the highlight moves to Management, and **Master Reports** is the yellow entry.

### Scenario 1.4 — The navbar on a phone

1. On the phone, open `http://<shop-pc>:8090` and sign in as `train_mgr`.

*Expected result:* the brand is on the left, a green **POS** pill and a **Menu** button on the right.

2. Tap **Menu**.

*Expected result:* a dark panel drops down. Dashboard is a single row. Inventory and Management have a chevron. The bottom holds Approvals, the workstation chip, your name, and Logout, all full width.

3. Tap **Inventory**, then **Management**.

*Expected result:* Inventory expands with a yellow left rail; when you tap Management, Inventory folds and Management opens. Only one is open at a time.

4. Tap **Stock Levels**.

*Expected result:* the panel closes by itself and Stock Levels loads.

5. Tap the green **POS** pill.

*Expected result:* the POS opens directly, without opening the menu.

**If it goes wrong:** no POS pill means the current workstation has **POS enabled** off (the pill shows grey and crossed) or the user is a chef.

### Scenario 1.5 — Switching workstation

1. As `train_admin` on the PC, click the pin chip in the navbar.

*Expected result:* **Select Workstation** lists every location. The current one has a yellow border.

2. Pick **Restaurant**, confirm **Switch**.

*Expected result:* the chip now says Restaurant. The dashboard figures change to that station.

3. As `train_mgr`, look for the same chip.

*Expected result:* managers see the chip too, but location edits they save elsewhere are queued for the developer. On the POS, the pencil next to the station name lists only sellable locations.

---

## 2. Cashier and bartender

Sign in as `train_cashier` for this chapter. Use `train_mgr` on a second device (or the trainer types the manager credentials) for every authorization.

### Scenario 2.1 — Open a shift (Must pass)

Situation: it is 08:00, the drawer has ZMW 500 in float.

1. The product grid is blurred and **Start New Shift** is on screen.
2. Enter **500** as the opening float. Tap **REQUEST APPROVAL**, then confirm **Request** in the pop-up.

*Expected result:* the box changes to **Awaiting Manager Approval** with the shift number.

3. Manager enters `train_mgr` and the password, confirms **Approve**.

*Expected result:* the blur lifts, the grid is live, the top bar shows the shift is open.

**If it goes wrong:** "Open a shift before checkout" at any later point means the shift was never approved or was ended.

### Scenario 2.2 — Simple cash sale (Must pass)

Situation: a customer buys two Mosi and one Fanta, pays ZMW 100 cash.

1. Tap **Beverages**, then **Lagers**, then **Mosi**. Tap **Mosi** again. Tap **Back**, then **Soft drinks**, then **Fanta**.

*Expected result:* the cart shows Mosi × 2 and Fanta × 1 with a running total.

2. Tap **CHARGE**. Payment method **Cash**. Tendered **100**. Tap **COMPLETE TRANSACTION**, then confirm **Complete** in the pop-up.

*Expected result:* a receipt opens for printing with the change shown. The cart is empty. Stock Levels for Main Bar show Mosi down by 2 and Fanta by 1.

### Scenario 2.3 — Out of stock

1. Find the product the trainer set to 0 stock.

*Expected result:* the tile is grey and does nothing when tapped.

2. Add a product with stock 3 and tap **+** four times.

*Expected result:* quantity stops at 3. The till will not oversell this station.

3. Switch on **In-stock**.

*Expected result:* the grey tile disappears from the grid.

### Scenario 2.4 — Search and open price

1. Type the first letters of a product into **Search product**.

*Expected result:* the grid filters live.

2. Tap the `0.00` product.

*Expected result:* **Custom Amount** asks for the price. Enter **35** and add.

*Expected result:* the cart line shows ZMW 35.00.

### Scenario 2.5 — Card, mobile money, and split pay (Must pass)

Situation: total ZMW 180. Customer pays ZMW 100 by Airtel Money and the rest by cash.

1. Ring the items. **Charge**.
2. Choose **Split pay**. First row **Airtel Money**, amount **100**. **Add payment method**, second row **Cash**, amount **80**.

*Expected result:* the remaining balance reaches 0.00 and Complete becomes available. The receipt lists both payments.

3. Ring another sale and pay the whole thing by **Card**.

*Expected result:* no tendered/change step; the receipt shows Card.

4. Ring another and use a split row **Other** named **Bank transfer**.

*Expected result:* the receipt shows "Bank transfer".

### Scenario 2.6 — Tip and customer name

1. Ring a sale. In the payment window, tap **10%**.

*Expected result:* the tip field fills with 10% of the total and the total due updates.

2. Type **Mrs Banda** into **Customer Name**. Complete.

*Expected result:* the receipt shows Mrs Banda and a tip line.

### Scenario 2.7 — Member sale (Pro and above)

1. Attach the training member (search by phone).

*Expected result:* a blue bar says **Member: <name>** and the customer name is locked to the member.

2. Switch **10% OFF** on. **Charge**.

*Expected result:* the total is reduced by 10%. The discount appears in the Discounts tile on Master Reports.

### Scenario 2.8 — Clear the cart

1. Add two items. Tap **CLEAR**.

*Expected result:* a confirmation. **Keep** leaves the cart; **Clear** empties it.

### Scenario 2.9 — Payout (Must pass)

Situation: the manager sends the cashier to buy ZMW 50 of ice.

1. **Payout**. Amount **50**, reason **Ice**. Confirm **Authorize**.
2. Enter manager credentials.

*Expected result:* "Payout logged". The X-Read now shows a payout line and the expected drawer is 50 lower.

### Scenario 2.10 — Lost stock (Pro and above)

Situation: a bottle of Mosi broke.

1. Add **Mosi** to the cart. Tap **LOST STOCK**.
2. Enter manager credentials. **Authorize**.

*Expected result:* "Waste logged". The cart is empty. Stock Levels show Mosi down by 1. **No** sale appears on the Receipts report. The Inventory Audit Trail shows a waste row.

### Scenario 2.11 — Return against an invoice (Must pass)

Situation: the customer from 2.2 brings back one Fanta.

1. Switch **Return** on. The invoice list opens. Filter by the sale number from 2.2 and select it.
2. Choose the Fanta line, quantity 1.
3. **Charge**. Enter manager credentials. Pick **ZRA refund reason** → **Wrong product**. Complete.

*Expected result:* a credit note receipt (negative total). Stock for Fanta goes up by 1. On Master Reports the ticket carries a red **Refund** badge.

4. Try to return two more Fanta against the same invoice.

*Expected result:* refused. The system will not credit more than the invoice still has left.

**If it goes wrong:** a return rung without picking an invoice is a POS return with no original Smart Invoice. ZRA cannot accept it. Always select the invoice first.

### Scenario 2.12 — X-Read

1. **X-Read**.

*Expected result:* a window with opening float, sales by payment method, payouts, and expected drawer. **PRINT X-READ** prints it. The shift stays open.

### Scenario 2.13 — End the shift (Must pass)

Situation: 17:00. Actual cash counted is ZMW 1,180; expected is ZMW 1,200.

1. **End**. Enter counted cash **1180**. Explain the difference in **variance reason** (e.g. "short ZMW 20, under investigation").
2. Enter manager credentials. Tap **CLOSE SHIFT & LOGOUT**, then confirm **End shift** in the pop-up.

*Expected result:* the cashier is signed out (a manager is returned to the Dashboard). Signing in again shows the station blurred with **Start New Shift**. Shifts & X-Reads shows the shift as closed with the variance.

### Assessment — cashier / bartender

| # | Checkpoint | Pass |
|---|---|---|
| C1 | Requests a shift with the right float and waits for approval | |
| C2 | Rings a mixed sale using categories and search | |
| C3 | Explains why a tile is grey and why + stops | |
| C4 | Completes cash with correct change, card, mobile money, and a split | |
| C5 | Attaches a member and applies 10% | |
| C6 | Logs a payout with reason and manager auth | |
| C7 | Logs lost stock and shows it in Stock Levels, not in Receipts | |
| C8 | Returns against the original invoice with a reason, and explains why a free-standing return is wrong | |
| C9 | Prints an X-Read without closing | |
| C10 | Ends the shift with the counted cash and a variance note | |

---

## 3. Waiter

Sign in as `train_waiter`. Needs Pro or above; tables need Pro+.

### Scenario 3.1 — Start a tab

Situation: two guests sit down and order drinks first.

1. Ring two drinks. From the cart, choose **Add to Tab** → **New tab**, name it **Table 4**, assign table **T4** (Pro+). Confirm.

*Expected result:* the cart is empty. **Tabs** shows "Table 4" with two lines. On **Tables**, T4 is red (occupied).

### Scenario 3.2 — Food with Got it / Later (Pro+)

1. Open the tab from **Tabs**. Add a dish from a Food category.

*Expected result:* the line shows **Got it** and **Later**.

2. Tap **Later**.

*Expected result:* the dish is sent to the kitchen. The KDS (section 5) shows a red ticket with this order number and your name.

3. Add a second dish and tap **Got it** (the guest already received it from the pass).

*Expected result:* nothing goes to the kitchen; the line is marked collected.

### Scenario 3.3 — Pickup

1. Wait for the kitchen to mark the first dish **Done**.

*Expected result:* the red pickup badge on the POS shows 1.

2. Open **Pickup**. Mark the dish collected.

*Expected result:* the badge drops to 0.

### Scenario 3.4 — Transfer or merge lines between tabs

Situation: one guest moves to Table 6.

1. **Tabs** → **Transfer / Merge**. Source tab Table 4. Tick one drink. Destination: new tab **Table 6**. Confirm **Transfer**.

*Expected result:* Table 4 has one line fewer; Table 6 exists with that line.

### Scenario 3.5 — Close a tab with split payment

1. Open Table 4 → **Charge**. Pay half by Card and half by Cash.

*Expected result:* receipt printed, tab gone from **Tabs**, T4 green again on the floor plan.

### Scenario 3.6 — Pending payment

1. Ring a sale and choose payment **Pending**.

*Expected result:* the order becomes a tab with no money taken. It shows in the Dashboard's **Unpaid Tabs** count.

### Scenario 3.7 — Web orders (Enterprise)

1. Ask the trainer to drop a test online order for Main Bar.
2. **Web Orders** (or the ⋯ menu → Web Orders).

*Expected result:* the order is listed with a badge count. Open it.

*Expected result:* its lines are in the cart; pay it with **Online**.

### Assessment — waiter

| # | Checkpoint | Pass |
|---|---|---|
| W1 | Opens a named tab assigned to a table | |
| W2 | Fires food with Later and recognises the ticket on KDS | |
| W3 | Uses Got it correctly for a plate already served | |
| W4 | Collects from Pickup and clears the badge | |
| W5 | Moves lines between tabs | |
| W6 | Settles a tab with split pay and sees the table free up | |
| W7 | Takes an online order and pays it with Online | |

---

## 4. Head chef and chef

Sign in as `train_chef` (head chef). Pro+ or Enterprise.

### Scenario 4.1 — Build a dish and its recipe (head chef)

1. **Menu Builder**. Add **Chicken & chips**: Food category, selling price **85**.
2. Save the recipe: **Chicken portion** 1, **Chips (raw potatoes)** 0.25 kg.

*Expected result:* the dish appears on the menu list with its ingredients. On the POS it appears under Food; the raw potatoes do not appear as a tile.

**If it goes wrong:** "Assign a UNSPSC code before registering the item with ZRA" means the dish saved locally but was not registered with ZRA. Add a level-4 code on the product and save again (Enterprise).

### Scenario 4.2 — Check what is on hand and produce (Must pass)

Situation: lunch starts in an hour; the bar has no Chicken & chips on hand.

1. **Meal Production**. Pick **Chicken & chips**. Choose workstation **Main Bar**.

*Expected result:* the card says **None on hand** in red.

2. Quantity **10**. Confirm **Produce & send**.

*Expected result:* Kitchen stock of chicken portions is down 10 and potatoes down 2.5 kg. Main Bar shows 10 Chicken & chips. The card now says 10 on hand in green.

3. Try to produce 100.

*Expected result:* refused or capped when ingredients are insufficient.

### Scenario 4.3 — Raw food cannot go straight to the till

1. Try to produce the raw ingredient itself to Main Bar.

*Expected result:* not allowed. Only finished dishes with recipes can be sent to a till.

### Scenario 4.4 — Work the kitchen display (chef)

Sign in as `train_cook` on the kitchen screen.

1. Have the waiter fire a dish with **Later** (scenario 3.2).

*Expected result:* a ticket with a red header shows the order number, who sent it, and minutes elapsed.

2. Tap **Cook** on the line. Confirm.

*Expected result:* the header turns yellow.

3. Tap **Done**. Confirm.

*Expected result:* the ticket clears; the waiter's Pickup badge increments. "All orders clear" shows when nothing is waiting.

### Scenario 4.5 — Chefs and stock

1. As `train_cook`, open **Stock Levels**.

*Expected result:* you can filter and look. Entering a physical count and saving is refused: "Kitchen staff have view-only access to stock."

### Scenario 4.6 — Recipe consumption report (head chef)

1. **Management → Recipe Consumption** (head chef has this; the chef does not). Set today's date and the Kitchen location.

*Expected result:* chicken and potatoes used by 4.2 are listed with quantities. The sales ledger is not involved.

2. Sign in as `train_cook` and try the same address.

*Expected result:* sent back to Meal Production.

### Assessment — kitchen

| # | Checkpoint | Pass |
|---|---|---|
| K1 | Creates a dish with a two-ingredient recipe | |
| K2 | Reads on-hand before deciding batch size | |
| K3 | Produces to the correct station and verifies both stock movements | |
| K4 | Explains why raw stock cannot be produced to a till | |
| K5 | Moves a KDS ticket Cook → Done | |
| K6 | Knows that chefs view stock but do not post counts | |
| K7 | Head chef reads Recipe Consumption for a day | |

---

## 5. Manager

Sign in as `train_mgr`. Everything a cashier does, plus the chapters below. Managers do all approvals in chapters 2 and 3; make sure they have practised typing their credentials on another person's screen without leaving it.

### 5.1 Staff and shifts

#### Scenario 5.1.1 — Create a user (Must pass)

1. **Management → Users & Staff → Add New User**. Full name, username `temp_cashier`, role **Cashier**, workstation **Restaurant**, password. Save.

*Expected result:* the user appears in the list.

2. Try the same username again.

*Expected result:* "That username is already in use."

3. Edit the user, leave the password blank, change the workstation.

*Expected result:* the workstation changes; the password is unchanged (sign in to prove it).

#### Scenario 5.1.2 — Delete versus deactivate

1. Delete `temp_cashier` before they have sold anything.

*Expected result:* the account is removed.

2. Try to delete `train_cashier`, who has sales.

*Expected result:* the account is **deactivated**, not removed. History is kept.

3. Try to delete your own signed-in account.

*Expected result:* "You cannot delete the signed-in account."

#### Scenario 5.1.3 — Approve, review, and close shifts from the office

1. Have `train_cashier` request a shift. On your PC, **Management → Shifts & X-Reads**.

*Expected result:* the pending shift is listed; approve it there.

2. Open **Master Shift Reports**, filter by today and `train_cashier`. Open the shift.

*Expected result:* the paid sales for that shift are listed. **Print shift** prints the X-Read.

3. The dashboard **Active shifts** card shows the same shift with a button into its sales.

### 5.2 Catalog and stock

#### Scenario 5.2.1 — Add a product correctly for ZRA (Must pass on Enterprise)

1. **Inventory → Products & Menu → New Product**. Name **Castle Lite 330ml**, SKU, category Lagers, unit, type Item, cost **12**, price **25**, POS visibility **Sellable on POS**.
2. ZRA tax class **Class A - Standard (16%)**. UNSPSC code: type **beer** and pick the level-4 code (for example `50202201`). **Save Product**.

*Expected result:* the product saves. The Unsynced products count on the ZRA page goes up by one until **Register products** is run.

3. Add a second product and leave UNSPSC blank.

*Expected result:* it saves, but it will be rejected when registered with ZRA ("Item class must be a level-4 UNSPSC"). Fix it before go-live.

#### Scenario 5.2.2 — Categories and routing

1. **Inventory → Categories**. Add **Desserts**, type **Food / Meal**, parent **Food**.

*Expected result:* on the POS, **Food** drills down to **Desserts**. Items in it show **Got it / Later** (Pro+).

2. Try to delete **Food** while Desserts exists.

*Expected result:* "Remove subcategories before deleting this category."

#### Scenario 5.2.3 — Stock take (Must pass)

Situation: month-end count at Main Bar. The system says 48 Mosi; you count 46.

1. **Inventory → Stock Levels**. Location Main Bar. On Mosi, physical count **46**, reason **Month-end count**. **Finish & update stock**.

*Expected result:* Mosi shows 46. **Inventory Audit Trail** shows a stock-take row of −2 by you.

2. Have a cashier sell one Mosi during your count, then save a count that was made before the sale.

*Expected result:* the adjustment is applied to live stock, so the sale is not lost.

3. Enter a count that would take quantity below zero.

*Expected result:* the quantity stops at zero.

#### Scenario 5.2.4 — Audit trail

1. **Inventory Audit Trail** (`index.php?page=audit`). Filter today, action **waste**.

*Expected result:* the broken Mosi from 2.10 is listed with the cashier, the station, −1, and the quantity after.

2. Filter **sale**, **produce**, **transfer**, **grv**, **stock_take** in turn.

*Expected result:* each shows the rows from this training day.

### 5.3 Purchasing

#### Scenario 5.3.1 — Vendor

1. **Inventory → Vendors → Add New Vendor**: name, contact, phone.
2. Try to delete a vendor that has a GRV.

*Expected result:* "This vendor has GRV records and cannot be deleted."

#### Scenario 5.3.2 — Inquiry → order → receive (Must pass)

Situation: you need 10 cases of Mosi at the Warehouse.

1. **Purchasing → Purchase inquiry → New**. Location Warehouse, vendor optional, needed-by next week, line Mosi × 240. Save.
2. Open it → **Convert**.

*Expected result:* a purchase order is created with the same lines.

3. **Purchase orders**: open it, set vendor (required), reference **PO-TRAIN-01**. Save.
4. Goods arrive. **Receive**.

*Expected result:* Goods Received opens with the order loaded.

5. Accept 240, supplier reference **INV-554**. Post.

*Expected result:* Warehouse stock of Mosi +240. Audit trail shows a GRV row. The PO is marked received.

6. Try to receive into **Main Bar**.

*Expected result:* refused unless Main Bar is a **Goods receiver** location.

#### Scenario 5.3.3 — Cancel an order

1. Create a second order and **Cancel** it.

*Expected result:* status Cancelled; it cannot be received.

#### Scenario 5.3.4 — Receiving bay (V-SIAS)

1. Have the trainer send a V-SIAS consignment to the shop.
2. **Inventory → Receiving Bay**.

*Expected result:* the dock shows tracking code, source, line count, status. The navbar Inventory menu shows a count badge on Receiving Bay.

3. Open it, flag any non-catalog line, **Send to GRV**, post.

*Expected result:* stock booked; the dock is cleared from the bay.

4. On another dock, **Cancel dock**.

*Expected result:* it disappears without booking stock.

### 5.4 Transfers

#### Scenario 5.4.1 — Bar requests stock from the warehouse (Must pass)

1. As `train_cashier` on the POS: **Stock request → New Requisition**. Destination Main Bar, source Warehouse, Mosi × 48.

*Expected result:* it appears under **My Pending Requests**. Stock has not moved.

2. As `train_mgr` at the Warehouse: **Transfer Stock → Outgoing (To Dispatch)**. The request is listed. **Dispatch**.

*Expected result:* Warehouse Mosi −48. If V-SIAS is in the path, the inspection asks for **Issuer** and **Checked by**, a dispatch note, and a seal photo. Without V-SIAS, dispatch completes directly.

3. As `train_cashier`: **Incoming (To Receive)** shows the transfer. **Accept stock**.

*Expected result:* with V-SIAS, the inspection opens and Receive stays locked until approved; it collects **Deliverer** and **Receiver**. After approval, Main Bar Mosi +48.

4. Try to dispatch with quantity 0.

*Expected result:* "Quantity must be greater than zero."

#### Scenario 5.4.2 — Approvals panel

1. As `train_mgr`, click the bell **Approvals**.

*Expected result:* a panel opens over the page with the V-SIAS pending list. The first time it asks for your V-SIAS login.

2. If the panel is blank or says the content is blocked, click **Open in new tab**.

*Expected result:* the same list opens in a new browser tab.

3. Approve the pending inspection in V-SIAS.

*Expected result:* the panel closes and the Transfers page reloads; the badge count drops.

### 5.5 Reports and end of day

#### Scenario 5.5.1 — Read today's trading (Must pass)

1. **Management → Master Reports**. The range defaults to today.

*Expected result:* the five tiles reflect this training day. **Receipts** lists each sale; the return from 2.11 carries a red **Refund** badge. Refunds tile equals that credit note.

2. Tap a receipt row.

*Expected result:* the line breakdown opens; voided/refunded lines are labelled.

3. **View** on a row.

*Expected result:* the printable receipt opens in a window.

#### Scenario 5.5.2 — Filters

1. Change **All employees** to `train_cashier`.

*Expected result:* only that cashier's receipts remain; the tiles recalculate.

2. Change **All stores** to **Restaurant**.

*Expected result:* the list empties if no sales were made there.

3. Click **‹**.

*Expected result:* both dates move back one day and the page reloads.

4. Tap the date range and pick the whole week.

*Expected result:* the range shows the two dates and the report covers the week.

#### Scenario 5.5.3 — Items, Categories, Taxes

1. **Items**.

*Expected result:* Mosi is near the top by revenue, with items sold and gross sales.

2. **Categories**.

*Expected result:* Lagers, Soft drinks, Food, and Uncategorized if any product has no category.

3. **Taxes**. Tap the VAT row.

*Expected result:* the drilldown lists each receipt with taxable amount and tax at 16%. **Print Breakdown** prints only that list.

#### Scenario 5.5.4 — Refund from reports

1. **Receipts** → red arrow icon on a paid sale.

*Expected result:* the Refund screen lists remaining quantities per line. Pick quantities, a **ZRA refund reason**, confirm.

*Expected result:* stock restored, credit note posted, the sale shows as refunded. "Nothing left to refund on this ticket" appears if you try again on a fully refunded sale.

#### Scenario 5.5.5 — Export

1. **Export**.

*Expected result:* print preview with the header "Sales Summary", category, period, and generated time. The filter bar and tabs are not printed.

#### Scenario 5.5.6 — Reports on a phone

1. Open Master Reports on the phone.

*Expected result:* the date range spans the full width with arrows at the edges; store and employee are full-width; Export is a full-width button. Tiles form two columns with Gross profit across the bottom. Tab pills scroll sideways. Each receipt is a card with labelled values and a full-width **View** and **Refund**. Tapping a card opens the breakdown full screen.

#### Scenario 5.5.7 — Z-Read (Must pass)

Situation: closing the day.

1. Every station has ended its shift (scenario 2.13).
2. **Management → End of Day (Z-Read)**. Date today, time 00:00 to 23:59, workstation Main Bar. **Filter**.

*Expected result:* totals by payment method and sales for the window. **Print Z-Read** prints only the report.

3. Repeat for Restaurant.

4. On Enterprise, open **ZRA Smart Invoice**.

*Expected result:* **Pending sales** is 0. If not, read section 6.

### 5.6 Members

1. **Members** (dashboard → Staff & Users → Members, or `index.php?page=members`). **Register Member**: **Chanda** with phone **0977000001**.
2. Register another person with the same phone.

*Expected result:* refused; the phone is the member's ID.

3. **History** on Chanda after the sale in 2.7.

*Expected result:* the sale is listed; the points balance has moved.

### 5.7 Locations and tables

#### Scenario 5.7.1 — Location edit queued for the developer

1. **Management → Locations**. Edit Restaurant: tick **Goods receiver**. Save.

*Expected result:* the row is highlighted as pending. The Management menu shows a warning badge. Nothing changes until the developer approves (scenario 7.2).

#### Scenario 5.7.2 — Table settings (Pro+)

1. **Management → Table Settings → Add New Table**. Name **T7**, zone **Patio**, capacity 4, location Restaurant.

*Expected result:* T7 appears green on the POS floor plan at Restaurant.

### Assessment — manager

| # | Checkpoint | Pass |
|---|---|---|
| M1 | Creates, edits, and understands deactivate vs delete | |
| M2 | Approves a shift from Shifts & X-Reads and opens its sales | |
| M3 | Adds a sellable product with tax class and level-4 UNSPSC | |
| M4 | Builds a nested category with the right routing type | |
| M5 | Posts a stock take and finds it in the audit trail | |
| M6 | Runs inquiry → order → GRV and verifies stock at the receiver | |
| M7 | Dispatches and accepts a transfer, including the V-SIAS steps when present | |
| M8 | Opens Approvals, signs in to V-SIAS, falls back to Open in new tab if needed | |
| M9 | Reads Receipts/Items/Categories/Taxes and explains each tile | |
| M10 | Refunds from reports with a ZRA reason | |
| M11 | Produces a Z-Read per station and checks the ZRA pending queue | |
| M12 | Knows which of their actions need the developer's approval | |

---

## 6. ZRA Smart Invoice (Enterprise) — administrator and manager

Train this on a **sandbox** device only. The live device must be initialized once, by the person doing go-live, following [ZRA_GO_LIVE.md](ZRA_GO_LIVE.md).

### Scenario 6.1 — Read the page

1. **Management → ZRA Smart Invoice**.

*Expected result:* four tiles: VSDC service, Pending sales, Fiscalized sales, Unsynced products. A device card with TPIN, branch, serial, taxpayer, SDC ID, MRC. Unfiscalized paid sales. Recent fiscal receipts.

2. Match the badge to the SDC ID.

*Expected result:* **Device initialized** with an SDC ID present; **Not initialized** when empty.

### Scenario 6.2 — Test connection (safe, repeatable)

1. **Test connection**.

*Expected result:* a green message (000 "Succeeded" or 902 "Device is already initialized") and the hint "Next: Sync standard codes." Either is fine.

2. Read the table in the user guide (section 11.1) for a red or amber message. The 404 "Smart Invoice app is not deployed" and the 999 "unreachable" messages are support issues, not something to fix by clicking Initialize.

### Scenario 6.3 — Initialize (once, trainer only, sandbox serial)

1. With the badge on **Not initialized**, **Initialize device**. Confirm.

*Expected result:* SDC ID and MRC fill in, badge turns green, the button becomes disabled.

Explain to trainees why the button is disabled afterwards and why they must never click it on a live till or recreate the `vsdc` container.

### Scenario 6.4 — Codes, classes, products (Must pass)

1. **Sync standard codes**.

*Expected result:* Standard codes count > 0.

2. **Sync UNSPSC classes**.

*Expected result:* "Stored N classification(s)"; UNSPSC classes count > 0.

3. **Register products**.

*Expected result:* "Synced N item(s)". The product from 5.2.1 step 3 (blank UNSPSC) is listed as failed with its class code. Fix it on the product form and register again until **Unsynced products** is 0.

### Scenario 6.5 — A sale fiscalizes on its own

1. Ring a paid cash sale on the POS.
2. Open the receipt.

*Expected result:* SDC ID, receipt number, signature, and a QR appear at the bottom. On the ZRA page, Fiscalized sales +1 and the sale is under Recent fiscal receipts. The QR opens the ZRA portal page for that invoice.

### Scenario 6.6 — A sale that failed

1. Trainer: stop the `vsdc` container, ring a paid sale, start it again.

*Expected result:* the sale sits under **Unfiscalized paid sales** with status failed and the error under the badge. Pending sales = 1.

2. **Push** on that row.

*Expected result:* it fiscalizes and leaves the list.

3. Repeat with two failed sales and use **Retry failed / pending**.

*Expected result:* "Retried 2 sale(s): 2 synced…".

### Scenario 6.7 — Credit notes

1. Return an item against the fiscalized sale from 6.5 (scenario 2.11).

*Expected result:* the credit note fiscalizes and cites the original receipt number. Trying to credit more than the original has left gives a 931 message that says **Do not retry**.

### Assessment — ZRA

| # | Checkpoint | Pass |
|---|---|---|
| Z1 | Reads the four tiles and the badge correctly | |
| Z2 | Uses Test connection and interprets 000 / 902 / 404 / 999 | |
| Z3 | States when Initialize is and is not allowed | |
| Z4 | Runs codes → classes → products and clears unsynced items | |
| Z5 | Finds SDC ID and QR on a receipt | |
| Z6 | Clears a failed sale with Push or Retry after reading the error | |
| Z7 | Explains why a return must cite the original invoice | |

---

## 7. Developer

The developer account is the trainer's own. These scenarios are demonstrated, not practised by shop staff.

### Scenario 7.1 — System settings

1. **Management → System Settings**. Change **Business name**, add a receipt header line (TPIN, address) and footer. Save.

*Expected result:* the navbar brand and the next receipt show the new text.

2. Change the three colours.

*Expected result:* the POS picks them up on the next load.

### Scenario 7.2 — Approve a location edit

1. **Management → Locations → Review Pending Edits**. The edit from 5.7.1 is listed. **Approve**.

*Expected result:* the row is no longer highlighted; the Management badge disappears; Restaurant is now a Goods receiver.

2. Reject a second pending edit.

*Expected result:* the location reverts to its saved values.

### Scenario 7.3 — Location rules

1. Try to delete a location that still has stock.

*Expected result:* "This location still has stock and cannot be deleted."

2. Try to delete one with sales history.

*Expected result:* "This location has sales history and cannot be deleted."

### Scenario 7.4 — License tier walk-through

1. Set tier **Lite**. Sign in as `train_waiter`.

*Expected result:* no Tabs, no split pay, no Got it/Later, no Pickup. Opening `index.php?page=members` shows "This feature requires Pro or higher."

2. Set **Pro**. Sign in as `train_chef`.

*Expected result:* chef lands on the POS, not the kitchen. `page=kds` shows "This feature requires Pro+ or Enterprise."

3. Set **Pro+**. Sign in as `train_mgr`.

*Expected result:* kitchen screens return; **ZRA Smart Invoice** is not on the Management menu for the manager (it is for admin and dev).

4. Set **Enterprise**.

*Expected result:* ZRA on the menu for managers, Web Orders and the Online payment method on the POS, ZRA tax class on the product form.

### Scenario 7.5 — License lockout

1. Set a **lockout date** of yesterday. Save.

*Expected result:* immediately, `train_cashier` sees the full-screen "License expired" page and can only sign out. `train_mgr` is redirected to Reports and can open Reports, Z-Read, receipts, and print shifts, but any save is blocked. The developer is unaffected.

2. Clear the date.

*Expected result:* normal access returns.

---

## 8. Mobile and tablet scenarios (all roles)

Run these on the phone. They confirm the floor staff can work without a PC.

| # | Scenario | Expected result |
|---|---|---|
| P1 | Waiter signs in, taps the green **POS** pill | POS opens without opening the menu |
| P2 | Manager taps **Menu**, opens Management, taps Master Reports | Panel closes; reports load |
| P3 | Manager opens Master Reports and swipes the tab pills | Receipts, Items, Categories, Taxes scroll sideways |
| P4 | Manager taps a receipt card | Breakdown opens full screen; Close returns |
| P5 | Manager taps **‹** twice | Range moves back two days |
| P6 | Manager taps **Export** | Print dialog with the report header |
| P7 | Chef opens Live KDS Screen on a tablet | Tickets fill the screen; Cook/Done are large enough to tap |
| P8 | Manager taps **Approvals** | Panel opens; if blocked, **Open in new tab** works |
| P9 | Cashier opens **Stock request** and creates a requisition | Appears under My Pending Requests |
| P10 | Manager opens the Dashboard | Stat tiles scroll sideways; buttons are full width |

---

## 9. Problem-solving drills

Give the trainee the symptom only. They must find the cause and the right action.

| Symptom | Cause | Correct action |
|---|---|---|
| Product grid is blurred | No open shift at this station | Request a shift; manager approves |
| "Open a shift before checkout" | Shift ended or never approved | Same as above |
| Tile is grey | Zero stock at this station | Transfer in, produce (dishes), or GRV |
| **+** stops at a number | On-hand limit | Same as above |
| Launch POS reads "POS Disabled" | Location has POS enabled off | Change station, or ask the developer to enable it |
| Return refused: no original Smart Invoice | Return rung without picking the invoice | Switch Return on and select the invoice first |
| Return refused: exceeds original | Already credited | Do not retry; refund the right receipt |
| Sale missing from Receipts but stock went down | Lost stock (waste) was used | Check Audit Trail, action waste |
| ZRA badge green but 404 red message | Smart Invoice app not deployed in the device | Call support; do not Initialize |
| ZRA 999 | Device container stopped | Call support |
| ZRA 899 after 902 | Keystore lost | New serial per go-live guide; do not Initialize the old one |
| Register products: item failed with class code | Blank or family-level UNSPSC | Assign a level-4 code; register again |
| Approvals panel "content is blocked" | Browser refuses the frame | Open in new tab |
| Management menu has a warning badge | Location edit awaiting developer | Developer: Review Pending Edits |
| Chef lands on the POS | Tier below Pro+ | Developer sets tier |
| "License expired" page | Lockout date passed | Developer clears or extends the date |
| Category cannot be deleted | Has products or subcategories | Move products / delete children first |
| Vendor cannot be deleted | Has GRVs | Keep it; deactivate by convention |
| Transfer "must go through V-SIAS inspection" | Dispatch/receive attempted around V-SIAS | Use Dispatch / Accept stock, complete the inspection |
| Receipts report empty for a store | Filter set to a station with no sales | Switch to All stores |

---

## 10. Daily and periodic routines (checklists)

### Opening (cashier + manager)

- [ ] Count the float; request shift with that amount
- [ ] Manager approves
- [ ] Check the pickup badge and Tabs for anything left overnight
- [ ] Enterprise: ZRA page shows **Local VSDC is reachable**

### During service

- [ ] Payouts logged with a reason at the moment the cash leaves
- [ ] Breakages written off with **Lost stock**, not a sale
- [ ] Returns always against the original invoice with a reason
- [ ] Waiters use **Later** for food that must be cooked, **Got it** for food already served

### Closing (cashier + manager)

- [ ] X-Read reviewed; variances explained
- [ ] Shift ended with counted cash
- [ ] Z-Read printed per station
- [ ] Enterprise: ZRA pending sales = 0, or each pending row has been read and Pushed
- [ ] Unpaid tabs reviewed

### Weekly (manager)

- [ ] Stock take on fast movers; adjustments carry a reason
- [ ] Audit trail review for waste and stock takes
- [ ] Unsynced products = 0 on the ZRA page
- [ ] Reports for the week: Items and Categories, exported

### Monthly (manager + developer)

- [ ] Full stock take
- [ ] Recipe Consumption against production
- [ ] User list reviewed; leavers deactivated
- [ ] Pending location edits cleared
- [ ] Backups verified (README)

---

## 11. Competency sign-off

| Trainee | Role | Chapter assessments passed | Mobile drills passed | Trainer | Date |
|---|---|---|---|---|---|
| | | | | | |
| | | | | | |
| | | | | | |

A trainee is cleared for a live shift when every **Must pass** scenario for their role is marked passed and the problem-solving drills relevant to that role have been answered correctly.
