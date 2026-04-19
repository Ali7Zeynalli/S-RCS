# Changelog

All notable changes to S-RCS will be documented in this file.

---

## [1.3.2] - 2026-04-19

### 🐛 Bug Fixes — Deployment & Infrastructure

- **SSL certificate proper server cert** — Dockerfile previously generated certificate with `basicConstraints=CA:TRUE` (default) and no `subjectAltName`. Replaced with proper OpenSSL config: `CA:FALSE`, `extendedKeyUsage=serverAuth`, SAN with `localhost`, `*.localhost`, `srcs`, `*.srcs.local`, `127.0.0.1`, `0.0.0.0`. Eliminates `AH01906`, `AH01909`, `AH02217`, `AH02604` Apache warnings.
- **ServerName directive** — Added global and per-VirtualHost `ServerName localhost` to eliminate `AH00558: Could not reliably determine the server's fully qualified domain name`.
- **SSL Stapling disabled** — Self-signed certificates cannot retrieve issuer info, stapling was fundamentally broken. Commented out in `apache-config/000-default.conf`.
- **HTTP → HTTPS redirect port fix** — Previously Apache redirected to `https://host:<HTTP_PORT>` (same port), causing TLS ClientHello bytes (`\x16\x03\x01...`) to hit HTTP port, generating 400 Bad Request flood. New `SRCS_HTTPS_PORT` env var (docker-compose → Dockerfile ENV → Apache `PassEnv`) ensures redirect uses the correct HTTPS port.
- **Install Wizard "Cannot read properties of undefined"** — Frontend `install.php` used `value.manual_steps[value.system_detected]` without optional chaining. When backend returned requirements without these fields (e.g., `PHP Version`, `Config Directory`, `Memory Limit`), JS crashed. Fix: defensive access (`Array.isArray` check, `||` fallbacks) + backend now always includes `manual_steps`, `system_detected`, `server_detected` for all requirements with Windows/Linux installation hints.
- **Config Directory not writable** — Bind mount `./www:/var/www/html` preserved host `ali:ali` ownership, Apache `www-data` could not write. Added `entrypoint.sh` that runs at container start (after volume mount) and fixes permissions on `config/`, `temp/`, `temp/secure_store/`, `reports/` with `chgrp www-data` + `chmod ug+rwX,o+rX` + setgid bit.
- **Update check 401 on login page** — `/api/check-update.php` required session, but `footer.php` is also included on login page (where session is empty). Made endpoint public (version info is not sensitive) with 5-second IP-based rate limit.
- **Favicon 500 error** — Missing `favicon.ico` returned 404, `.htaccess` routed to `error.php`, which crashed → 500. Fix: `.htaccess` returns 204 for missing favicon + entrypoint copies `temp/assets/images/logo.png` to `favicon.ico` on container start. Also added generic 404 rule for static assets (`.css`, `.js`, `.png`, etc.) so future missing files don't hit PHP error handler.
- **SweetAlert2 case-sensitivity** — Real folder is `SweetAlert2` (PascalCase), but `header.php` and `users.php` referenced `sweetalert2` (lowercase). Windows/macOS case-insensitive filesystems masked this; Docker Linux exposed it. Fixed to match real directory name.

### 🔧 New Infrastructure

- **`php/entrypoint.sh`** — Custom container entrypoint that runs before Apache. Handles:
  - Auto-create missing writable directories
  - Fix ownership/permissions on volume-mounted directories
  - Auto-generate favicon from logo

### 📋 How to Apply

```bash
git pull
docker-compose down
docker-compose up -d --build
```

### 🔐 Breaking Changes

None. All changes are backward-compatible.

---

## [1.3.1] - 2026-04-19

### 🐛 Bug Fixes
- **Groups listing fixed for large domains** — `getAllGroups()` now uses LDAP pagination (`LDAP_CONTROL_PAGEDRESULTS`) to bypass AD default 1000 MaxPageSize limit. Large organizations (1000+ groups) no longer get JSON parse errors.
- **GPO listing fixed + performance boost** — `api/gpo.php` now uses pagination and eliminates N+1 query problem. Previously 100 GPOs = 101 LDAP queries (timeout), now only 2 queries.
- **Report generation restored** — PHP `display_errors=0` prevents error output from corrupting JSON response. `ReportGenerator::getAllGPOs()` also got pagination.
- **`ou.php` and `computer.php` pagination** — Same MaxPageSize fix applied for consistency.
- **Docker network tools** — Added `iproute2`, `net-tools`, `iputils-ping`, `wget` to image. System Health dashboard now correctly shows MAC/IP info instead of `sh: 1: ip: not found`.
- **UPN username warning removed** — `@` character in `user@domain.com` format no longer triggers "Potentially dangerous characters" log warning.

### ✨ New Features
- 🔄 **Auto Update Check System** (inspired by [dockgate](https://github.com/Ali7Zeynalli/dockgate))
  - Footer shows green **"UPDATE"** badge when new version is available on GitHub
  - Click badge → modal with current→remote version + CHANGELOG preview
  - 24-hour localStorage cache (no aggressive polling)
  - New endpoint: `GET /api/check-update.php`
  - New file: `www/VERSION` — single source of truth for current version
  - CHANGELOG.md auto-parsed for release notes

### 🔧 Improvements
- PHP syntax validated on all modified files
- Error logging improved (`log_errors=1`, `display_errors=0` in API endpoints)

### 📋 How to Apply
```bash
git pull
docker-compose down
docker-compose up -d --build
```

---

## [1.3.0] - 2026-01-16

### 🔧 Improvements
- ⚙️ **Installer: Environment-Based Configuration**
  - Database settings now auto-loaded from `.env` file via Docker environment variables
  - Database input fields are now read-only in installer
  - Added warning message instructing users to edit `.env` file before installation
  - Improved security by centralizing credential management in `.env`
- 🔒 **New Security Lock Mechanism**
  - Replaced the mandatory "Uninstall Wizard" with a file-based lock system (`.installed`)
  - Prevents accidental re-installation without needing to archive files
  - "System Locked" screen appears if installer is accessed after installation

---

## [1.3.0] - 2026-01-15

### ✨ New Features
- 🎫 **Task Management (Helpdesk)** module added
  - Create, edit, and delete support tickets
  - Assign tickets to administrators
  - Status workflow: New → Assigned → In Progress → Resolved → Closed
  - Public comments and internal notes
  - Category management (Hardware, Software, Network, etc.)
- 👤 **Affected User Integration** - Link tickets directly to AD users
  - Search and select affected users from Active Directory
  - Display detailed user info (OU, Groups, Email)
  - Edit affected user in existing tickets
- 📝 **Full Audit Logging** - All ticket actions logged to Activity Logs
  - TICKET_CREATE - when a new ticket is created
  - TICKET_UPDATE - when ticket details are modified
  - TICKET_DELETE - when a ticket is removed
  - TICKET_ASSIGN - when ticket is assigned to someone
  - TICKET_STATUS - when status changes
  - TICKET_COMMENT - when comments/notes are added

### 🔧 Improvements
- Enhanced user search with display name and username
- Improved modal UI for ticket creation and editing
- Merged all SQL schemas into single `schema.sql` for cleaner installation

### 📚 Documentation
- Added Task Management section to README.md
- Added Tapşırıq İdarəetməsi section to README_AZ.md
- Created CHANGELOG.md for version tracking
- Added "What's New" section to both READMEs
