# AGENTS.md

## Cursor Cloud specific instructions

osTicket is a PHP + MySQL support-ticket web app. There is **no package manager / build step** — third-party libraries are vendored under `include/`. Runtime deps are just PHP (8.2–8.4) and MariaDB/MySQL. The update script installs PHP 8.3 + extensions and MariaDB; the notes below cover starting/running things, which the update script intentionally does not do.

### Services

Two services are needed for local development (both must be started each session — the update script does not start services):

- **MariaDB** (data store): `sudo service mariadb start`
- **PHP dev server** (serves client UI, staff SCP, installer, API): from the repo root run
  `php manage.php serve --host 0.0.0.0 --port 8000`

Then browse:
- Client / support center: `http://localhost:8000/`
- Staff control panel (SCP): `http://localhost:8000/scp/`
- API + email intake: `http://localhost:8000/api/`

### Key gotchas (non-obvious)

- **Use DB host `127.0.0.1`, not `localhost`.** With `localhost`, PHP `mysqli` connects over a unix socket at `/var/run/mysqld/mysqld.sock` and fails with `mysqli_sql_exception: No such file or directory`. `127.0.0.1` forces TCP and works. The DB user is granted for `127.0.0.1` and `%`.
- **`manage.php` (and any bootstrap-loading CLI/page) redirects to the installer and exits if osTicket is not yet installed.** `php manage.php serve` therefore only works *after* installation is complete. Before install, the site auto-redirects to `/setup/install.php`.
- **Runtime config lives in `include/ost-config.php`** (gitignored, created by the installer from `include/ost-sampleconfig.php`). It must be writable during install (`chmod 666`). If this file is missing or still has `OSTINSTALLED` set to `FALSE`, the app is not installed — see "Fresh install" below.
- The installer auto-creates a warm-up ticket, so a fresh install already has one ticket.

### Local install details (already configured in the base snapshot)

- Database `osticket`, DB user `osticket` / password `osticket` (host `127.0.0.1`), table prefix `ost_`.
- Admin (SCP) login: username `ostadmin`, password `OsTicketAdmin#2026`.
- System email `support@example.com`, admin email `admin@example.com`.

### Fresh install (only if `include/ost-config.php` is missing or DB `osticket` is empty)

```bash
sudo service mariadb start
sudo mariadb -e "CREATE DATABASE IF NOT EXISTS osticket CHARACTER SET utf8 COLLATE utf8_general_ci;
CREATE USER IF NOT EXISTS 'osticket'@'127.0.0.1' IDENTIFIED BY 'osticket';
CREATE USER IF NOT EXISTS 'osticket'@'%' IDENTIFIED BY 'osticket';
GRANT ALL PRIVILEGES ON *.* TO 'osticket'@'127.0.0.1';
GRANT ALL PRIVILEGES ON *.* TO 'osticket'@'%'; FLUSH PRIVILEGES;"
cp include/ost-sampleconfig.php include/ost-config.php && chmod 666 include/ost-config.php
# start the dev server on a port, then drive the web installer (it lives at /setup/install.php):
php -S 0.0.0.0:8000 -t . &   # manage.php serve won't run until installed
BASE=http://localhost:8000/setup/install.php
curl -s -c /tmp/cj -b /tmp/cj -X POST "$BASE" -d "s=prereq" -o /dev/null
curl -s -c /tmp/cj -b /tmp/cj -X POST "$BASE" -d "s=config" -o /dev/null
curl -s -c /tmp/cj -b /tmp/cj -X POST "$BASE" \
  --data-urlencode "s=install" --data-urlencode "name=Cursor Helpdesk" \
  --data-urlencode "email=support@example.com" --data-urlencode "lang_id=en_US" \
  --data-urlencode "fname=Cursor" --data-urlencode "lname=Admin" \
  --data-urlencode "admin_email=admin@example.com" --data-urlencode "username=ostadmin" \
  --data-urlencode "passwd=OsTicketAdmin#2026" --data-urlencode "passwd2=OsTicketAdmin#2026" \
  --data-urlencode "prefix=ost_" --data-urlencode "dbhost=127.0.0.1" \
  --data-urlencode "dbname=osticket" --data-urlencode "dbuser=osticket" \
  --data-urlencode "dbpass=osticket" --data-urlencode "timezone=UTC"
```

### Lint & tests

- Lint a file: `php -l <file.php>`.
- Test suite (custom runner, run from repo root so `bootstrap.php` is on the include path):
  `php -d include_path=".:/workspace:/usr/share/php" setup/test/run-tests.php`
  It prints `N FAIL(s)` only on failure; a clean run lists each suite ending in `ok`. Note: `jsl` (JavaScript Lint) is not installed, so JS lint checks are skipped (they still report `ok`).

### Optional (not needed for basic web ticketing)

Background cron (`php manage.php cron`), inbound IMAP mail (needs a PHP `imap` extension, which is not installed), outbound SMTP, and Memcache are all optional. Web-form ticket creation and the staff panel work without them.
