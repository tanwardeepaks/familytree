## Quick summary

This is a small, classic PHP/MySQL family-tree app. It renders a nested HTML/CSS family tree in `index.php` and uses a client-side tree library referenced as `FamilyTree.js` (check `familytree.js` in the repo). The server uses procedural PHP with mysqli (global `$conn` from `db.php`) and relies on simple migration scripts in `admin/` to evolve the schema.

## Key files (start here)
- `db.php` — central mysqli connection; `$conn` is expected in most scripts.
- `index.php` — main app: builds a JSON `nodes` array from `family_members` and bootstraps the client tree. Shows permission checks for `families.visibility` and uses `$_SESSION` flags (`admin_id`, `member_id`, `member_family_id`).
- `save_member.php` — canonical example of prepared statements and the dynamic bind_param pattern used by the app. Good reference for handling nullable foreign keys (`father_id`, `mother_id`, `spouse_id`).
- `add_member.php` (redirects to `admin/add_member.php`) and `admin/add_member.php` — admin UI for adding members.
- `admin/*.php` — migration and admin utilities (e.g. `migrate_*` scripts, `setup.php`, `login.php`). They are idempotent-style scripts you can run in-browser to modify schema and create the default admin user.
- `family.sql` — canonical schema + seed; import this when creating the DB.
- `css/styles.css`, `uploads/`, `familytree.js` — client assets (styles, images, JS tree library).

## Architecture & data flow (what matters)
- Web server (Apache/XAMPP) serves PHP pages. PHP scripts read/write MySQL via `db.php` and echo HTML/JSON to the client.
- `index.php` queries `family_members` (left joins to parents/spouse), converts the result into a node array, then assigns it to the client tree (the `nodes` variable). Editing navigates to admin pages (`admin/edit_member.php`).
- Family scoping: the app supports multiple `families` (migrations add a `families` table). `index.php` supports a `family_id` query param and an `include_cross` flag (include related members outside the family). Permission checks rely on `$_SESSION` keys.
- File uploads: photos are saved into `uploads/` and referenced as `uploads/<filename>` in `index.php`.

## Project-specific conventions / patterns
- DB access uses mysqli with a shared `$conn` variable from `db.php`. Use `$conn->prepare`, `$stmt->bind_param`, `$stmt->execute`. See `save_member.php` for a working dynamic bind example (uses `call_user_func_array` to bind variable-length param lists).
- Migrations are plain PHP scripts in `admin/` named `migrate_*.php`. They are written to be idempotent (check columns via `SHOW COLUMNS`) and print results to the browser. Run them via the web UI for quick iteration.
- Session keys to look for: `$_SESSION['admin_id']`, `$_SESSION['admin_username']`, `$_SESSION['member_id']`, `$_SESSION['member_family_id']` — these gate admin/member flows.
- Family visibility: `families.visibility` uses `'private'|'public'`. When private, `index.php` blocks non-members and non-admins.

## Developer workflows (concrete steps)
1. Setup a local PHP/MySQL server (XAMPP suggested). Copy project into document root (example: `D:\xampp82\htdocs\familytree`).
2. Create DB and import schema:

```powershell
mysql -u root -p cfamilytree < "D:\xampp82\htdocs\familytree\family.sql"
```

3. Edit `db.php` to match your MySQL credentials. Restart Apache if needed.
4. Run admin/setup.php in the browser once to create the `admin_users` table and a default admin account (username `admin`, password `admin123`).
5. Run migration pages in `admin/` (e.g. `migrate_add_families.php`, `migrate_member_extra_fields.php`, `migrate_paternal_maternal.php`) if your DB is older than `family.sql` or when upgrading.
6. Open `index.php` (e.g. `http://localhost/familytree/index.php`) to view the tree; use `add_member.php` or the admin pages to add data.

## Debugging tips specific to this repo
- SQL or connection failures: check `db.php` and `$conn->error`. Migration scripts echo errors directly to the browser.
- PHP error output: during development enable `display_errors` or add `ini_set('display_errors',1); error_reporting(E_ALL);` at the top of the script being debugged.
- Asset/JS mismatch: `index.php` references `FamilyTree.js` while the repo contains `familytree.js` — verify the filename/casing when debugging missing-script issues (Windows is case-insensitive, but deployment hosts may not be).
- Check uploaded photos in `uploads/` and ensure filesystem write permissions for the webserver user.
- To inspect Apache/PHP logs on XAMPP (Windows), check `D:\xampp82\apache\logs\error.log` (path depends on your XAMPP install).

## Small code examples to follow
- Prepared statement (pattern used across the app): see `save_member.php` — build SQL with nullable columns, prepare, then bind dynamically using type string and `call_user_func_array`.
- Permission check pattern (see `index.php`):

```php
$isAdmin = isset($_SESSION['admin_id']);
$isMember = isset($_SESSION['member_id']) && $_SESSION['member_family_id'] == $family_id;
if (!($isAdmin || $isMember)) { /* block view */ }
```

## What to watch out for / assumptions
- Codebase uses procedural PHP and mysqli (no modern framework). Expect direct HTML+PHP mixing and minimal abstraction.
- Several migration scripts assume reasonable DB backups; run them only after backing up production data.
- Some scripts print default credentials (admin/admin123). Rotate/remove default credentials in production.

## Quick checklist for an AI agent (what to open first)
1. `db.php` — confirm `$conn` usage.
2. `save_member.php` — input names and prepared statement pattern.
3. `index.php` — node JSON shape & session/permission checks.
4. `admin/migrate_*.php` and `admin/setup.php` — schema changes and admin bootstrap.
5. `family.sql` — canonical schema.

---
If you want, I can iterate and shorten any section, or add a tiny example patch (e.g., unify `FamilyTree.js` vs `familytree.js` naming) — tell me which part is unclear or missing. 
