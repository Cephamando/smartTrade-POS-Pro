# Odelia POS v2.0 - Comprehensive User Manual

## 1. System Access & Authentication
* **Login:** Enter your assigned username and password. The system utilizes secure CSRF-protected tokens.
* **Session Management:** Depending on your role, you will be redirected to the Cashier POS, Kitchen KDS, or Admin Dashboard.
* **Global Location Switcher:** If you have managerial access, click your current location in the top-right navigation bar to instantly switch your active workstation.

## 2. Shift Management
* **Opening a Shift:** Upon accessing the POS, cashiers must enter their starting float (Opening Cash).
* **Real-Time Shift Status:** Managers can click on any active shift from the Dashboard to see expected cash drawers, total revenue, and a drill-down of every transaction.
* **Closing a Shift (Z-Read):** Cashiers end their shift by declaring their physical cash. The system calculates overages/shortages and permanently archives the Z-Read report.

## 3. Point of Sale (POS) Operations
* **Adding Items:** Click products from the menu grid or use the barcode scanner.
* **Partial Payments & Split Tenders:** Click "Pay" and enter an amount lower than the total. The system will track the remaining balance. Multiple payment methods (e.g., Cash + MTN Money) can be used on a single ticket.
* **Table Management (Hospitality):** Assign orders to specific tables. Use the "Transfer Table" button to move a customer's tab seamlessly to another seating area.
* **Voids & Refunds:** Only authorized roles can void an item from a pending tab or process a refund for a closed sale.

## 4. Inventory & Stock Control
* **Receive Stock:** Add new inventory via the Purchase Order screen. It automatically updates average cost metrics.
* **Stock Transfers:** Move inventory physically between locations (e.g., HQ to Branch 1).
* **Recipe Consumption:** In Hospitality mode, selling a "Burger" automatically deducts the raw ingredients (buns, beef patties) mapped in the Menu Builder.

## 5. Kitchen Display System (KDS)
* Chefs monitor the KDS screen for incoming orders.
* Orders flash yellow when pending and green when marked as "Ready".
* Cashiers receive a real-time badge notification on the POS and Dashboard when food is ready for pickup.
 