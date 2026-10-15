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

Create these on **Office → Staff** before the session. Use simple passwords for training and delete the accounts afterwards.

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

*Expected result:* the Dashboard opens. The navbar shows Dashboard, Stock, Kitchen, Office, and a green **POS**.

4. **Logout**, then sign in as `train_chef`.

*Expected result:* Meal Production opens. The navbar shows Kitchen display, Prep, Menu, Stock, and Recipe use. There is no POS button.

**If it goes wrong:** a chef landing on the POS means the tier is Lite or Pro. Kitchen roles need Pro+ or Enterprise.

### Scenario 1.2 — Forced password change

1. As `train_admin`, edit `train_cashier` on **Users & Staff** and set a new password.
2. Sign in as `train_cashier`.

*Expected result:* if the account is flagged to change password, the Change Password screen appears and **Cancel** is hidden. Enter the current password, a new one of at least 6 characters, confirm, and save. The POS then opens.

3. Try a 4-character password.

*Expected result:* it is rejected.

### Scenario 1.3 — Know where you are (active page)

1. As `train_mgr`, open **Stock → Products**.

*Expected result:* **Stock** in the navbar is highlighted yellow with an underline. Open the Stock menu: **Products** has a yellow background, under Catalog.

2. Open **Office → Reports**.

*Expected result:* the highlight moves to Office, and **Reports** is the yellow entry.

### Scenario 1.4 — The navbar on a phone

1. On the phone, open `http://<shop-pc>:8090` and sign in as `train_mgr`.

*Expected result:* the brand is on the left, a green **POS** pill and a **Menu** button on the right.

2. Tap **Menu**.

*Expected result:* a dark panel drops down. Dashboard is a single row. Stock, Kitchen, and Office have a chevron. The bottom holds the workstation chip and your name.

3. Open your name and tap **Training**.

*Expected result:* the training picker opens. Close it.

4. Tap **Stock**, then **Office**.

*Expected result:* Stock expands with a yellow left rail; when you tap Office, Stock folds and Office opens. Only one is open at a time.

5. Tap **Stock** again, then **Stock levels**.

*Expected result:* the panel closes by itself and Stock levels loads.

6. Tap the green **POS** pill.

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

### Scenario 2.5 — Flutterwave and split pay (Must pass)

Situation: total ZMW 180. The customer pays ZMW 100 by Airtel Money and the rest by cash. Airtel is chosen on the Flutterwave panel, not as its own payment method.

1. Ring the items. **Charge**.
2. Choose **Split pay**. Payment 1 **Cash**, amount **80**. Payment 2 **Flutterwave**, amount **100**.

*Expected result:* the status reads **Covers the bill**. The Flutterwave panel says to take ZMW 100. **Complete transaction** stays locked.

3. On the Flutterwave panel choose **Airtel**, type the guest's number, and **Send MoMo prompt**. Wait until the panel turns green, then **Complete transaction**.

*Expected result:* the receipt lists Cash ZMW 80 and Flutterwave ZMW 100. Under Flutterwave it prints **Network: Airtel**, **Number** (the handset), and **Network txn** (the ID from Airtel). If Airtel does not return its own ID, the line is **Txn ID** with Flutterwave's transaction ID.

4. Ring another sale and pay the whole thing with **Flutterwave**, using **Card / other**.

*Expected result:* the receipt shows Flutterwave, the card type and last four digits, and the transaction ID.

5. Ring another and use a split row **Other** named **Bank transfer**.

*Expected result:* the receipt shows "Bank transfer".

### Scenario 2.6 — Tip and customer name

1. Ring a sale. In the payment window, tap **10%**.

*Expected result:* the tip field fills with 10% of the total and the total due updates.

2. Type **Mrs Banda** into **Customer Name**. Complete.

*Expected result:* the receipt shows Mrs Banda and a tip line.

### Scenario 2.6a — Tip given after the bill is paid (Must pass)

Situation: Mrs Banda paid and left. Five minutes later she comes back and hands the cashier ZMW 20 "for the service".

