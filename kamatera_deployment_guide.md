Here is the complete Markdown documentation covering the entire Kamatera setup, Nginx routing, ZRA VSDC integration, and security automations.

You can run this command from your `smartTrade-POS-Pro` project root to generate the file.

### Part 1: CLI Overwrite

```bash
cat << 'EOF' > kamatera_deployment_guide.md
# Kamatera VPS Deployment Guide: smartTrade-POS-Pro

This document outlines the production deployment architecture and configuration for the smartTrade POS system and Odelia Enterprise portfolio on a Kamatera Ubuntu VPS.

## 1. System Preparation & Security
The underlying host is secured and synchronized to the local timezone to ensure accurate logging and VSDC handshake validation.

* **Timezone Configuration:**
  ```bash
  sudo timedatectl set-timezone Africa/Lusaka

```

* **Security Packages:**
```bash
sudo apt-get install fail2ban unattended-upgrades -y
sudo systemctl enable --now fail2ban
sudo dpkg-reconfigure --priority=low unattended-upgrades

```



## 2. Nginx Reverse Proxy & SSL

The system uses Nginx as a reverse proxy to route subdomains to their respective Docker containers, bypassing port conflicts and securing traffic via Porkbun wildcard certificates.

* **Portfolio Configuration (`/etc/nginx/sites-available/portfolio.conf`):** Routes `odeliazm.com` to `https://127.0.0.1:8444`.
* **POS Configuration (`/etc/nginx/sites-available/smartrade.conf`):** Routes `smarTrade.odeliazm.com` to `http://127.0.0.1:8090`.
* *Note:* Includes `client_max_body_size 64M;` to permit large Excel imports (e.g., UNSPSC classifications).



## 3. ZRA VSDC (Tomcat) Integration

The ZRA Smart Invoice application is hosted in a Tomcat container.

* **Deploying the `.war` File:**
Due to Git ignoring `.war` files, the application must be manually uploaded to the host and injected into the container:
```bash
scp api/vsdc.war root@<VPS_IP>:/root/smartTrade-POS-Pro/api/
docker cp ~/smartTrade-POS-Pro/api/vsdc.war vsdc:/usr/local/tomcat/webapps/

```


* **Timezone Synchronization (Fixing "Future Date" Error):**
Tomcat runs in UTC by default. A `setenv.sh` file is injected to align the JVM with Lusaka time:
```bash
docker exec vsdc bash -c 'echo "export CATALINA_OPTS=\"-Duser.timezone=Africa/Lusaka\"" > /usr/local/tomcat/bin/setenv.sh'
docker restart vsdc

```



## 4. Automated Maintenance (Cron Jobs)

Two automated bash scripts are configured in the root directory to handle routine maintenance.

* **Automated SSL Renewals (`/root/renew_porkbun_ssl.sh`):**
Uses `jq` and the Porkbun API to fetch the latest wildcard certificates on the 1st of every month at 2:00 AM, avoiding manual Let's Encrypt ACME challenges.
* **Automated Database Backups (`/root/backup_pos_db.sh`):**
Extracts credentials dynamically from `.env`, runs `mysqldump` inside the `smartTradePOS_db` container, compresses the output to `.sql.gz`, and deletes backups older than 30 days. Runs daily at 3:00 AM.
EOF

```