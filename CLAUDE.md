# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

**MEJR Timesheet**: internal time-tracking app for MEJR Consulting. Consultants log hours against projects they are assigned to; administrators manage consultants, clients, projects, assignments and hourly rates in US dollars, and review hours and money (revenue, cost, margin) on a dashboard. UI is bilingual (Spanish default, English).

- Plain PHP 8.0+ (no framework, no router, no templating engine), one file per screen at the repo root, sharing a common bootstrap and layout from `config/`.
- MySQL/MariaDB via PDO (code checks MySQL error `1062` for duplicate keys). SQL is kept portable to MySQL 5.7/8 and MariaDB 10.x: no `LATERAL`, no window functions, `GROUP BY` lists every non-aggregated column.
- Bootstrap 5.3.8 **CSS only** from jsDelivr (with SRI); no Bootstrap JS. `assets/css/mejr-marca.css` (brand) + `assets/css/styles.css` (app layout), both linked from `config/plantilla-inicio.php`.
- Only Composer dependency: `phpoffice/phpspreadsheet` (Excel export).
- Developed on **MAMP (macOS)** at `/Applications/MAMP/htdocs/NAMP/`, served at `https://localhost:8890/NAMP/` (HTTP on 8888).

## Running locally

There is no build step, no test suite and no linter configured.

1. `composer install` (creates `vendor/`, gitignored).
2. Create `config/conexion.php` (gitignored, **not in the repo**). It must define a `$pdo` PDO instance for MySQL with `PDO::ERRMODE_EXCEPTION` and an associative default fetch mode.
3. The base schema is **not in the repo** (`*.sql` is gitignored; migrations are whitelisted one by one in `.gitignore`). Tables inferred from queries:
   - `usuarios(id, nombres, apellidos, especialidad, usuario UNIQUE, correo UNIQUE, password_hash, rol ENUM('administrador','consultor'), activo)`
   - `clientes(id, nombre UNIQUE, activo)`
   - `proyectos(id, cliente_id, nombre, activo)`, unique on `(cliente_id, nombre)`
   - `proyecto_consultor(proyecto_id, consultor_id, activo)`, unique on the pair
   - `registros_horas(id, consultor_id, proyecto_id, fecha DATE, horas DECIMAL(_,2), descripcion)`
   - `intentos_login`: `sql/003_intentos_login.sql`
   - `tarifas_cliente`, `tarifas_consultor`: `sql/004_tarifas.sql` (check the FK column types against the real schema before running it)
4. Serve the folder with Apache 2.4 + PHP + `mod_rewrite`.

Static files `index.html`, `consultor.html` (+ `js/app.js`, `js/consultor.js`) and `vista-previa.html` are **design prototypes with fake data**, not part of the running app; `.htaccess` refuses to serve them. `LEEME.txt` documents the original brand-styling rollout.

## Screens

| Role | Screen | Notes |
|---|---|---|
| Admin | `tablero.php` | Landing. Dashboard in US$ by period (`desde`/`hasta` months, `mes=` alias) and client: KPIs, by client/project/consultant, 12-month trend, missing rates, Excel |
| Admin | `horas-admin.php` | Read-only list of hours with filters, rates, revenue and cost per row, Excel with amounts |
| Admin | `consultores.php` → `consultor.php` | Table with row actions → profile (no `id` = create): data, assigned projects, pay rate, hours, password reset, status |
| Admin | `clientes.php` → `cliente.php`, `proyecto.php` | Grouped table (client row, its projects below) → client profile (data, billing rate, projects) and project profile (name, assigned consultants, status). A project's client never changes |
| Consultant | `mis-horas.php` | Landing. Log hours, edit/delete own entries of the current month, month view, own Excel (no money) |
| Both | `cambiar-clave.php`, `salir.php` | Change own password; logout (POST) |
| — | `login.php`, `panel.php` | `panel.php` only routes each role to its landing (its POST still logs out) |

Old screens (`usuarios*.php`, `asignaciones.php`, `proyectos.php`, `resumen-mensual.php`, `mis-proyectos.php`, `mis-reportes.php`) are 4-line redirects through `config/redireccion.php`; delete them after one release.

## Page structure

Every screen with a session follows the same shape:

```php
$rolPagina = 'administrador';            // or 'consultor', or null for both
require __DIR__ . '/config/arranque.php'; // session, language, DB, validar-sesion, role 403, CSRF token
$traducciones += [ /* 'Spanish key' => 'English' */ ];
// ... POST handling (csrfValido(), validation, PRG with avisar() + redirigir()), queries ...
$tituloPagina = '...'; $seccion = 'clientes'; $parametrosIdioma = [...]; $scriptsPagina = ['confirmar.js?v=1'];
require __DIR__ . '/config/plantilla-inicio.php';
?> ...HTML... <?php require __DIR__ . '/config/plantilla-fin.php';
```

