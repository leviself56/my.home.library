# my.home.library

my.home.library is a lightweight PHP + MySQL console for tracking the books you lend, who has them, and their condition. It ships with a single-page UI, REST-style API, and visual workflows for check-ins, check-outs, and reader management.

## Highlights
- Librarian dashboard with metrics, due-soon and recent-activity feeds
- Book catalog with photos, availability filters, and attachments
- Guided check-in / check-out drawers that capture notes, return windows, and photos
- Reader and librarian directory with password resets and delete safeguards
- Personal history view so borrowers can review their own loans and comments
- Installer that bootstraps the schema, seeds defaults, and creates the first librarian
- 30-day sticky sessions (file-based storage under `storage/sessions`) for web and mobile browsers

## Requirements
- PHP 8.1+ with `mysqli`, `json`, and `mbstring`
- MySQL or MariaDB 10.5+ (UTF8MB4 with `utf8mb4_uca1400_ai_ci` collation recommended)
- Web server configured for PHP (Apache, Nginx+PHP-FPM, Caddy, etc.)
- Composer is not required for this app; everything in `/html/my.home.library` is ready to deploy

## Installation
1. **Place the files**
   - Clone or extract the repository into your web root. The app lives under `html/my.home.library`.
   - Point your virtual host / document root at that directory.
2. **Prepare folders**
   - Ensure PHP can write to the project root and `storage/sessions`:
     ```bash
     cd /path/to/my.home.library
     chmod -R 775 storage/sessions
     chmod 664 config.php  # file is created by the installer; adjust after install if desired
     ```
3. **Create a database/user** (optional)
   - Create an empty MySQL schema and a user with full privileges on that schema, or let the installer create it for you by providing host credentials with create-db rights.
4. **Run the browser installer**
   - Visit `https://your-host/install.php`.
   - Step 1: enter database host, username, password, and schema name. The installer creates the DB (if it doesn’t already exist) and imports `database/migrations/default-schema.sql`.
   - Step 2: enter site title/subtitle and your first librarian’s name, username, and password. The installer tests file permissions (`config.php`, `storage/sessions`), seeds default settings, writes `config.php`, and logs you in.
5. **Secure the install surface**
   - Remove public access to `install.php` after completion (rename or restrict via web server config) so nobody reruns the installer over an active instance.

## Usage
- Sign in at `/` with the librarian account you created.
- Use the sidebar panels to navigate:
  - **Dashboard** shows current totals, due-soon items, and recent activity.
  - **Books** lists every title with search, filters, and detail drawers for attachments and status changes.
  - **Check Out / Check In** workflows walk you through lending and receiving books, capturing photos and notes.
  - **Users** lets librarians create, update, or delete reader/librarian accounts (with mirror safety prompts).
  - **My Account / My History** panels are available to all authenticated users for profile edits and viewing their loan timeline.
  - **Settings** provides quick editing of the site title/subtitle shown in the UI.
- Sessions persist for roughly 30 days thanks to the file-backed session store. If you deploy on HTTPS and need `SameSite=None`, set `session.sameSite` + `session.domain` in `config.php` to match your host.

## Maintenance & Upgrades
- Schema changes live in `database/migrations`. Re-running `default-schema.sql` is idempotent (`CREATE TABLE IF NOT EXISTS` plus `INSERT IGNORE`).
- Configuration lives in `config.php`. The installer writes database credentials and the `installed` flag; you can extend it with a `session` section if you need custom cookie domains or storage paths.
- Session files accumulate in `storage/sessions`. PHP cleans these automatically per your `session.gc_*` settings, but you can also prune them manually during maintenance windows.
- API endpoint docs remain alongside each route under `api/**/README.md` if you plan to integrate third-party systems.

## Development Tips
- You can run the UI locally with PHP’s built-in server:
  ```bash
  php -S localhost:8080 -t html/my.home.library
  ```
  Ensure MySQL is reachable from your machine and that `config.php` contains valid credentials.
- When working on frontend assets, `assets/js/app.js` contains the SPA logic and `assets/css/app.css` holds the core styles.

For issues or feature ideas, open a ticket in the repository and include your PHP/MySQL versions plus any installer output or logs.
