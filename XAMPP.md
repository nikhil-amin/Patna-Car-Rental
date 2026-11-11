# Running DriveEase on XAMPP

A step-by-step guide for Windows, macOS and Linux. About 15 minutes.

> **How this guide was verified.** The server-side layout used here (project in a sub-folder of the Apache web root, `.htaccess` rewrite, `mod_rewrite`) was tested on a real Apache 2.4 + PHP 8.3 + MariaDB 10.11 server: every page, stylesheet, script and image loads, login works, and `.env`, `includes/`, `database/` and `tests/` return **403**. I could not run the Windows or macOS XAMPP installers themselves, so XAMPP-specific menu names and default settings below come from XAMPP's documented behaviour. If something differs on your version, see [Troubleshooting](#troubleshooting).

---

## Contents

1. [Before you start](#1-before-you-start)
2. [Install and start XAMPP](#2-install-and-start-xampp)
3. [Copy the project](#3-copy-the-project)
4. [Create the database and import the data](#4-create-the-database-and-import-the-data)
5. [Create the `.env` file](#5-create-the-env-file)
6. [Run the migration](#6-run-the-migration)
7. [Open the site and check it is safe](#7-open-the-site-and-check-it-is-safe)
8. [Log in and change the demo passwords](#8-log-in-and-change-the-demo-passwords)
9. [Optional: a clean address like `http://driveease.local`](#9-optional-a-clean-address-like-httpdriveeaselocal)
10. [Upgrading an existing XAMPP install of the original project](#10-upgrading-an-existing-xampp-install-of-the-original-project)
11. [Testing from your phone](#11-testing-from-your-phone)
12. [Running the automated tests](#12-running-the-automated-tests)
13. [Troubleshooting](#troubleshooting)

---

## 1. Before you start

You need **XAMPP with PHP 8.1 or newer.** DriveEase will not run on PHP 8.0 or older, and some XAMPP downloads still bundle an older PHP. Pick the installer whose PHP version is 8.1 or higher on the XAMPP download page.

Where things live:

| | Windows | macOS | Linux |
|---|---|---|---|
| XAMPP folder | `C:\xampp` | `/Applications/XAMPP/xamppfiles` | `/opt/lampp` |
| Web root (`htdocs`) | `C:\xampp\htdocs` | `/Applications/XAMPP/xamppfiles/htdocs` | `/opt/lampp/htdocs` |
| PHP command | `C:\xampp\php\php.exe` | `/Applications/XAMPP/xamppfiles/bin/php` | `/opt/lampp/bin/php` |
| Apache config | `C:\xampp\apache\conf\httpd.conf` | `…/xamppfiles/etc/httpd.conf` | `/opt/lampp/etc/httpd.conf` |

The rest of this guide uses the Windows paths. Substitute your own.

## 2. Install and start XAMPP

1. Install XAMPP. The default components (Apache, MySQL, PHP, phpMyAdmin) are all you need.
2. Open the **XAMPP Control Panel** and click **Start** next to **Apache** and **MySQL**. Both should turn green.
   - macOS / Linux: use the XAMPP Manager app, or `sudo /opt/lampp/lampp start`.
3. Confirm your PHP version: open the **Shell** button in the control panel and run:

   ```
   php -v
   ```

   It must say `PHP 8.1` or higher. Also check the two extensions DriveEase needs:

   ```
   php -m
   ```

   The list must include **`mysqli`** and **`mbstring`**. XAMPP enables both by default.

If Apache will not start (red text about port 80 or 443), jump to [Port conflicts](#port-80-or-3306-is-already-in-use).

## 3. Copy the project

Copy the whole project folder into the web root and name it `driveease`:

```
C:\xampp\htdocs\driveease\
        ├── public\
        ├── includes\
        ├── config\
        ├── database\
        ├── .env.example
        └── …
```

Keep the folder structure exactly as it is. Do **not** copy only the `public` folder.

## 4. Create the database and import the data

1. Open **<http://localhost/phpmyadmin>**.
2. Click **New** in the left column. Name the database **`carrental`**, choose collation **`utf8mb4_unicode_ci`**, and click **Create**.
3. With `carrental` selected, open the **Import** tab, click **Choose file**, select `C:\xampp\htdocs\driveease\database\carrental.sql`, and click **Import** at the bottom.
4. You should see a green "Import has been successfully finished" and 7 tables: `cars`, `clientcars`, `clients`, `customers`, `driver`, `feedback`, `rentedcars`.

> If the import stops with "file too large", open `C:\xampp\php\php.ini`, raise `upload_max_filesize` and `post_max_size` (for example to `20M`), save, and restart Apache. The dump itself is tiny, so this is rare.

## 5. Create the `.env` file

The `.env` file holds your database login. Create it by copying the template.

**Command line (easiest).** In the XAMPP **Shell**:

```
cd C:\xampp\htdocs\driveease
copy .env.example .env
```

**Or in Windows Explorer:** copy `.env.example`, paste it, and rename the copy to `.env`. If Windows refuses a name that starts with a dot, use the command above, or open Notepad, **File → Save As**, set *Save as type* to **All files**, and type `.env` as the file name (so it does not become `.env.txt`).

Open `.env` in a text editor. XAMPP's default database login is user `root` with an **empty** password, so for a local machine this works as it is:

```ini
APP_NAME=DriveEase
APP_ENV=local
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=carrental
DB_USER=root
DB_PASS=
ALLOW_ADMIN_SIGNUP=true
```

Set `APP_ENV=local` while you develop. It shows detailed errors, which makes problems much easier to diagnose. **Never use `local` on a public server.**

> **Optional but recommended: a dedicated database user.** `root` with no password is acceptable only because XAMPP listens on your own computer. If you want to practise the safe setup, open phpMyAdmin → **SQL** and run:
>
> ```sql
> CREATE USER 'driveease'@'localhost' IDENTIFIED BY 'choose-a-password';
> GRANT ALL PRIVILEGES ON carrental.* TO 'driveease'@'localhost';
> FLUSH PRIVILEGES;
> ```
>
> Then set `DB_USER=driveease` and `DB_PASS=choose-a-password` in `.env`.

**MySQL on a different port?** If you already have another MySQL installed, XAMPP's MySQL is often moved to port **3307**. Check the port shown next to MySQL in the control panel and set `DB_PORT` to match.

## 6. Run the migration

The migration upgrades the original database in place (it adds the new columns and tables, and replaces plaintext passwords with secure hashes). It keeps all your data and is safe to run more than once.

In the XAMPP **Shell**:

```
cd C:\xampp\htdocs\driveease
php database\migrate.php
```

You should see five numbered steps ending in `Done.`:

```
DriveEase migration
[1] Accounts
  + customers.created_at
  …
  password hashes upgraded: 4
…
[5] New tables
  ledger rows created: 11
Done.
```

Run it a second time. It should print `Done.` with no `+` lines and `password hashes upgraded: 0`, which confirms nothing is applied twice.

> **"php is not recognized"?** Use the full path: `C:\xampp\php\php.exe database\migrate.php`, or open the Shell from the XAMPP Control Panel, which sets this up for you.
>
> **Running it in the browser does nothing** by design. It only runs from the command line.

## 7. Open the site and check it is safe

Open **<http://localhost/driveease/>**. You should see the DriveEase homepage with car photos, and the **Cars** page should list 12 cars.

Notice the address changes to `http://localhost/driveease/public/…` as you browse. That is expected with this setup: a rewrite rule in the project's `.htaccess` sends requests into the `public/` folder. (For a clean address, see [step 9](#9-optional-a-clean-address-like-httpdriveeaselocal).)

### The safety check (do not skip)

This setup relies on Apache honouring the project's `.htaccess` file. Confirm it by visiting each of these addresses. **Every one must show "Forbidden" (403), never a page of text or a download:**

- <http://localhost/driveease/.env>
- <http://localhost/driveease/includes/db.php>
- <http://localhost/driveease/database/carrental.sql>
- <http://localhost/driveease/config/config.php>

If you can read any of them, your database password is exposed. Fix it before continuing:

1. Open `C:\xampp\apache\conf\httpd.conf`.
2. Find the `<Directory "C:/xampp/htdocs">` block and make sure it says `AllowOverride All`.
3. Make sure the line `LoadModule rewrite_module modules/mod_rewrite.so` has no `#` in front of it.
4. Save, then **Stop and Start Apache** in the control panel and re-check.

If you cannot get this working, use the virtual-host method in step 9 instead. It keeps `.env` outside the web root entirely, so it cannot leak whatever `.htaccess` does.

You can also run the automated checker, which performs all these tests for you (needs Git Bash, see [step 12](#12-running-the-automated-tests)):

```
./tests/deploy_check.sh http://localhost/driveease/
```

## 8. Log in and change the demo passwords

Go to **Log in** (top right). The sample data includes:

| Role | Username | Password |
|---|---|---|
| Customer | `diwakar` | `root` |
| Customer | `rakshith` | `root` |
| Fleet owner | `nikhil` | `root` |
| Fleet owner | `roshan` | `root` |

Use the **Customer** or **Fleet owner** tab to match the account. Fleet owners are the admins.

These passwords are public knowledge. Change them, even locally, so you build the habit:

```
php database\user.php set-password customer diwakar
php database\user.php set-password admin nikhil
```

You will be asked for a new password (at least 8 characters, with a letter and a number). On Windows the typing is visible; this is a limitation of the Windows console.

Other useful commands:

```
php database\user.php list
php database\user.php create admin myowner "My Name" me@example.com 9876543210 "My City"
```

## 9. Optional: a clean address like `http://driveease.local`

A virtual host points Apache directly at the `public/` folder. The address stays clean (`/cars.php`, not `/driveease/public/cars.php`) and the project's private files are *outside* the web root, which is the safest arrangement and the same as a real server.

**a. Edit the virtual-hosts file** `C:\xampp\apache\conf\extra\httpd-vhosts.conf` and add at the bottom:

```apache
# Keep localhost working: the first virtual host becomes the default
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot "C:/xampp/htdocs"
</VirtualHost>

<VirtualHost *:80>
    ServerName driveease.local
    DocumentRoot "C:/xampp/htdocs/driveease/public"
    <Directory "C:/xampp/htdocs/driveease/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Use forward slashes in the paths, even on Windows.

**b. Make sure the file is loaded.** In `C:\xampp\apache\conf\httpd.conf` the line `Include conf/extra/httpd-vhosts.conf` must not start with `#`.

**c. Map the name to your computer.** Open **Notepad as Administrator** (right-click → *Run as administrator*), open `C:\Windows\System32\drivers\etc\hosts`, and add a line at the bottom:

```
127.0.0.1   driveease.local
```

On macOS/Linux edit `/etc/hosts` with `sudo`.

**d. Restart Apache** from the control panel, then open **<http://driveease.local/>**.

## 10. Upgrading an existing XAMPP install of the original project

If you already run the original *Patna-Car-Rental* on XAMPP, your data is kept. Do these in order:

1. **Back up the database.** phpMyAdmin → select `carrental` → **Export** → **Quick** → **Export**. Keep that `.sql` file safe. Also copy your whole `htdocs\<old folder>` somewhere as a backup.
2. **Check any images you uploaded.** Car photos live in `assets\img\cars\`. In the new layout they belong in `public\assets\img\cars\`. Copy any images that are not already in the new project.
3. **Install the new files** as in [step 3](#3-copy-the-project) (a new folder is cleaner than overwriting the old one).
4. **Create `.env`** ([step 5](#5-create-the-env-file)). Use the same database name your old `connection.php` used (normally `carrental`, user `root`, empty password).
5. **Run the migration** ([step 6](#6-run-the-migration)). It hashes the existing plaintext passwords, so **everyone keeps the password they already had.**
6. **Check your data:** log in with an existing account and open the **Cars** page. If your cars appear with their photos, you are done.

If anything goes wrong, drop the `carrental` database, re-import your backup from step 1, and you are back where you started.

## 11. Testing from your phone

DriveEase is responsive, so it is worth looking at on a real phone.

1. Connect the phone to the **same Wi-Fi** as your PC.
2. Find your PC's address: run `ipconfig` (Windows) or `ifconfig` / `ip a` (macOS/Linux) and look for something like `192.168.1.25`.
3. On the phone, open `http://192.168.1.25/driveease/`.
4. If it does not load, Windows Firewall may be blocking Apache: allow *Apache HTTP Server* on **Private** networks when prompted.

Only do this on a network you trust: XAMPP's demo accounts and `root` database login are not meant to be exposed. Stop Apache when you are finished.

## 12. Running the automated tests

| Test | How |
|---|---|
| Security/unit tests (30) | `php tests\foundation_test.php` |
| Full HTTP tests (64) | `./tests/phase3_http.sh` (needs `bash`, `curl` and the `mysql` command; use **Git Bash** or WSL on Windows) |
| Deployment check | `./tests/deploy_check.sh http://localhost/driveease/` (needs `bash` and `curl` only) |

**Run the first two only on a development database.** They create and remove temporary rows and rewrite a demo account's password hash.

---

## Troubleshooting

### Port 80 or 3306 is already in use

Apache or MySQL will not start, or the control panel mentions a port. Common culprits on Windows: Skype, IIS / World Wide Web Publishing Service, another MySQL.

**Apache:** XAMPP Control Panel → **Config** (Apache) → `httpd.conf`. Change `Listen 80` to `Listen 8080` and `ServerName localhost:80` to `ServerName localhost:8080`. Restart Apache and browse to `http://localhost:8080/driveease/`. (If you also see a 443 error, change `Listen 443` in `extra\httpd-ssl.conf` to `4443`.)

**MySQL:** XAMPP → **Config** → `my.ini`: change both `port=3306` entries to `3307`, restart MySQL, and set `DB_PORT=3307` in `.env`.

### Symptoms and fixes

| Symptom | Likely cause and fix |
|---|---|
| Page is unstyled, no icons or images | The web root is not `public/`. With the sub-folder method the page address must be `/driveease/…` and the rewrite must be working (see the [safety check](#the-safety-check-do-not-skip)) |
| `Not Found` for `/driveease/` | The folder is not directly inside `htdocs`, or it has a different name |
| `Forbidden` on the whole site | The folder permissions or `Require all granted` are missing; on macOS/Linux run `chmod -R a+rX` on the project |
| `Internal Server Error` immediately | Apache cannot parse `.htaccess`: `mod_rewrite` is disabled. Enable it in `httpd.conf` and restart |
| `Connection refused` or `Access denied for user` | MySQL is not started, or `DB_USER` / `DB_PASS` / `DB_PORT` in `.env` is wrong. Set `APP_ENV=local` to see the exact message |
| `Unknown database 'carrental'` | You skipped step 4, or named the database differently |
| `Table … doesn't exist` | The SQL import did not finish ([step 4](#4-create-the-database-and-import-the-data)) |
| Login says "Invalid username or password" for the demo accounts | The migration has not been run yet ([step 6](#6-run-the-migration)) |
| `Unknown column 'category'` / `'booking_status'` | The migration has not been run, or ran against a different database than `.env` points to |
| `Too many failed attempts` | The 5-attempt lockout. Wait 15 minutes, or in phpMyAdmin run `DELETE FROM login_attempts;` |
| "Your session expired or the request was invalid" | The form was open too long, or cookies are blocked. Reload the page and try again |
| `Call to undefined function mb_strlen()` | Enable `extension=mbstring` in `C:\xampp\php\php.ini`, restart Apache |
| `Class "mysqli" not found` | Enable `extension=mysqli` in `php.ini`, restart Apache |
| `Too many arguments to function mysqli_stmt::execute()` | Your PHP is older than 8.1. Install a newer XAMPP |
| `php` not recognised in a normal command prompt | Use the XAMPP **Shell** button, or the full path `C:\xampp\php\php.exe` |
| `.env` saved as `.env.txt` | Windows hid the extension. Rename it via the command line: `ren .env.txt .env` |
| Changes to `.env` seem ignored | Real environment variables override `.env`; also restart Apache after editing |
| Still stuck | Set `APP_ENV=local`, retry, and check `C:\xampp\apache\logs\error.log` |

When everything works, change `APP_ENV` back to `production` before you ever put the site on a real server. See the [hosting guide](HOSTING.md) for that.
