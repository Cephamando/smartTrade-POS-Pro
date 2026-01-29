# smartTrade-POS-pro 🚀

**smartTrade-POS-pro** is a robust, web-based Point of Sale and Inventory Management system designed by **Mando Chishimba** operating as **Odelia Enterprise Zambia**. It is built to handle multi-location hospitality environments (Bars, Kitchens, Stores) with strict inventory controls and shift management.

## 🌟 Features

### 🏢 Multi-Location Inventory
* **Centralized Warehousing:** Manage a Master Storeroom and transfer stock to sub-locations.
* **Location Types:** Distinct logic for **Stores** (Receive goods), **Bars** (Sell drinks), and **Kitchens** (Sell food/Consume ingredients).
* **Stock Transfers:** Request > Approve workflow for moving items between locations.

### 🛒 Point of Sale (POS)
* **Fast Checkout:** Barcode scanning support and quick-search.
* **Cart Management:** Discount and Tip handling.
* **Receipts:** Auto-generates thermal-printer friendly receipts with QR Codes.
* **Stock Checks:** Prevents selling items that are out of stock at the specific location.

### 🛡️ Controls & Security
* **Shift Management:** Mandatory "Open Shift" (Float count) and "Close Shift" (Cash count + Manager verification).
* **Variance Tracking:** Automatically calculates cash/stock discrepancies.
* **User Roles:** Admin, Manager, and Cashier permission levels.
* **Force Password Change:** New staff must update credentials on first login.

### 📊 Analytics
* **Real-time Reports:** Gross Profit (Revenue - Tax - Cost).
* **Breakdowns:** Sales by Payment Type, Employee, and Product.
* **Shift History:** Detailed audit logs of every shift closed.

---

## 🛠️ Tech Stack

* **Language:** PHP 8.2 (Native MVC Structure)
* **Database:** MySQL 8.0
* **Server:** Apache
* **Frontend:** Bootstrap 5, SweetAlert2, Chart.js
* **Infrastructure:** Docker & Docker Compose

---

## 🚀 Installation & Setup

### 1. Prerequisites
Ensure you have **Docker** and **Docker Compose** installed on your machine.

### 2. Clone & Configure
```bash
# Clone the repository
git clone [https://github.com/yourusername/smartTrade-POS-pro.git](https://github.com/yourusername/smartTrade-POS-pro.git)
cd smartTrade-POS-pro

# Create Environment File
cp .env.example .env
# Edit .env and set your database passwords!

3. Start the Server
docker-compose up -d --build
Access the application at: http://localhost:8090 (or the port defined in docker-compose).

🔐 Default Credentials
Role,Username,Password,Location
Super Admin,odelia_admin,password123,Main Store
Store Manager,store_manager,password123,Main Store
Barman,main_barman,password123,Main Bar
Chef,head_chef,password123,Kitchen

Note: Users will be prompted to change these passwords upon first login.

© Copyright
Odelia Enterprise Zambia All Rights Reserved.
# SmartTrade-pos
# SmartTrade-pos