# AA Driving School Management System (Mombasa)

PHP + MySQL + HTML/CSS/JS web system, built to the design in the project proposal (three-tier architecture, XAMPP).

## Setup (XAMPP, Windows)
1. Copy the `aa_driving_school` folder to `C:\xampp\htdocs\`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open http://localhost/phpmyadmin → **Import** → choose `database.sql` → Go.
   (This creates the `aa_driving_school` database with demo data.)
4. Open http://localhost/aa_driving_school/
5. If your MySQL has a password, edit `DB_PASS` in `config.php`.

## Demo logins
| Role | Username | Password |
|---|---|---|
| Administrator | admin | admin123 |
| Receptionist | reception | pass1234 |
| Instructor | instructor1 | pass1234 |
| Student | student1 | pass1234 |

**Change these passwords before real use** (Users page).

## Modules
- **Login / logout** with hashed passwords, CSRF protection and role-based access
- **Students** – register, search, edit, delete, optional student login, fee balance
- **Instructors** – manage records and optional instructor login
- **Vehicles** – fleet list, availability / maintenance status
- **Lessons** – schedule with automatic conflict detection (instructor, student, vehicle), mark completed/cancelled
- **Payments** – Cash / M-Pesa / Bank, printable receipts, balances
- **Test results** – theory and practical scores, automatic pass/fail (pass mark in `config.php`)
- **Reports** – payments, outstanding fees, students, lessons, test results, progress; print and CSV export
- **Users** – administrator creates accounts, resets passwords

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
