# MSP ITSM RC1 — Production Deployment

## Target
PHP 8.3+, PDO MySQL, MySQL 8.0+, Apache or Nginx, HTTPS.

## 1. Files
Deploy the repository outside the web root, for example:

/var/www/msp-itsm

Point the web server DocumentRoot/root to:

/var/www/msp-itsm/public

Never expose app/, database/, docs/, tests/, storage/, or install.php.

## 2. PHP extensions
Required:
- pdo_mysql
- mbstring
- json
- openssl

Recommended:
- fileinfo

## 3. Database
Create a dedicated application database/user. Do not run the application with MySQL root in production.

Example:

CREATE DATABASE msp_itsm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'msp_app'@'localhost' IDENTIFIED BY 'CHANGE_ME';
GRANT ALL PRIVILEGES ON msp_itsm.* TO 'msp_app'@'localhost';
FLUSH PRIVILEGES;

Then apply, in order:
1. database/schema.sql
2. database/migrations/*.sql
3. database/seed.sql

The migration files are applied lexicographically.

## 4. Environment
Copy .env.example into your deployment secret/configuration system. Do not commit secrets.

The application already reads MSP_DB_* and MSP_MAIL_* environment variables.

## 5. Installer
install.php is for controlled provisioning only. Prefer CLI/database provisioning for production. If install.php is used:
1. restrict it to administrators/IP allowlist;
2. complete installation;
3. verify application login;
4. remove or disable install.php;
5. verify /install.php returns 404/403.

## 6. Storage
Create writable storage/uploads with the PHP-FPM/web-server account as owner. Do not make storage/ publicly executable.

## 7. HTTPS/session
Terminate HTTPS at the web server/reverse proxy. Force HTTP -> HTTPS. Keep HttpOnly, SameSite=Lax and Secure cookies enabled.

## 8. Cron
Run contract alerts daily, for example:

5 7 * * * /usr/bin/php /var/www/msp-itsm/cron/contract_alerts.php >> /var/log/msp-itsm-contract-alerts.log 2>&1

Use the actual PHP path and deployment path.

## 9. Smoke test
After deployment verify:
- login/logout
- dashboard
- RBAC deny/allow
- customer access
- service/contract access
- ticket create/detail/comment/assign/start/resolve/close
- customer reopen
- task lifecycle
- audit/history
- contract alert cron
- upload path
- HTTPS cookie flags

## 10. Backup/rollback
Take a database backup before release. Keep the previous application artifact and migration version. Use docs/25_Backup_Restore_DR_Runbook.md and docs/24_Production_GoLive_Readiness.md as the operational gate.

## 11. Important RC1 limitation
This release packages the current ITSM/MSP core for real-server UAT. The later Final Architecture layers (Communication Hub, Identity/Case Correlation, Requirement Governance, Evidence/Scope and AI/Call pipeline) are not part of RC1 yet.