1. Tap the green **Tip** button in the POS toolbar.
2. In **Find the receipt**, type **Banda** (or the receipt number, the amount, or the cashier's name). Tap the matching receipt.

*Expected result:* the receipt's number, time, total, and server appear in the grey box. The amount field and quick buttons (5%, 10%, 15%, Round up) become active.

3. Enter **20**, **Paid by** Cash, **Credit to** the cashier who served. Save.

*Expected result:* "Tip recorded". The original receipt and its ZRA invoice are unchanged. The X-Read now shows a **Tips after payment** line and the expected cash is 20 higher.

4. Try to tip the same way with the amount empty, or without choosing a receipt.

*Expected result:* the form refuses; nothing is saved.

### Scenario 2.6b — Surcharge with a reason (Must pass)

Situation: a guest finishes a meal and asks for the leftovers to be packed.

1. Ring the sale. In the payment window, open the **Surcharge** drop-down and pick **Takeaway packaging** (the administrator's list; type to search it).

*Expected result:* the surcharge amount fills with that reason's default and the total due goes up by the same amount.

2. Change the amount to **8**. Complete the sale.

*Expected result:* the receipt shows a **Surcharge — Takeaway packaging** line of ZMW 8. On Master Reports → **Tips & Surcharges**, the reason appears with 1 receipt and ZMW 8.

3. Ring another sale, choose **Other**, type **Late-night service**, amount 15.

*Expected result:* the typed reason prints on the receipt and shows in the report under its own name.

### Scenario 2.6c — Paying with foreign cash (when Currency is enabled)

Situation: a tourist pays a ZMW 198 bill with a USD 20 note.

1. In the payment window, tap **Paying with foreign cash?**. Choose **USD**, type **20**.

*Expected result:* the kwacha equivalent (about ZMW 396 at a rate near 19.8) fills the **Total tendered** box and the change is shown in ZMW. The small line under the total shows the amount due in the enabled currencies.

2. Complete the sale.

*Expected result:* the receipt is in kwacha; the sale is a normal cash sale in reports.

### Scenario 2.6d — Card or mobile money through Flutterwave (when Payments is enabled)

1. Ring a sale. Choose **Flutterwave (Card / Mobile Money)**.

*Expected result:* a panel appears with **Send MoMo prompt** and **Card / other**, plus a network list (MTN, Airtel, Zamtel). If test keys are in use the panel is marked **TEST MODE**.

2. Mobile money: pick the network, type the guest's number, **Send MoMo prompt**.

*Expected result:* status changes to **Waiting for the customer**; the guest approves on the phone; the panel turns green "Flutterwave confirmed the payment". Tap **Complete transaction**.

*Expected result:* the receipt prints **Network**, **Number**, and **Network txn**. The same three values are on the X-Read under **FLUTTERWAVE**, on the Z-Read **Flutterwave payments** table, and on Master Reports in the Payment column.

3. Card: tap **Card / other**; a Flutterwave window opens. In the sandbox use the test card shown in Settings → Payments.

*Expected result:* the window closes on success, the panel turns green, and Complete finishes the sale. The receipt shows the card and the transaction ID. A declined card shows **Declined**; take another payment method.

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

*Expected result:* a window with opening float, sales by payment method, payouts, and expected drawer. Flutterwave sales are listed again under **FLUTTERWAVE** with the network, number, and transaction ID. **PRINT X-READ** prints it. The shift stays open.

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
| C5a | Records a tip against an already-paid receipt and finds it on the X-Read | |
| C5b | Adds a surcharge with the right reason and explains where it shows | |
| C5c | Takes foreign cash and gives change in kwacha (if enabled) | |
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

*Expected result:* the red pickup badge on the POS shows 1. A green bar names the order and the dish, and a chime plays. **Quiet** stops the sound. **Dismiss** hides that order. Tapping the order opens Pickup.

2. Open **Pickup**. Mark the dish collected.

*Expected result:* the badge drops to 0.

### Scenario 3.4 — Transfer or merge lines between tabs

Situation: one guest moves to Table 6.

1. **Tabs** → **Transfer / Merge**. Source tab Table 4. Tick one drink. Destination: new tab **Table 6**. Confirm **Transfer**.

*Expected result:* Table 4 has one line fewer; Table 6 exists with that line.

### Scenario 3.5 — Close a tab with split payment

1. Open Table 4 → **Charge**. Pay half by **Flutterwave** and half by **Cash**. Confirm the Flutterwave portion before completing.

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

1. **Menu**. Add **Chicken & chips**: Food category, selling price **85**.
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

*Expected result:* the ticket clears on the kitchen display, which stays silent. The waiter's POS shows the green ready bar, plays the chime, and the Pickup badge increments. "All orders clear" shows on the kitchen display when nothing is waiting.

### Scenario 4.5 — Chefs and stock

1. As `train_cook`, open **Stock Levels**.

*Expected result:* you can filter and look. Entering a physical count and saving is refused: "Kitchen staff have view-only access to stock."

### Scenario 4.6 — Recipe consumption report (head chef)

1. **Recipe use** (head chef has this in the navbar; a cook does not). Set today's date and the Kitchen location.

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

1. **Office → Staff → Add New User**. Full name, username `temp_cashier`, role **Cashier**, workstation **Restaurant**, password. Save.

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

1. Have `train_cashier` request a shift. On your PC, **Office → Shifts**.

*Expected result:* the pending shift is listed; approve it there.

2. Open **Master Shift Reports**, filter by today and `train_cashier`. Open the shift.

*Expected result:* the paid sales for that shift are listed. **Print shift** prints the X-Read.

3. The dashboard **Active shifts** card shows the same shift with a button into its sales.

### 5.2 Catalog and stock

#### Scenario 5.2.1 — Add a product correctly for ZRA (Must pass on Enterprise)

1. **Stock → Products**, then **New Product**. Name **Castle Lite 330ml**, SKU, category Lagers, unit, type Item, cost **12**, price **25**, POS visibility **Sellable on POS**.
2. ZRA tax class **Class A - Standard (16%)**. UNSPSC code: type **beer** and pick the level-4 code (for example `50202201`). **Save Product**.

*Expected result:* the product saves. The Unsynced products count on the ZRA page goes up by one until **Register products** is run.

3. Add a second product and leave UNSPSC blank.

*Expected result:* it saves, but it will be rejected when registered with ZRA ("Item class must be a level-4 UNSPSC"). Fix it before go-live.

#### Scenario 5.2.2 — Categories and routing

1. **Stock → Categories**. Add **Desserts**, type **Food / Meal**, parent **Food**.

*Expected result:* on the POS, **Food** drills down to **Desserts**. Items in it show **Got it / Later** (Pro+).

2. Try to delete **Food** while Desserts exists.

*Expected result:* "Remove subcategories before deleting this category."

#### Scenario 5.2.3 — Stock take (Must pass)

Situation: month-end count at Main Bar. The system says 48 Mosi; you count 46.

1. **Stock → Stock levels**. Location Main Bar. On Mosi, physical count **46**, reason **Month-end count**. **Finish & update stock**.

*Expected result:* Mosi shows 46. **Inventory Audit Trail** shows a stock-take row of −2 by you.

2. Have a cashier sell one Mosi during your count, then save a count that was made before the sale.

*Expected result:* the adjustment is applied to live stock, so the sale is not lost.

3. Enter a count that would take quantity below zero.

*Expected result:* the quantity stops at zero.

#### Scenario 5.2.4 — Audit trail

1. **Stock → History**. Filter today, action **Waste**.

*Expected result:* the Action type menu lists the real movements (Sale, Online sale, Waste, Stock take, and the others), not a single "All actions" line. The broken Mosi from 2.10 is listed with the cashier, the station, −1, and the quantity after.

2. Filter **Sale**, **Production**, **Transfer in**, **Goods received**, and **Stock take** in turn.

*Expected result:* each shows the rows from this training day.

### 5.3 Purchasing

#### Scenario 5.3.1 — Vendor

1. **Stock → Vendors → Add New Vendor**: name, contact, phone.
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

5. On the GRV line, set **Pack size** 24, **Packs** 10, **Pack price** 240. Supplier reference **INV-554**. Post.

*Expected result:* as you type, **Qty (units)** becomes 240, **Unit price** K10.00, **Line total** K2,400, and the footer shows 240 units / K2,400. After posting, Warehouse stock of Mosi +240, the product's cost price is K10.00, the audit trail shows a GRV row, and the PO is marked received. The GRV appears under **Recent goods received**; tap it to see the packs, units, and prices.

5a. Start another GRV line for Fanta: type **Qty (units)** 48 with pack size 12, then type **Unit price** 6.

*Expected result:* Packs becomes 4 and Pack price becomes 72. Changing the pack price back to 60 makes the unit price 5.00. The numbers always agree with each other.

6. Try to receive into **Main Bar**.

*Expected result:* refused unless Main Bar is a **Goods receiver** location.

#### Scenario 5.3.3 — Cancel an order

1. Create a second order and **Cancel** it.

*Expected result:* status Cancelled; it cannot be received.

#### Scenario 5.3.4 — Receiving bay (V-SIAS)

1. Have the trainer send a V-SIAS consignment to the shop.
2. **Stock → Receiving bay**.

*Expected result:* the dock shows tracking code, source, line count, status. The Stock menu shows a count badge on Receiving bay.

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

1. **Office → Reports**. The range defaults to today.

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

#### Scenario 5.5.2a — Tips and surcharges by staff

1. Open the **Tips & Surcharges** tab.

*Expected result:* four tiles — tips on receipts, tips after payment (with the cash part), all tips, surcharges. A table lists surcharges by reason (Takeaway packaging and Late-night service from 2.6b). Below it, one row per staff member with their receipts, tips on receipts, tips after payment, and total.

2. Find the cashier from 2.6a.

*Expected result:* their **Tips after payment** column shows 1 (cash 20.00) and their total includes it. This is the figure Payroll pulls in when tips are included in pay.

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
2. **Office → End of day**. Date today, time 00:00 to 23:59, workstation Main Bar. **Filter**.

*Expected result:* totals by payment method, a **Flutterwave payments** table (network, number, transaction ID), and sales for the window. **Print Z-Read** prints only the report.

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

1. **Office → Locations**. Edit Restaurant: tick **Goods receiver**. Save.

*Expected result:* the row is highlighted as pending. The Management menu shows a warning badge. Nothing changes until the developer approves (scenario 7.2).

#### Scenario 5.7.2 — Table settings (Pro+)

1. **Office → Tables → Add New Table**. Name **T7**, zone **Patio**, capacity 4, location Restaurant.

*Expected result:* T7 appears green on the POS floor plan at Restaurant.

### 5.8 Searchable drop-downs (all screens)

#### Scenario 5.8.1 — Find without scrolling

1. **Goods Received**: click the **Product** box on a line and type **mos**.

*Expected result:* the list narrows to Mosi while you type. Type the product's SKU or barcode instead — it still finds it.

2. **Vendors** box: type part of the contact person's name or phone.

*Expected result:* the vendor is found even though only the company name is displayed.

3. **Users & Staff** → primary workstation box: type **ware**.

*Expected result:* Warehouse is the only option left. Press Enter to pick it; press Esc or clear the text to see everything again.

### 5.9 Payroll

Sign in as `train_manager` (or admin). Needs the training staff from 0.2.

#### Scenario 5.9.1 — Statutory rules

1. **Office → Payroll → Statutory rules**.

*Expected result:* PAYE bands 0–5,100 at 0%, to 7,100 at 20%, to 9,200 at 30%, above at 37%; NAPSA 5% capped; NHIMA 1%; 26 working days; tips included in pay but not taxed (tick **Tax tips** if your accountant says otherwise).

2. In **Quick PAYE check** type **8,000**.

*Expected result:* PAYE 670 on a taxable 8,000 (0 + 400 + 270). Nothing is saved by the check.

#### Scenario 5.9.2 — Components and employee (Must pass)

1. **Components → Add**: **Housing allowance**, earning, fixed, 500, taxable. Add **Staff loan**, deduction, fixed, 0 (amount set per person).
2. **Employees → Add**: the training cashier. Link to the `train_cashier` login, pay type **Monthly**, basic **8,000**, NRC, NAPSA and NHIMA numbers, bank. Under standing components set Housing 500 and Staff loan 500. Save.

*Expected result:* the employee is listed as active with their pay type and basic.

#### Scenario 5.9.3 — Pay run (Must pass)

1. **Pay runs → New run**: title **Training month**, this calendar month, pay date the 25th. Create.

*Expected result:* the run opens in **Draft** with one slip per active employee. The cashier's **Tips** box already holds the tips from 2.6 and 2.6a.

2. Set the cashier's **Days worked** to 26. **Recalculate**.

*Expected result (without tips):* gross 8,500 (8,000 + 500 housing); NAPSA employee 425; taxable 8,075; PAYE 692.50; NHIMA 80; loan 500; **net 6,802.50**. Employer NAPSA 425 and NHIMA 80 are shown separately.

3. Set days worked to 13 and recalculate.

*Expected result:* basic halves to 4,000 and everything below it reworks.

4. Tap **Adjustments** on the slip, add an earning **Overtime 300**, apply, recalculate.

*Expected result:* gross rises by 300; the slip lists Overtime as its own line.

5. **Approve**.

*Expected result:* the inputs lock; **Mark paid** and **Reopen** appear. **Delete** is gone.

6. **Print payslips**.

*Expected result:* one page per employee with earnings, deductions, employer contributions, and net pay. Printing a single slip works from the slip row.

7. **Mark paid**.

*Expected result:* status **Paid**, and an expense for the total net pay is booked at the run's location.

8. Try to delete a paid run.

*Expected result:* refused; only drafts can be deleted.

### 5.10 Online ordering portal (Enterprise)

#### Scenario 5.10.1 — Switch it on

1. **Office → Settings → Online ordering**. Enable, pick **Main Bar** as the fulfilling location, title **Training Café Online**, delivery fee 25, tick Flutterwave payment and delivery. Save. **Open page**.

*Expected result:* a mobile-styled page in the shop's colours with the menu. Only sellable items in stock at Main Bar appear.

#### Scenario 5.10.2 — Customer orders (play the customer)

1. Add 2 × Fanta and 1 × Mosi. Open the basket, **Checkout**.
2. **Register**: name **Jane Customer**, phone **0977 123 456**, PIN **1234**.

*Expected result:* registration refuses a number that is not a Zambian mobile (must start 07/09 and be 10 digits). After registering, you are back at checkout.

3. Choose **Delivery**, type an address, and place the order. Payment is Flutterwave only.

*Expected result:* the Flutterwave window opens for card or Zambia mobile money. After payment, the order shows a status tracker and the total includes the delivery fee. **My orders** lists it as paid.

#### Scenario 5.10.3 — Shop receives it

1. On the POS at Main Bar, the **Web Orders** badge shows 1. Open it.

*Expected result:* the order shows **PAID ONLINE** once Flutterwave confirms, order type Delivery, phone and time, and the lines. Stock for Fanta and Mosi has already gone down. An unpaid order is marked **UNPAID** / **Awaiting Flutterwave** and is not charged again at the till.

2. When the items are ready, hand the paid order over.

*Expected result:* a normal receipt; the customer's tracker moves to **handed over**. No second payment is taken.

### 5.11 Events (functions, classes, and invitations)

Sign in as `train_mgr`. The shop from 5.10 must be on, because a published event is a page on that shop. Build one training event and **remove it before you leave**. Do not leave a practice function on the live shop.

Money on the shop is shown as `K` with two decimals. The staff page says ZMW.

#### Scenario 5.11.1 — The event file and its cover (Must pass)

1. **Office → Events → New event**.
2. **Name** `Sunday tasting`. **Starts** a future Sunday at 18:00, **Ends** 21:00. **Store** Main Bar. **Venue note** `Private room`.
3. **Visibility** `Private invite`. **Status** `draft`. **Capacity** `20`. **Deposit** `0`. **Guest action** `Apply`.
4. **Short line** `A small tasting`. **Story** `Three courses, one table.`
5. Under **Cover**, **Choose picture**. Pick a JPEG. Drag and use **Zoom in** / **Zoom out** until the preview is the frame you want. **Save event**.

*Expected result:* the event file stays open and the cover preview shows that frame. The picture's name in the library is readable (the file name, cleaned up), not a string of random letters. Reload the page: the same cover is still there.

6. **Choose picture** again, frame a different photo, and **Save event**.

*Expected result:* the new frame stays after a reload. The first picture remains in the library.

7. **All events**. Use the month arrows until you reach that Sunday.

*Expected result:* `Sunday tasting` sits on that day. Click the name to come back to the file. **Capacity** is the headcount you are planning for. The shop does not stop guests at that number; tier and choice limits do (5.11.2 and 5.11.3).

**If it goes wrong:** the cover snaps back to the old picture, or the page says **The framed cover did not upload. Try a smaller picture.** Choose the picture again, frame it, and save a smaller JPEG.

#### Scenario 5.11.2 — Payment tiers and the guest word (Must pass)

Tiers are added after the event exists. The price is per person. A limit of `0` means no limit. A deposit, if you set one, is collected first.

1. On **Payment tiers**, add **Regular** at `100`, limit `10`. Add **VIP** at `250`, limit `4`. Add **VVIP** at `500`, limit `0`.
2. Choose **Other (custom)**. The name box appears. Type `Student`, price `50`, limit `2`. **Add tier**.
3. Try to add `Student` again.

*Expected result:* four rows, each ending in **each**. VVIP has no limit. The second Student is refused: `Student is already a tier.` An empty custom name is refused: `Name the custom tier.`

4. Set **Guest action** to `Register` and **Save event**.

*Expected result:* the line under Guest action stays: guests see this word on the shop. **Apply** suits a request, **Register** a class or ticket, and **RSVP** an invitation. The guest list inside the office still uses invited, applied, accepted, waitlisted, declined, and cancelled. Those words do not change when the shop button changes.

#### Scenario 5.11.3 — Choices, the run of show, and tasks

1. **Choices**: group `Meal`, name `Beef`, price `0`, limit `8`, then **+**. Add `Meal` / `Fish` the same way. Add group `Seat`, name `Window`, price `0`, limit `0`.

*Expected result:* each row reads group, name, and price. Guests later pick one item from each group. A choice at its limit shows **(full)** and cannot be picked by a new guest.

2. **Run of show**: 18:00, moment `Welcome drinks`, notes `At the door`. **Add**.
3. **Tasks**: `Print place cards`. **Add task**. Click the task.

*Expected result:* the task gains a tick and a line through the words. Click it again to clear the tick.

#### Scenario 5.11.4 — Publish it, and the scrolling advert (Must pass)

An event appears on the shop only when **Visibility** is **On the shop** and **Status** is **open**. Private, draft, closed, and cancelled stay off the shop.

1. Set **Visibility** to `On the shop`, **Status** to `open`, **Deposit** still `0`. **Save event**.
2. Open the shop (**System Settings → Online ordering → Open page**, or the public shop address).

*Expected result:* under the hero, a dark strip scrolls `Sunday tasting` with its cover, name, and date. Rest the pointer on the strip and it pauses. **Events** in the shop menu opens a list headed **Ask for a place, then keep the link we give you.** The card shows the short line and the framed cover.

3. Open the event.

*Expected result:* the heading is **Register** and the button is **Register**. The four tiers show with `K` prices **each**. The line under the heading says tier prices are per person. A full tier is marked **(full)** and its button is dead.

4. Set **Status** back to `draft`, save, and reload the shop.

*Expected result:* the strip and the public page are gone. A saved link says the function is private, closed, or the link is wrong.

5. Put it back to **On the shop** and **open** before the next scenario.

#### Scenario 5.11.5 — A guest registers and pays (Must pass)

1. On the shop event, play the guest. Name `Amina Banda`, party `2`, a phone and an email, tier **VIP**. Leave the tier blank and try to register.

*Expected result:* the form asks you to choose a tier.

2. Choose **VIP** and **Register**.

*Expected result:* **Registration received. Keep this page — it is your private link.** The page is only hers. It shows Tier **VIP · K250.00 each**. Two people at 250 is 500, and the deposit is 0, so the line is **Balance due K500.00** and the button is **Pay balance**.

3. On the staff event, find Amina. Her row shows VIP. Open **Invite link** in a new tab and confirm it is the same private page.
4. Set **Deposit** to `200` and **Save event**. Reload her private page.

*Expected result:* **Deposit due K200.00**. The deposit is taken first. The rest of the 500 becomes the balance.

5. On her private page, **Pay now** while the deposit is still due. With Flutterwave test keys from 7.1b, the Flutterwave window opens and a successful payment is marked paid when she returns. With no keys, the page says Flutterwave is not enabled and nothing is stored. Training continues with cash.
6. On the staff **Payments** card, choose Amina, amount `200`, purpose **Deposit**, **Record cash**. Reload her page.

*Expected result:* **Paid K200.00** and **Balance due K300.00**. The staff list shows the cash deposit as **paid**.

7. Record cash `300`, purpose **Balance**. Reload her page.

*Expected result:* nothing is due. Both rows are on the event.

Changing **Guest action** changes only the public words:

| Guest action | Heading | Button | After they submit |
|---|---|---|---|
| Apply | Apply for a place | Apply | Application received. Keep this page — it is your private link. |
| Register | Register | Register | Registration received. Keep this page — it is your private link. |
| RSVP | RSVP | RSVP | RSVP received. Keep this page — it is your private link. |

If a deposit is set, the shop adds that the deposit is collected on the private page first.

#### Scenario 5.11.6 — Invite, reply, and email

1. **Invite someone**: name `Brian Phiri`, an email, party `1`, tier **Tier later**. Leave the message or use `{name}`, `{event}`, `{when}`, and `{link}`. **Save invitation**.

*Expected result:* Brian appears as **invited**. **Invite link** opens his private page. If **System Settings → Email** has no host or from address, the note says mail is not set up yet and the link is still created.

2. On Brian's page choose **No, I can't** and **Save reply**. Do not pick a tier.

*Expected result:* **You are marked as unable to attend.** Declining does not ask for a tier. The pay box is hidden. Choosing **Yes, I'll be there** does ask for a tier when the event has tiers, and saves the meal and seat.

3. On the staff row, change his status to **waitlisted**, then to **accepted**. **Check in**.

*Expected result:* the badge follows the list. After check-in a green tick sits by his name and the button reads **Undo**.

4. **Mass email**: audience **Accepted**, subject `You are invited: {event}`, send.

*Expected result:* a log line per address. **sent** when Email is filled in and the mailbox accepts it. **failed** when it is not. `{name}`, `{event}`, `{when}`, and `{link}` are filled in for each guest. Guests with no email are skipped.

5. **Office → Settings → Email**. Fill **SMTP host**, **port** (587, or 465 for a direct secure connection), **username**, **password**, **from address**, and **from name**. **Save email**.

*Expected result:* the password box is empty afterwards and says the password is saved. A later save with a blank password keeps the old one. Invitations then send; until then, every guest link still works.

#### Scenario 5.11.7 — Remove the training event (Must pass)

1. On the event, **Remove event**. Read the warning. Confirm **Remove event**.

*Expected result:* you are back on the calendar and `Sunday tasting` is gone. Guests, tiers, choices, payments, and the shop page go with it. The cover pictures stay in the library. The shop advert no longer scrolls that event.

**If it goes wrong:** cancelling the dialog leaves the event in place. Use **Remove event** only for a function you mean to delete. To hide one you may bring back, set **Status** to `closed` or **Visibility** to `Private invite` instead.

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
| M13 | Posts a GRV in packs and explains units, unit price, and line total | |
| M14 | Reads the Tips & Surcharges tab and reconciles it with the X-Read | |
| M15 | Creates an employee, runs payroll, checks PAYE/NAPSA/NHIMA, prints payslips | |
| M16 | Receives a web order on the POS and hands over a prepaid one | |
| M17 | Publishes an event with tiers and a framed cover, takes a registration, records the deposit then the balance, and removes the training event | |

---

## 6. ZRA Smart Invoice (Enterprise) — administrator and manager

Train this on a **sandbox** device only. The live device must be initialized once, by the person doing go-live, following [ZRA_GO_LIVE.md](ZRA_GO_LIVE.md).

### Scenario 6.1 — Read the page

1. **Office → ZRA invoices**.

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

1. **Office → Settings**. Change **Business name**, add a receipt header line (TPIN, address) and footer. Save.

*Expected result:* the navbar brand and the next receipt show the new text.

2. Change the three colours.

*Expected result:* the POS picks them up on the next load.

### Scenario 7.1a — Surcharge reasons (administrator or developer)

1. **Office → Settings**, POS surcharges tab. Add **Takeaway packaging**, default amount 10. Add **Late-night service**, default 5%. Save each.

*Expected result:* both appear in the table and in the POS surcharge drop-down on the next sale. Switching one **off** hides it from the POS without deleting past sales that used it.

### Scenario 7.1b — Payments (Flutterwave live)

1. **Office → Settings**, Payments tab. Enable, choose **Live**, and paste the v4 **client ID**, **client secret**, and webhook hash from Flutterwave → Settings → API keys. Save. **Test connection**.

*Expected result:* "Keys accepted" and the badge **LIVE**. Copy the webhook URL and the same secret hash into the Flutterwave dashboard. A confirmed charge marks the shop order paid even if the customer closed the browser; it stays on Web Orders until Hand Over and is not filed with ZRA from the webhook. The POS now offers **Flutterwave** as a payment method (scenario 2.6d). Only the first characters of saved keys are shown on the page. On the Customer ordering tab, an optional https **order webhook** receives a POST when an online order is ready and again when it is handed over. Leave it blank to send nothing.

### Scenario 7.1c — Currency

1. **Currency** tab. Enable, tick USD, ZAR, EUR, GBP, refresh every 60 minutes. **Refresh now**.

*Expected result:* the table shows live kwacha rates and the time they were fetched. Set a **house rate** for USD (e.g. 20.00) and save.

*Expected result:* the USD row shows the house rate as the one in use; the POS converts with it (scenario 2.6c). Remove it to go back to live.

2. In the quick converter type **100 USD → ZMW**.

*Expected result:* the kwacha amount at the rate in use.

### Scenario 7.2 — Approve a location edit

1. **Office → Locations → Review Pending Edits**. The edit from 5.7.1 is listed. **Approve**.

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

*Expected result:* kitchen screens return; **ZRA invoices** is not on the Office menu for the manager (it is for admin and dev).

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
| P2 | Manager taps **Menu**, opens Office, taps Reports | Panel closes; reports load |
| P3 | Manager opens Master Reports and swipes the tab pills | Receipts, Items, Categories, Taxes scroll sideways |
| P4 | Manager taps a receipt card | Breakdown opens full screen; Close returns |
| P5 | Manager taps **‹** twice | Range moves back two days |
| P6 | Manager taps **Export** | Print dialog with the report header |
| P7 | Chef opens Kitchen display on a tablet | Tickets fill the screen; Cook/Done are large enough to tap |
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
| Shop advert does not show the function | Visibility is not On the shop, or status is not open | Set both, save, and reload the shop |
| Shop still says Apply for a place | Guest action is Apply | Set Register or RSVP and save |
| Framed cover snaps back to the old picture | The framed file did not upload | Frame it again; use a smaller JPEG if the page says so |
| "Mail is not set up yet" on an invitation | Email host or from address is empty | The guest link is still valid. Fill System Settings → Email |
| Mass email line says failed | Mailbox is not set up, or it rejected the message | Read the log; fix Email, then send again |
| "already a tier" | That name is already on this event | Remove it, or type a different custom name |
| Tier "does not have enough places left" | The party would pass that tier's limit | Lower the party or raise the limit. A limit of 0 means no limit |
| Pay now says Flutterwave is not enabled | Payment keys are missing | Record cash on the event, or add test keys under Payments |
| The Guide says it is not set up | Gemini key is missing | Add it in System Settings → Guide |

---

## 10. Customer shop and events (Enterprise)

### Scenario 10.1 — Place a web order

1. Without signing in, open `index.php?page=shop`.
2. Browse a category and add an item to the basket.
3. Open the basket and tap **Checkout**.
4. Enter a name and phone number (this creates a customer account) and choose a PIN.
5. Place the order. Payment opens in Flutterwave (card or Zambia mobile money only).

*Expected result:* The order shows in My Orders with a status of Received.

### Scenario 10.2 — Fulfill the web order

1. Sign in as `train_cashier` on the fulfilling POS.
2. Notice the Web Orders badge has gone up. Click **Web Orders**.
3. Open the new order.
4. A paid online order requires no further payment. An unpaid order is still waiting on Flutterwave; do not take cash for it.
5. Mark the items ready, then tap **Hand Over**.

*Expected result:* The sale completes, printing the receipt.

### Scenario 10.3 — Create an event

1. As `train_mgr`, open **Office → Events**.
2. Tap **Create Event**. Give it a title, date, and time.
3. Add a ticket tier (e.g. "General Admission") and save.
4. Toggle the event to **Published**.

*Expected result:* Customers see the event advertised on the shop landing page and can buy a ticket.

### Scenario 10.4 — Ask the guide

1. As any staff member, open the back-office **Dashboard**.
2. Click the **Guide** button in the bottom right (or inside the phone Menu).
3. Type: "How do I add a new user?"
4. Tap **Ask**.

*Expected result:* The guide replies with the steps and provides an "Open Users" link.

### Scenario 10.5 — Sync the guide cache

1. As `train_admin`, open **Office → Settings**.
2. Open the **Guide** tab.
3. Paste a Gemini API key, pick a model (e.g. Gemini 3.8 Flash), and **Save**.
4. Click **Sync Documentation**.

*Expected result:* The label updates to "Cache ID:..." The guide will now use cached manuals.

### Assessment — Enterprise

| # | Checkpoint | Pass |
|---|---|---|
| E1 | A guest places an order on the customer shop | |
| E2 | A cashier completes a pay-at-counter web order on the POS | |
| E3 | A manager creates a published event with tickets | |
| E4 | A staff member asks the Guide a question and opens the named screen | |
| E5 | A manager caches the manuals via Sync Documentation in Settings | |

---

## 11. Daily and periodic routines (checklists)

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
- [ ] Events for the coming week: deposits, guest list, and the shop advert shows only functions that should be public

### Monthly (manager + developer)

- [ ] Payroll run created, tips checked, approved, payslips printed, marked paid
- [ ] Full stock take
- [ ] Recipe Consumption against production
- [ ] User list reviewed; leavers deactivated
- [ ] Pending location edits cleared
- [ ] Backups verified (README)

---

## 12. Competency sign-off

| Trainee | Role | Chapter assessments passed | Mobile drills passed | Trainer | Date |
|---|---|---|---|---|---|
| | | | | | |
| | | | | | |
| | | | | | |

A trainee is cleared for a live shift when every **Must pass** scenario for their role is marked passed and the problem-solving drills relevant to that role have been answered correctly.
