# AA Driving School Management System (Mombasa)

PHP + MySQL + HTML/CSS/JS web system, built to the design in the project proposal (three-tier architecture, XAMPP).

## Setup (XAMPP on Windows)

### Requirements
- XAMPP with Apache, PHP, and MySQL/MariaDB.
- PHP 7.3 or newer, with `PDO` and `pdo_mysql` enabled (included with standard XAMPP installs).
- A browser. The instructions below use XAMPP's default Apache and MySQL ports.

### Install and configure
1. Copy or clone the project folder to `C:\xampp\htdocs\aa_driving_school\`. Confirm that
   `index.php` and `database.sql` are directly inside that folder.
2. Open the XAMPP Control Panel and start **Apache** and **MySQL**. If another MySQL service
   is installed, make sure phpMyAdmin and this app connect to the same server; two servers
   can have different databases and passwords even on the same computer.
3. Open <http://localhost/phpmyadmin>, sign in with the MySQL account for that XAMPP server,
   select **Import**, choose the project's `database.sql`, and click **Import** (or **Go**,
   depending on the phpMyAdmin version). The script creates the `aa_driving_school` database
   and demo records. **It drops and recreates the project's tables each time it is imported**;
   back up any data you need before importing again.
4. Configure the app's database login before opening it. In PowerShell:

   ```powershell
   cd C:\xampp\htdocs\aa_driving_school
   Copy-Item config.local.php.example config.local.php
   ```

   Edit `config.local.php` and set `host`, `user`, and `pass` to credentials that can access
   the imported database. The example uses `localhost`, database `aa_driving_school`, and
   XAMPP's common `root` account with an empty password. Change these if your MySQL account
   uses a password or a different host.

   For better security, use a dedicated app account instead of `root`. After importing the
   database, run the following in phpMyAdmin's **SQL** tab while signed in with an account
   allowed to create users. Replace the example password with a strong, unique password:

   ```sql
   CREATE USER 'aa_driving_app'@'localhost' IDENTIFIED BY 'replace-with-a-strong-password';
   GRANT SELECT, INSERT, UPDATE, DELETE ON aa_driving_school.* TO 'aa_driving_app'@'localhost';
   ```

   Then set `user` to `aa_driving_app` and `pass` to the password you chose in
   `config.local.php`. For the default setup, use `localhost` as the host in both places;
   the MySQL account's host entry must allow connections to the app's configured endpoint.
   Do not commit `config.local.php` or share its password; it is ignored by Git.
   Alternatively, set `AA_DRIVING_DB_PASS` in the environment used to launch PHP; a value
   in this variable overrides only the configured password.
5. Open <http://localhost/aa_driving_school/> and sign in with a demo account below.

### Troubleshooting
- **404 Not Found:** Check that the project folder is under `C:\xampp\htdocs\` and that
  `index.php` is inside `aa_driving_school`.
- **Database connection failed:** Confirm MySQL is running, `aa_driving_school` appears in
  phpMyAdmin, and `config.local.php` has the correct host, username, and password. The app
  and phpMyAdmin must point to the same MySQL server. If the login works in phpMyAdmin but
  not in the app, check that the database account's host matches the app's configured host.
- **phpMyAdmin cannot connect:** Sign in with the credentials for the XAMPP MySQL server;
  phpMyAdmin's login and the app's `config.local.php` are separate settings.
- **PDO MySQL driver error:** Enable `pdo_mysql` for the PHP installation used by Apache in
  `C:\xampp\php\php.ini`, then restart Apache.

## Demo logins
| Role | Username | Password |
|---|---|---|
| Administrator | admin | admin123 |
| Receptionist | reception | pass1234 |
| Instructor | instructor1 | pass1234 |
| Student | student1 | pass1234 |

**Change the demo passwords before real use** (Users page). If a database password was ever
committed or shared, rotate it and keep the replacement in `config.local.php`.

## Modules
- **Login / logout** with hashed passwords, CSRF protection and role-based access
- **Students** – register, search, edit, delete, optional student login, fee balance
- **Instructors** – manage records and optional instructor login
- **Vehicles** – fleet list, availability / maintenance status
- **Lessons** – schedule with automatic conflict detection (instructor, student, vehicle), mark completed/cancelled
- **Payments** – Cash / M-Pesa / Bank, printable receipts, balances
- **Test results** – theory and practical scores, automatic pass/fail (pass mark in `config.php`)
- **Reports** – payments, outstanding fees, students, lessons, test results, progress; print and CSV export
- **Users** – administrator creates administrator/receptionist accounts and resets passwords;
  student and instructor accounts are created with their profiles so they are linked correctly

## Who can do what
| Page | Admin | Receptionist | Instructor | Student |
|---|---|---|---|---|
| Students / Instructors / Vehicles | full | add/edit | – | – |
| Lessons | full | schedule/edit | own lessons, mark done | own lessons (view) |
| Payments | full | record | – | own (view) |
| Test results | full | – | record | own (view) |
| Reports | yes | yes | – | – |
| Users | yes | – | – | – |

## Files
`config.php` (DB + helpers) · `includes/` (layout) · one PHP page per module · `assets/style.css` · `database.sql`
