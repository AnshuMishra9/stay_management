# Stay Management

Stay Management is a CodeIgniter 3 application for managing plants, properties,
rooms, bookings, check-ins, check-outs, users, and property-level access.

The repository currently includes CodeIgniter 3.1.13 and all browser assets
required for normal operation.

## Local requirements

- Windows with XAMPP
- Apache with `mod_rewrite` enabled and `AllowOverride All` permitted
- PHP 8.2 with `mysqli`, `fileinfo`, `session`, and `json` enabled
- MariaDB 10.4 or a compatible MySQL server
- Project directory: `C:\xampp\htdocs\stay_management`

The CodeIgniter framework is included in the repository, so Composer installation
is not required to start the application.

## Quick start

### 1. Put the project in the XAMPP document root

The expected location is:

```text
C:\xampp\htdocs\stay_management
```

Start **Apache** and **MySQL** from the XAMPP Control Panel.

The application URL and rewrite base currently use the `stay_management` folder
name. If the folder or URL changes, update both:

- `application/config/config.php` (`base_url`)
- `.htaccess` (`RewriteBase`)

### 2. Create the local environment file

From PowerShell in the project directory, run:

```powershell
Copy-Item -LiteralPath '.env.example' -Destination '.env'
```

Open `.env` and verify the local database settings. The default XAMPP values are:

```dotenv
DB_HOSTNAME=127.0.0.1
DB_DATABASE=stay_management
DB_USERNAME=root
DB_PASSWORD=
DB_DBDRIVER=mysqli
```

`127.0.0.1` is recommended instead of `localhost` on Windows/XAMPP to avoid a
possible IPv6 name-resolution delay.

Generate an application encryption key:

```powershell
& 'C:\xampp\php\php.exe' -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Paste the generated value after `ENCRYPTION_KEY=` in `.env`. Never commit or
share the real `.env` file.

### 3. Recreate the database structure

The canonical zero-data installer is
[`db/stay_management.sql`](db/stay_management.sql).

> **Destructive operation:** this installer runs `DROP DATABASE IF EXISTS
> stay_management`, creates the database again, and removes all existing data.
> Back up any database that must be retained before running it.

With the default XAMPP root account (no password), run:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' --host=127.0.0.1 --port=3306 --protocol=tcp --user=root --execute "SOURCE C:/xampp/htdocs/stay_management/db/stay_management.sql"
```

If the database user has a password, add `--password` to the command and enter
the password at the prompt. The same SQL file can alternatively be imported from
phpMyAdmin.

### 4. Add the minimum bootstrap records

The installer intentionally contains no rows. Before the first login, open the
`stay_management` database in phpMyAdmin, select the **SQL** tab, replace the
bootstrap mobile placeholder, and run:

```sql
USE stay_management;

INSERT INTO status_details
    (sd_id, status_code, sd_name, display_order, sd_status)
VALUES
    (1, 'room_booked', 'Room booked', 1, 1),
    (2, 'checked_in',  'Checked in',  2, 1),
    (3, 'checked_out', 'Checked out', 3, 1),
    (4, 'cancelled',   'Cancelled',   4, 1),
    (5, 'no_show',     'No show',     5, 1);

-- Replace NULL with a quoted, controlled 10-15 digit mobile number.
-- Leaving it as NULL makes the INSERT fail safely instead of creating a
-- predictable shared account.
SET @bootstrap_mobile = NULL;

INSERT INTO users
    (name, mobile_no, role, fk_plant, status, added_by)
VALUES
    ('Bootstrap Super Admin', @bootstrap_mobile, 'super_admin', NULL, 1, NULL);

SELECT user_id, name, mobile_no, role, status
FROM users
WHERE role = 'super_admin';
```

Replace `NULL` before executing the SQL. The authorized number must contain
10-15 digits. No password is required because this application currently uses
OTP authentication.

Reference lists such as GST rates, booking channels, states, and amenities are
optional at startup and can be populated later with company-approved values.

### 5. Open and initialize the application

Open:

```text
http://localhost/stay_management/
```

Use the bootstrap super-admin mobile number, request an OTP, and enter the
six-digit code shown by the current local login flow. After login:

1. Create a plant and its admin from **Plants**.
2. Create a property for that plant.
3. Configure room categories, rooms, users, and optional reference data.
4. Start creating bookings and using the check-in/check-out workflow.

## Verify the installation

Run the PHP test suites from the project directory:

```powershell
& 'C:\xampp\php\php.exe' 'run_tests.php'
```

The expected summary is:

```text
Passed: 4 / 4 suites
```

MySQL must be running. The database-backed test suites create and remove random
disposable databases and currently expect the local XAMPP `root` user with a
blank password; they do not modify the main `stay_management` database.

## Common problems

- **Routes return 404:** confirm Apache `mod_rewrite` is enabled,
  `AllowOverride All` is allowed, and the folder name matches `RewriteBase`.
- **Database connection fails:** start MySQL, check `.env`, and confirm the schema
  installer completed successfully. The current database config assumes the
  server's default port; `DB_PORT` is present in `.env.example` but is not yet
  consumed by `application/config/database.php`.
- **Mobile number is not authorized:** confirm the user exists in `users`, has
  `status = 1`, and the entered number is an exact match.
- **Booking workflow statuses are missing:** run the `status_details` bootstrap
  insert shown above.
- **Uploads fail:** ensure Apache/PHP can write to `secure_uploads`. This folder
  is private and should never be served directly.
- **Sessions or logs cannot be written:** ensure PHP's session directory
  (`C:\xampp\tmp` in the standard XAMPP setup), `application/logs`, and
  `application/cache` are writable by the Apache process.
- **`/db`, `/tests`, `/application`, or `/secure_uploads` returns 403:** this is
  expected; `.htaccess` deliberately blocks private project paths.

## Security and deployment notes

- Keep `.env`, runtime logs, sessions, uploaded identity documents, database
  exports, and real customer data out of shared archives and version control.
- The current login controller returns the generated OTP to the browser and the
  login page displays it as a compatibility fallback. This is suitable only for
  isolated local development. Before staging or production, connect the SMS
  gateway and stop returning or displaying the OTP. Setting
  `DEV_EXPOSE_OTP=false` alone does not suppress the current controller response.
- Set `CI_ENVIRONMENT=production`, use a dedicated database account, protect
  secrets outside the repository, and serve the application over HTTPS.
- The database installer is intended for a clean setup, not an in-place
  production upgrade.
