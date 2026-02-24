# OdeliaPOS

A highly scalable, multi-location Point of Sale and Inventory Management System built for retail, hardware, and hospitality businesses. Engineered with a lean native PHP architecture and Docker containerization.

## 🚀 Key Architecture

OdeliaPOS utilizes a single-codebase architecture with **Dynamic Feature Toggling**. Instead of maintaining separate apps for different business types, features are hidden or exposed based on the active License Tier.

### 🏢 Licensing Tiers (Product Matrix)

| Feature Module | Lite (Retail/Kiosk) | Pro (Multi-Store) | Hospitality (F&B) |
| :--- | :---: | :---: | :---: |
| Basic POS & Shift Management | ✅ | ✅ | ✅ |
| In-Stock Only Filtering | ✅ | ✅ | ✅ |
| Multi-Location Switching | ❌ | ✅ | ✅ |
| Hold/Open Tabs & Split Bills | ❌ | ✅ | ✅ |
| Advanced Inventory (GRVs/Transfers)| ❌ | ✅ | ✅ |
| Member Loyalty Tracking | ❌ | ✅ | ✅ |
| Kitchen Display System (KDS) | ❌ | ❌ | ✅ |
| Digital Pickup Screen | ❌ | ❌ | ✅ |
| Recipe/Menu Portion Control | ❌ | ❌ | ✅ |

## 🛠️ Tech Stack
* **Backend:** Native PHP 8.2 (No heavy frameworks, highly optimized router)
* **Database:** MySQL 8.3
* **Frontend:** HTML5, Bootstrap 5.3, Vanilla JavaScript, Chart.js, SweetAlert2
* **Infrastructure:** Docker & Docker Compose

## 🎨 White-Label Ready
OdeliaPOS includes a built-in Developer settings panel. Log in with a `dev` account to instantly change the **Business Name**, **Theme Colors**, and **Receipt Headers/Footers** globally, allowing you to resell the software seamlessly to different clients.

## ⚙️ Quick Start Installation

1. **Clone the repository:**
   ```bash
   git clone [https://github.com/yourusername/pos-app.git](https://github.com/yourusername/pos-app.git)
   cd pos-app
Start the Docker Containers:

Bash
docker compose up -d
Initialize the Database:
Import the init.sql schema into your MySQL container (via Adminer or CLI).

Login Credentials:

Username: admin (or dev)

Password: posRoot123! (Default hash configured in the schema)

🔐 Security & Access Control
Global Router Authentication: Every request routes through public/index.php, ensuring strict session validation. Background APIs utilize session_write_close() to prevent UI blocking.

Role-Based Access: UI elements dynamically respond to roles (dev, admin, manager, cashier, chef, waiter, bartender).

Workflow Integrity: Food items cannot be bypassed at the register; they strictly route to the Kitchen Display System (KDS) and must be fulfilled via the Digital Pickup Screen.

Developed by Mando Chishimba - Odelia Enterprise