- `config/arranque.php` runs at file level (not inside a function) because `idioma.php`, `conexion.php` and `validar-sesion.php` create globals. It leaves `$cuenta` (the session user row, read once by `validar-sesion.php`), `$esAdmin`, and the helpers `escapar()`, `texto()` (escape + translate), `validarId()`, `csrfValido()`, `avisar()` (single flash in `$_SESSION['aviso']`), `redirigir()` (303).
- `config/plantilla-inicio.php`: `<head>`, the fixed role navigation with `$seccion` marked, ES/EN switch keeping `$parametrosIdioma`, account menu (`<details>`, no JS) and the flash.
- **Spanish strings are the translation keys**: shared ones live in `config/idioma.php`, page ones in the page's `$traducciones +=`. Change a Spanish string and you must change its key.
- `config/formato.php`: hours as integer hundredths (`mejrEscalar('2.50', 2) === 250`, `mejrHoras()`), US$ as integer cents (`mejrUsd()`), dates and month names per language, `mejrPeriodoMeses()`.
- `config/tarifas.php`: rate rules, `mejrSqlValorizados()` (entries of a period with the rate in force on each date, as a derived table), `mejrMontoExacto()` / `mejrUsdExacto()`.
- `config/asignaciones.php`: one handler for assignments from either side (consultant or project). `config/limite-ingreso.php`: login throttling. `config/exportar-horas.php` / `exportar-tablero.php`: Excel.

## Conventions to keep

- All identifiers, comments and UI copy are in Spanish.
- PDO prepared statements everywhere; dynamic SQL only concatenates whitelisted column names, generated placeholder names or integer constants.
- All output through `escapar()` / `texto()`; descriptions use `nl2br(escapar(...))`.
- Every POST checks `csrfValido()`; success redirects with 303 (PRG) and a flash via `avisar()`.
- Input validation: `is_string()` first, regex anchored with `\A...\z`, IDs via `validarId()`.
- One primary button per screen, next to the title (`.mejr-titulo`); row actions inline at the right (`.mejr-col-acciones` / `.mejr-acciones`); confirmations with `data-confirmar` + `js/confirmar.js`.
- **Hours**: only consultants log them. 0.01–24.00 per entry and ≤ 24.00 per consultant per day. Create, edit and delete only for dates in the current month up to today (`America/Santiago`, re-read inside the transaction); once the month changes it is read-only. Edits exclude the entry itself from the 24 h check. Inserts and edits lock the consultant row `FOR UPDATE`. Consultants can delete their own current-month entries (logged); nothing else is ever deleted.
- **Money**: rates in US$ cents per hour, with `vigente_desde`; each hour is valued with the rate in force on its date. Amounts are summed exactly (hundredths × cents) and rounded only for display, so dashboard, Horas and Excel always match. A rate dated before the current month is accepted only if it is older than every existing rate (initial backfill, loaded from the newest rate back to the oldest); only current/future rates and the oldest one can be deleted. Hours without a rate count as 0 and flag totals as incomplete. **Consultants never see money.**
- Deactivation is soft (`activo = 0`) for consultants, clients, projects and assignments.
- Admin changes are logged with `error_log('MEJR: administrador <id> ...')`, IDs only.
- Passwords: `password_hash(PASSWORD_DEFAULT)`, 12–72 bytes. Login re-hashes when `password_needs_rehash()`, and runs one bcrypt for unknown accounts so timing doesn't reveal them.
- **No inline JavaScript or `style=` attributes**: the CSP in `.htaccess` is `script-src 'self'` and `style-src 'self' https://cdn.jsdelivr.net`. Scripts go in `js/` (listed in `$scriptsPagina`), styles in `assets/css/styles.css`; use `<meter>` or classes instead of inline widths.
- Static assets are cached (CSS/JS 7 days, images 30 days): bump the `?v=` of a CSS or JS link when you change it.

## Gotchas

- `.htaccess` serves both environments: a request to port 8888 (MAMP) goes to HTTPS on port 8890 of the same host; anything else goes to HTTPS on the standard port (302 until verified in production). HSTS only outside localhost. Only `php|css|js|webp|png|jpg|jpeg|gif|svg|ico` are served; `config/`, `vendor/`, `sql/`, `.git` and dotfiles get 403.
- Changing your own password keeps the current session and closes every other session of that account (`credencial_version`). Deactivating a consultant or resetting their password closes their sessions on the next request.
- Apache escapes non-ASCII characters in the error log (`cre\xc3\xb3`), so audit lines with accents look mangled there.
