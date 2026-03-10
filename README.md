# Odelia POS v2.0 (Enterprise Edition)

Odelia POS is a highly secure, multi-tier Point of Sale and ERP hybrid system designed for both Retail and Hospitality environments. It features robust shift management, real-time inventory tracking, multi-location architecture, and strict OWASP security compliance.

## 🚀 Key Features
* **Multi-Tier Licensing:** Adapts automatically to 'Lite', 'Pro', or 'Hospitality' modes depending on license.
* **Global Workstation Switching:** Managers can seamlessly hop between store locations within the same session.
* **Hospitality Suite:** Includes Table Management, Split Tabs, Kitchen Display System (KDS), and Recipe Consumption.
* **Enterprise Accounting:** Handles Partial Payments, Split Tenders, and generates cryptographically secure Z-Read end-of-day reports.
* **Security First (OWASP Hardened):** Built-in CSRF protection, Sub-Resource Integrity (SRI), strict Content Security Policies (CSP), HTTP Strict Transport Security (HSTS), and secure cookie enforcement.

## 🛠️ Technology Stack
* **Backend:** PHP 8.2 (PDO, Object-Oriented/Procedural hybrid)
* **Database:** MySQL / MariaDB
* **Frontend:** HTML5, Bootstrap 5.3, SweetAlert2, Chart.js
* **Infrastructure:** Docker & Docker Compose (Apache mod_rewrite & mod_headers enabled)

## 📦 Installation (Docker)
1. Clone the repository.
2. Ensure Docker and Docker Compose are installed.
3. Run `docker-compose up -d --build`
4. Access the system via `http://localhost` (or your configured domain).
5. The system will automatically provision the database on the first run using `init.sql`.

## 🔒 Security Compliance
Odelia POS v2.0 has been hardened against the OWASP Top 10. All static and dynamic files are protected via `.htaccess` server-level headers. Do not remove the `mod_headers` requirement from the Apache configuration, as this enforces XSS and Clickjacking protections.

---
*Copyright &copy; 2026 Mando N. Chishimba. All Rights Reserved. Powered by Odelia Enterprise.*
