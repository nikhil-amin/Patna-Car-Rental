# Hosting DriveEase

How to put DriveEase on a real server: shared hosting (cPanel), or your own Ubuntu server with Apache or Nginx.

<a id="legacy-warning"></a>

> ### Read this first: deployment status
> The main booking, cancellation, return, invoice, payment, fleet-management and reporting routes have been rebuilt with prepared database helpers, server-side validation, CSRF checks for state-changing requests, role checks and fleet/customer ownership scoping. However, **live database and browser end-to-end verification is still pending**. Treat this repository as a development/staging build; do not use real customer data or payments until the full workflow and deployment checks pass. See [Project status](../README.md#project-status).

> ### How this guide was verified
> Tested on **Ubuntu 24.04** with real servers running **Apache 2.4.58 + PHP 8.3**, **Nginx 1.24 + PHP-FPM 8.3**, and **MariaDB 10.11**, covering four layouts: Apache sub-folder, Apache virtual host with `public/` as the root, a **cPanel-style layout** (reproduced on Apache), and Nginx. Each passed the bundled `tests/deploy_check.sh` (every asset loads, database connects, login works, secrets are unreachable). The permission scheme, force-HTTPS rule, and a backup-and-restore round trip were also run.
> **Not tested here:** the cPanel / Plesk web interfaces themselves, Windows/IIS, Docker, and Let's Encrypt issuance (`certbot`). Those steps follow each tool's standard procedure and are marked *(not tested here)*.

---

## Contents

1. [Choose how to host](#1-choose-how-to-host)
2. [What the server needs](#2-what-the-server-needs)
3. [Prepare before you upload](#3-prepare-before-you-upload)
4. [Option A: shared hosting with cPanel](#4-option-a-shared-hosting-with-cpanel)
5. [Option B: Ubuntu server with Apache](#5-option-b-ubuntu-server-with-apache)
6. [Option C: Ubuntu server with Nginx](#6-option-c-ubuntu-server-with-nginx)
7. [HTTPS, Cloudflare and proxies](#7-https-cloudflare-and-proxies)
8. [Verify the deployment](#8-verify-the-deployment)
9. [Backups and restore](#9-backups-and-restore)
10. [Updating to a new version](#10-updating-to-a-new-version)
11. [Hosting troubleshooting](#11-hosting-troubleshooting)

---

## 1. Choose how to host

| | **A. Shared hosting (cPanel)** | **B/C. Your own server (VPS)** |
|---|---|---|
| Cost / effort | Cheapest, least to manage | A little more, you manage the server |
| Best for | Demos, small businesses | Full control, custom domains, scaling |
| Needs | PHP 8.1+, MySQL, ability to place files above `public_html` | Ubuntu 22.04 / 24.04, SSH, sudo |
| Command line | Often (SSH/Terminal); [there is a no-SSH route](#no-ssh-route) | Yes |
| HTTPS | Free AutoSSL | Free Let's Encrypt |

Whichever you pick, the principle is the same:

> **Only the contents of `public/` may be reachable from the internet.** `.env` (database password), `includes/`, `config/` and `database/` must sit *outside* the web root.

## 2. What the server needs

- **PHP 8.1 or newer** (Ubuntu 22.04 ships 8.1, 24.04 ships 8.3). PHP 7.x and 8.0 will not work.
- PHP extensions **`mysqli`**, **`mbstring`**, **`json`**, **`session`**.
- **MySQL 5.7+ or MariaDB 10.3+** (tested on MariaDB 10.11).
- A way to put `.env`, `includes/`, `config/` and `database/` outside the public folder.
- HTTPS (every host above offers it free).
- Strongly recommended: shell access (SSH) for the migration and account tool. Without it, use the [no-SSH route](#no-ssh-route).

## 3. Prepare before you upload

1. **Get the files.** Use the project zip (it deliberately excludes `.env` and `.git`).
2. **Decide who the fleet owners are.** Self-service fleet-owner signup is on by default (as in the original). On a public site, turn it off with `ALLOW_ADMIN_SIGNUP=false` and create owners yourself using `php database/user.php create admin …`.
3. **Plan to change the demo passwords.** The sample accounts use the password `root`.
4. **Have your database details ready** (host, port, name, user, password). You will put them in `.env`.

Your production `.env` will look like this:

```ini
APP_NAME=DriveEase
APP_ENV=production
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=youraccount_carrental
DB_USER=youraccount_driveease
DB_PASS=a-long-random-password
ALLOW_ADMIN_SIGNUP=false
```

`APP_ENV=production` is essential: it hides errors from visitors and logs them instead.

---

## 4. Option A: shared hosting with cPanel

### 4.1 Pick the PHP version and extensions

cPanel → **MultiPHP Manager** (or *Select PHP Version*) → choose **PHP 8.1 or newer** for your domain. In *Select PHP Version → Extensions*, make sure **mysqli** (or *nd_mysqli*) and **mbstring** are ticked.

### 4.2 Create the database

cPanel → **MySQL® Databases**:

1. **Create New Database** (for example `carrental`). cPanel adds your account prefix, giving something like `myaccount_carrental`.
2. **Add New User** with a strong generated password. Note both the full user name and the password.
3. **Add User To Database** → choose the user and database → tick **ALL PRIVILEGES** → **Make Changes**.

### 4.3 Upload the files, in the right layout

This is the step that matters most. Your account looks like this:

```
/home/myaccount/                  ← your home folder (NOT reachable from the web)
├── public_html/                  ← the web root (reachable from the web)
├── …
```

Arrange the project like this:

```
/home/myaccount/
├── .env                          ← from .env.example, edited
├── config/                       ← from the project
├── includes/                     ← from the project
├── database/                     ← from the project
└── public_html/                  ← CONTENTS of the project's public/ folder
    ├── index.php
    ├── cars.php
    ├── assets/
    └── …
```

So: upload the project's **`config/`, `includes/`, `database/`** folders to your home folder, and upload everything **inside** `public/` into `public_html/`. The code finds `../includes` and `../.env` relative to `public_html`, so no code changes are needed.

How to upload:

- **File Manager:** upload the zip to `/home/myaccount/`, use **Extract**, then move folders as shown above. Remember to enable *Show Hidden Files* in File Manager's settings to see `.env` and `.htaccess`.
- **FTP/SFTP** (FileZilla): same layout. Make sure hidden files are shown and uploaded.

> **Cleaner alternative: a subdomain.** In cPanel → **Domains** (or *Subdomains*), create `rent.example.com` and set its **Document Root** to the project's `public` folder, for example `/home/myaccount/driveease/public`. Then upload the *whole* project to `/home/myaccount/driveease/`. This avoids moving files around and is the closest to the standard layout. Use it if your host lets you set the document root.
>
> Do **not** install into a sub-folder of `public_html` (for example `public_html/rental/`) with code above it. The relative paths will not line up.

### 4.4 Create `.env`

In File Manager open `/home/myaccount/` → **+ File** → name it `.env`, then **Edit** and paste the production values from [step 3](#3-prepare-before-you-upload), with the real database name, user and password from step 4.2.

Set its permissions to **640** (File Manager → right-click → *Change Permissions*), or **600** if your host's PHP runs as your own user (it normally does on cPanel).

### 4.5 Load the database

cPanel → **phpMyAdmin** → select your new database → **Import** → choose `database/carrental.sql` → **Go**.

Then run the migration. Two ways:

**With a terminal** (cPanel → **Terminal**, or SSH). Use the PHP 8.1+ binary; the default `php` on shared hosting is sometimes older, so check first:

```bash
cd ~
php -v                    # must be 8.1 or newer
php database/migrate.php
```

If `php` is too old, try a versioned binary such as `/usr/local/bin/ea-php83` or `/opt/cpanel/ea-php83/root/usr/bin/php`:

```bash
/opt/cpanel/ea-php83/root/usr/bin/php database/migrate.php
```

<a id="no-ssh-route"></a>**No terminal? Migrate on your own computer, then import the result.** This works on any host:

1. Set up DriveEase locally with [XAMPP](XAMPP.md) and run the migration there ([XAMPP step 6](XAMPP.md#6-run-the-migration)).
2. Change the demo passwords locally with `php database\user.php set-password …` (see [accounts](#46-accounts)).
3. In local phpMyAdmin: select `carrental` → **Export** → **Custom** → tick *Add DROP TABLE* → **Export**.
4. On the host, instead of importing `carrental.sql`, import that exported file into the host database.

### 4.6 Accounts

Change the demo passwords and create your real fleet owner. With a terminal:

```bash
cd ~
php database/user.php set-password customer diwakar
php database/user.php set-password admin nikhil
php database/user.php create admin yourowner "Your Name" you@example.com 9876543210 "Your City"
```

Without a terminal, do this on your local copy *before* exporting (see the no-SSH route above).

### 4.7 HTTPS

cPanel → **SSL/TLS Status** → run **AutoSSL** for your domain *(not tested here)*. Once the padlock works, force HTTPS by adding this to `public_html/.htaccess` (keep the existing `Options -Indexes` line):

```apache
Options -Indexes

# Force HTTPS (also correct behind Cloudflare or a load balancer)
RewriteEngine On
RewriteCond %{HTTPS} !=on
RewriteCond %{HTTP:X-Forwarded-Proto} !=https
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

DriveEase automatically marks its session cookie `Secure` once the connection is HTTPS.

### 4.8 Permissions

Folders `755`, files `644`, and `.env` `600` or `640`. File Manager's default for uploads is usually already correct.

Then go to [Verify the deployment](#8-verify-the-deployment).

---

## 5. Option B: Ubuntu server with Apache

For a fresh Ubuntu **22.04 or 24.04** server you can SSH into. Commands use `sudo`. Replace `rent.example.com` with your domain, and point the domain's **A record** at the server's IP first.

### 5.1 Install the software

```bash
sudo apt update
sudo apt install -y apache2 libapache2-mod-php php-mysql php-mbstring mariadb-server unzip
sudo a2enmod rewrite headers
php -v        # must be 8.1 or newer
```

### 5.2 Secure and prepare the database

```bash
sudo mysql_secure_installation
sudo mysql
```

In the MySQL prompt:

```sql
CREATE DATABASE carrental CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'driveease'@'localhost' IDENTIFIED BY 'a-long-random-password';
GRANT ALL PRIVILEGES ON carrental.* TO 'driveease'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 5.3 Deploy the code

```bash
sudo mkdir -p /var/www/driveease
sudo unzip driveease-phase3.zip -d /tmp/driveease-src          # adjust the zip name
sudo cp -a /tmp/driveease-src/*/. /var/www/driveease/          # copies the project contents
cd /var/www/driveease
sudo cp .env.example .env
sudo nano .env                                                  # set the production values (step 3)
```

Import the data and migrate:

```bash
sudo mysql carrental < database/carrental.sql
sudo php database/migrate.php
```

Change the demo passwords and create your owner:

```bash
sudo php database/user.php set-password customer diwakar
sudo php database/user.php set-password admin nikhil
sudo php database/user.php create admin yourowner "Your Name" you@example.com 9876543210 "Your City"
```

Lock down the files. Apache runs as `www-data` and only needs to *read* the project:

```bash
sudo chown -R root:www-data /var/www/driveease
sudo find /var/www/driveease -type d -exec chmod 750 {} \;
sudo find /var/www/driveease -type f -exec chmod 640 {} \;
sudo chmod 640 /var/www/driveease/.env
```

> Vehicle photos are mapped to externally hosted Unsplash images; the application does not require a writable local car-image upload directory.

### 5.4 Configure the virtual host

```bash
sudo nano /etc/apache2/sites-available/driveease.conf
```

```apache
<VirtualHost *:80>
    ServerName rent.example.com
    DocumentRoot /var/www/driveease/public

    <Directory /var/www/driveease/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/driveease-error.log
    CustomLog ${APACHE_LOG_DIR}/driveease-access.log combined
</VirtualHost>
```

```bash
sudo a2ensite driveease
sudo a2dissite 000-default
sudo apache2ctl configtest        # must print "Syntax OK"
sudo systemctl reload apache2
```

`DocumentRoot` is `public/`, so `.env`, `includes/` and the rest are not reachable from the web.

### 5.5 HTTPS and firewall

```bash
sudo apt install -y certbot python3-certbot-apache      # (not tested here)
sudo certbot --apache -d rent.example.com               # (not tested here)

sudo ufw allow OpenSSH
sudo ufw allow 'Apache Full'
sudo ufw enable
```

Certbot installs the certificate, adds the HTTPS site and the HTTP→HTTPS redirect, and renews automatically.

### 5.6 PHP settings for production

Edit `/etc/php/8.3/apache2/php.ini` (use your PHP version in the path):

```ini
display_errors = Off
log_errors = On
expose_php = Off
```

Then `sudo systemctl reload apache2`. DriveEase also hides errors itself when `APP_ENV=production`.

---

## 6. Option C: Ubuntu server with Nginx

Use [5.1–5.3](#5-option-b-ubuntu-server-with-apache) with these differences: install `nginx php-fpm php-mysql php-mbstring mariadb-server unzip` instead of Apache, and chown the project to `root:www-data` the same way (PHP-FPM also runs as `www-data`).

Create `/etc/nginx/sites-available/driveease` (this exact configuration was tested):

```nginx
server {
    listen 80;
    server_name rent.example.com;
    root /var/www/driveease/public;
    index index.php;

    # Never serve dotfiles (.env, .git, .htaccess) even if one ends up under public/
    location ~ /\.(?!well-known) { deny all; }

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        try_files $uri =404;                       # block path-info tricks
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;   # match your PHP version
    }

    # Cache static assets for a month
    location ~* \.(css|js|png|jpg|jpeg|gif|svg|ico|woff2?|ttf)$ {
        expires 30d;
        access_log off;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/driveease /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
sudo apt install -y certbot python3-certbot-nginx       # (not tested here)
sudo certbot --nginx -d rent.example.com                # (not tested here)
sudo ufw allow 'Nginx Full'
```

Check the socket name with `ls /run/php/` and match it in `fastcgi_pass`. Also set `display_errors = Off`, `log_errors = On` and `expose_php = Off` in `/etc/php/8.3/fpm/php.ini` and reload `php8.3-fpm`.

Nginx ignores `.htaccess`, which is fine: the config above already does everything it did.

---

## 7. HTTPS, Cloudflare and proxies

- **Always use HTTPS** on a real site. DriveEase detects HTTPS directly, **or** via the `X-Forwarded-Proto: https` header that proxies and CDNs send, and then marks session cookies `Secure`. (Tested: the cookie is not `Secure` on plain HTTP and is `Secure` when the proxy header says HTTPS.)
- **Cloudflare:** set **SSL/TLS mode to *Full (strict)***. *Flexible* mode talks to your server over plain HTTP, which causes redirect loops and weakens the setup.
- **Load balancers / reverse proxies:** make sure they pass `X-Forwarded-Proto`. Without it the app thinks it is on HTTP and will not set `Secure` cookies; `tests/deploy_check.sh` will flag this.
- **HSTS** (optional, once HTTPS is solid everywhere): add `Header always set Strict-Transport-Security "max-age=31536000"` to the Apache HTTPS virtual host, or `add_header Strict-Transport-Security "max-age=31536000" always;` in Nginx.

---

## 8. Verify the deployment

Run the bundled checker from **any computer** (your laptop is fine). It needs only `bash` and `curl` (Git Bash on Windows works):

```bash
./tests/deploy_check.sh https://rent.example.com/
```

To include a sign-in test, give it a customer account:

```bash
DE_USER=diwakar DE_PASS='your-new-password' ./tests/deploy_check.sh https://rent.example.com/
```

It checks that:

1. the home page renders, with no PHP errors showing;
2. every CSS, JS and image file loads (a failure here means the web root is wrong);
3. the database is connected;
4. **`.env`, `includes/`, `config/`, `database/`, `tests/` and `.git` are not downloadable** (a failure here is urgent);
5. directory listing is off;
6. security headers are present, the session cookie is `HttpOnly`/`SameSite`, and on HTTPS also `Secure`, with HSTS and the HTTP→HTTPS redirect reported;
7. sign-in works, a form without a CSRF token is rejected (HTTP 403), and sign-out works.

`[FAIL]` lines must be fixed. `[WARN]` lines are recommendations. A healthy HTTPS site ends with `0 failed`.

You can also check by hand. These should all fail to load (403 or 404):

```
https://rent.example.com/.env
https://rent.example.com/includes/db.php
https://rent.example.com/database/carrental.sql
```

---

## 9. Backups and restore

DriveEase's data is entirely in the database, plus any car images under `public/assets/img/cars/`. Back up both.

### Automatic daily backup (Linux server)

Store the database login in a private file so the password never appears in process lists:

```bash
sudo mkdir -p /var/backups/driveease
sudo nano /root/.driveease.cnf
```

```ini
[client]
user=driveease
password=a-long-random-password
host=127.0.0.1
```

```bash
sudo chmod 600 /root/.driveease.cnf
sudo crontab -e
```

Add this line (daily at 02:00, keeping 14 days):

```cron
0 2 * * * mysqldump --defaults-extra-file=/root/.driveease.cnf --single-transaction carrental | gzip > /var/backups/driveease/carrental-$(date +\%F).sql.gz && find /var/backups/driveease -name '*.sql.gz' -mtime +14 -delete
```

Also copy backups **off the server** (another machine or object storage). A backup on the same disk does not survive a disk failure.

### Restore

```bash
gunzip -c /var/backups/driveease/carrental-2026-10-09.sql.gz | sudo mysql carrental
```

(This exact backup-and-restore round trip was tested: counts and revenue matched exactly.) To practise safely, restore into a scratch database first (`CREATE DATABASE test_restore;` then `… | mysql test_restore`).

### cPanel

cPanel → **Backup** → download a **Home Directory** backup and a **MySQL Database** backup, or schedule backups from your host's backup tool.

---

## 10. Updating to a new version

DriveEase upgrades are designed to be safe to repeat:

1. **Back up** the database (step 9) and the files.
2. **Upload the new files** over the old ones. The zip excludes `.env`, so your configuration is preserved. If you deploy with git: `git pull`.
3. **Run the migration again.** It is idempotent: it only applies what is new and changes nothing else.

   ```bash
   cd /var/www/driveease && sudo php database/migrate.php
   ```

4. **Reload PHP** if you use PHP-FPM or OPcache (`sudo systemctl reload php8.3-fpm` or `apache2`).
5. **Run `tests/deploy_check.sh`** again.

**Rollback:** restore the files you backed up, and restore the database backup if the migration had changed it.

---

## 11. Hosting troubleshooting

| Symptom | Likely cause and fix |
|---|---|
| **500 Internal Server Error** right after upload | Check the host's error log (cPanel → *Errors*; Apache `…/driveease-error.log`). Common causes: PHP older than 8.1; or the host forbids the `Options` directive in `.htaccess`, in which case remove the `Options -Indexes` line from `public/.htaccess` |
| White page, no content | `APP_ENV=production` is hiding an error. Read the PHP error log, or set `APP_ENV=local` *briefly* to see it, then switch back |
| `deploy_check`: assets fail / page unstyled | The web root is not `public/` (or `public_html` does not hold the *contents* of `public/`). See [4.3](#43-upload-the-files-in-the-right-layout) |
| `deploy_check`: database not connected | Wrong `DB_*` values. On cPanel use the **prefixed** names (`myaccount_…`). If the DB host is not `localhost`/`127.0.0.1`, ask your host. Check `DB_PORT` |
| `Class "mysqli" not found` | Enable the `mysqli` extension (cPanel → *Select PHP Version → Extensions*, or `sudo apt install php-mysql`) |
| `Call to undefined function mb_strlen()` | Enable `mbstring` (cPanel → Extensions, or `sudo apt install php-mbstring`) |
| `deploy_check` says **EXPOSED** | `.env` or source is downloadable. Stop and fix the layout: the document root must be `public/` and `.env` must be outside it. Rotate the database password afterwards |
| "Session cookie is not Secure" on HTTPS | The proxy does not send `X-Forwarded-Proto: https`. Configure it, or terminate TLS on the app server itself |
| Login works, then you are logged out on the next page | PHP cannot save sessions: the `session.save_path` directory is missing or not writable by the web user |
| Redirect loop after enabling "force HTTPS" | Cloudflare *Flexible* mode. Use *Full (strict)*, and keep the `X-Forwarded-Proto` line in the rewrite rule |
| `Access denied` while running the migration | The database user needs `ALTER` and `CREATE`. Grant `ALL PRIVILEGES` on the database for the migration |
| `php: command not found` or the wrong version over SSH | Use the full path to the right PHP binary ([4.5](#45-load-the-database)) |
| Wrong times in bookings | The app uses `Asia/Kolkata`. Change `date_default_timezone_set` in `includes/bootstrap.php` for another zone |
| "Too many failed attempts" | The 5-attempt, 15-minute lockout. Wait, or run `DELETE FROM login_attempts;` |
| Everything worked, then the site was hacked or defaced | Restore from a clean backup, rotate the DB password, change all account passwords, inspect access/error logs, and check for unexpected file or database changes |

## Production checklist

- [ ] Document root is `public/` (or `public_html` holds only the contents of `public/`)
- [ ] `.env` is outside the web root, mode `640`/`600`, with `APP_ENV=production`
- [ ] Dedicated database user, not `root`
- [ ] Demo passwords changed; unused demo accounts removed
- [ ] `ALLOW_ADMIN_SIGNUP=false` on a public site
- [ ] HTTPS working, HTTP redirects to HTTPS
- [ ] `./tests/deploy_check.sh https://your-domain/` reports `0 failed`
- [ ] Daily database backups, copied off the server, restore tested
- [ ] Old Google Maps API key revoked (it remains in git history)
- [ ] Live MySQL and browser end-to-end tests pass; do not use real customer data until verified
