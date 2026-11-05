# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

**MEJR Timesheet**: internal time-tracking app for MEJR Consulting. Consultants log hours against projects they are assigned to; administrators manage users, clients, projects and assignments, and review/export hours. UI is bilingual (Spanish default, English).

- Plain PHP 8 (no framework, no router, no templating engine), one file per screen at the repo root.
- MySQL/MariaDB via PDO (code checks MySQL error `1062` for duplicate keys).
- Bootstrap 5.3.8 CSS from jsDelivr (with SRI) + `assets/css/mejr-marca.css` (brand palette) + `assets/css/styles.css` (own overrides), both linked from every page.
- Only Composer dependency: `phpoffice/phpspreadsheet` (Excel export).
- Developed on **MAMP (macOS)** at `/Applications/MAMP/htdocs/NAMP/`, served at `https://localhost:8890/NAMP/`.

## Running locally

There is no build step, no test suite and no linter configured.

1. `composer install` (creates `vendor/`, gitignored).
2. Create `config/conexion.php` (gitignored, **not in the repo**). It must define a `$pdo` PDO instance for MySQL with `PDO::ERRMODE_EXCEPTION` and an associative default fetch mode (pages use `$row['col']` and catch `PDOException`).
3. The base schema is **not in the repo** (`*.sql` is gitignored; migrations are whitelisted one by one in `.gitignore`). Tables and columns inferred from queries:
   - `usuarios(id, nombres, apellidos, especialidad, usuario UNIQUE, correo UNIQUE, password_hash, rol ENUM('administrador','consultor'), activo)`
   - `clientes(id, nombre UNIQUE, activo)`
   - `proyectos(id, cliente_id, nombre, activo)`, unique on `(cliente_id, nombre)`
   - `proyecto_consultor(proyecto_id, consultor_id, activo)`, unique on the pair
   - `registros_horas(id, consultor_id, proyecto_id, fecha DATE, horas DECIMAL(_,2), descripcion)`
   - `intentos_login(id, ip, identificador, creado_en)`: created by `sql/003_intentos_login.sql`
4. Serve the folder with Apache 2.4 + PHP + `mod_rewrite`.

Static files `index.html`, `consultor.html` (+ `js/app.js`, `js/consultor.js`) and `vista-previa.html` are **design prototypes with fake data**, not part of the running app; `.htaccess` refuses to serve them. `LEEME.txt` documents the original brand-styling rollout.

## Architecture

Every PHP page follows the same top-to-bottom script, copy-pasted rather than shared:

1. `session_name('mejr_timesheet')` + `session_start([...strict, gc_maxlifetime 1800, httponly, samesite=Lax, secure if HTTPS])`, then `Cache-Control: no-store`.
2. `require_once config/idioma.php` → resolves `$idioma` (`?idioma=` → `mejr_idioma` cookie → session → `Accept-Language`), defines the base `$traducciones` map and `mejrTraducir()`. Each page then appends its own strings with `$traducciones += [...]`. **Spanish strings are the translation keys**: change a Spanish UI string and you must change its key in that page's array too.
3. Idle-timeout check (30 min via `$_SESSION['ultima_actividad']`) → redirect to `login.php`.
4. `require config/conexion.php` then `config/validar-sesion.php`, which reads the user once per request and kills the session if the account is inactive, the role is invalid, the 30-min idle limit passed, or the password changed (`$_SESSION['credencial_version']` = `sha256(password_hash)`). On success it leaves the row in `$cuentaSesion` (`id, nombres, apellidos, rol, activo, password_hash`).
5. Page does `$cuenta = $cuentaSesion;` and enforces role (`administrador` or `consultor`) with a 403.
6. Defines local `escapar()` and a page-specific `texto<Page>()` (= escape + translate).
7. POST handling, then queries, then the HTML template inline.

Pages by role:

