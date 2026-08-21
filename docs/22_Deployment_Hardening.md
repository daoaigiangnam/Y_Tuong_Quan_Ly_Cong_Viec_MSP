# 22 — Deployment Hardening

## Purpose

This document defines the deployment boundary for the MSP/ITSM engineering baseline. The application may be developed with an installer and local defaults, but production deployment must expose only the dedicated `public/` web root and must inject secrets through environment variables or an equivalent secret manager.

## 1. Web-root boundary

The production web server must point to:

```text
<project>/public
```

It must **not** expose the repository root. This keeps `app/`, `database/`, `docs/`, `tests/`, `storage/` and `install.php` outside the HTTP document root.

The engineering repository intentionally retains `install.php` for initial provisioning. Before production exposure, the installer must be removed, disabled, or otherwise made unreachable.

## 2. Secrets and configuration

Production credentials must never be committed to Git. The runtime configuration reads database and mail settings from environment variables such as:

- `MSP_DB_HOST`
- `MSP_DB_PORT`
- `MSP_DB_NAME`
- `MSP_DB_USER`
- `MSP_DB_PASS`
- `MSP_MAIL_FROM`
- `MSP_MAIL_FROM_NAME`

The `.env` file is ignored by Git for local development. Production should prefer the hosting platform's secret store/environment configuration.

## 3. Upload isolation

User-uploaded files belong under `storage/uploads` and must not be placed under the public web root. If the application later needs public download URLs, files should be served through an authenticated controller or a controlled, non-executable object-storage path rather than by exposing the storage directory directly.

## 4. Session hardening

The application bootstrap enables:

- `HttpOnly` session cookies;
- `SameSite=Lax`;
- HTTPS-aware `Secure` cookies.

Production HTTPS must be mandatory at the reverse proxy/web server layer.

## 5. Installer control

`install.php` is an engineering/provisioning artifact. Production deployment procedure must include an explicit step to:

1. run installation/migration from a controlled administrative context;
2. verify schema and seed data;
3. remove or disable `install.php` before opening the application to users;
4. verify that `https://<host>/install.php` is unreachable;
5. verify that repository paths such as `/app`, `/database`, `/docs` and `/tests` are not web-accessible.

## 6. Backups

Before production migration and before destructive schema changes:

- take a database backup;
- verify the backup can be restored;
- retain a rollback point for the application release;
- document backup retention and restore ownership.

## 7. Logging and monitoring

Production must retain application/security logs sufficient to investigate:

- authentication failures;
- authorization denials;
- ticket and workflow changes;
- mail delivery failures;
- migration/deployment events;
- unexpected application errors.

Logs must not contain plaintext passwords, database credentials, session secrets or other sensitive values.

## 8. Rollback

Every production release must have a documented rollback procedure covering:

1. application artifact rollback;
2. database rollback or restore strategy;
3. cache/session invalidation if required;
4. health verification;
5. operator sign-off.

Database migrations must be assessed for reversibility before release. Irreversible migrations require a tested backup/restore strategy.

## 9. Automated gate

`tests/deployment_hardening_test.php` verifies the engineering baseline for the web-root boundary, environment-driven configuration, `.env` protection, upload isolation, session-cookie hardening and the existence of this runbook.

The automated gate proves the repository is **deployment-hardened by design**; it does not replace infrastructure configuration review, vulnerability scanning, backup restore testing or production UAT.