| Role | Pages |
|---|---|
| Both | `login.php`, `panel.php` (dashboard + logout POST), `cambiar-clave.php` |
| Admin | `usuarios.php` (list/search, activate/deactivate), `usuarios-crear.php`, `usuarios-editar.php` (edit + password reset), `clientes.php`, `proyectos.php`, `asignaciones.php`, `horas-admin.php` (filters + Excel), `resumen-mensual.php` |
| Consultant | `mis-proyectos.php`, `mis-horas.php` (log hours), `mis-reportes.php` (monthly report + Excel) |

Shared helpers in `config/`:

- `exportar-horas.php`: `descargarHorasExcel($registros, $totalCentesimas, $periodo)` streams the xlsx and `exit`s.
- `limite-ingreso.php`: login throttling over table `intentos_login`, 15-minute window: 5 attempts per account from one IP, 50 per account from any IP, 30 per IP. Each attempt is inserted **before** the password check and counted including itself (so parallel requests cannot slip past); rejected attempts are deleted, and a successful login clears the account's rows. The counter key is `cuenta:<id>` when the account exists (so spelling variants share it) or `texto:<lowercased input>`; only its SHA-256 is stored. It fails open: if the table is missing, login keeps working and the error goes to the PHP log. `.htaccess` relies on `REMOTE_ADDR`, so behind a proxy the host needs `mod_remoteip`.
- On a successful login, `login.php` re-hashes the password when `password_needs_rehash()` says so, keeping bcrypt cost aligned with the dummy `password_hash()` used for unknown accounts.

## Conventions to keep

- All identifiers, comments and UI copy are in Spanish.
- Every DB access uses PDO prepared statements; dynamic SQL only concatenates fixed column names from a whitelist (`horas-admin.php`), generated placeholder names (`usuarios.php` search with `ESCAPE '!'`) or integer constants.
- All output goes through `escapar()` / `texto*()`; descriptions use `nl2br(escapar(...))`.
- Every POST checks `hash_equals($_SESSION['csrf'], $_POST['csrf'])`; successful POSTs redirect with **303** (PRG) and pass flash messages through `$_SESSION[...]`.
- Input validation: `is_string()` first, then regex anchored with `\A...\z`, IDs via `FILTER_VALIDATE_INT` (1..4294967295).
- Hours are handled as integer hundredths (`centesimas`), 0.01–24.00 per entry and ≤ 24.00 per consultant per day; consultants may only log dates in the current month up to today (`America/Santiago`). `mis-horas.php` serializes inserts with `SELECT ... FOR UPDATE` on the consultant row.
- Passwords: `password_hash(PASSWORD_DEFAULT)`, 12–72 bytes, no NUL bytes. Consultants sign in with email; admins with username. Login runs one bcrypt even when the account does not exist, so response time does not reveal valid accounts.
- Admin changes are logged with `error_log('MEJR: administrador <id> ...')`, IDs only, no names or emails.
- Deactivation is soft (`activo = 0`); hours and assignments are never deleted.
- **No inline JavaScript** (`<script>` blocks or `on*=` attributes): the CSP in `.htaccess` is `script-src 'self'`. Put scripts in `js/` and load them with `defer`.
- Static assets are cached by Apache (CSS/JS 7 days, images 30 days): bump the `?v=` of a CSS or JS link when you change that file, and give replaced images a new file name. Images are served as WebP; the original `mejr-logo.jpg` and `timesheet.png` are kept as brand masters.

## Gotchas

- `.htaccess` serves both environments: a request to port 8888 (MAMP) goes to HTTPS on port 8890 of the same host; anything else goes to HTTPS on the standard port (302 until verified in production). HSTS is only sent outside localhost. It blocks `config/`, `vendor/`, `sql/` and `.git` (case-insensitive; `config/` and `sql/` also have their own `Require all denied` `.htaccess`) and serves **only** `php|css|js|webp|png|jpg|jpeg|gif|svg|ico` files: any other extension or dotfile gets 403. Adding a new static type (fonts, PDFs) means adding its extension there.
- Changing your own password keeps the current session (its `credencial_version` is refreshed) and closes every other session of that account.
