# Gun shop website

A small, fast inventory website for a California gun shop selling new and used firearms.
Plain PHP 8 + SQLite, no framework and no build step, so it runs on ordinary shared hosting.
This repo holds the code with **demo data only**; the shop's real inventory never lives here.

## Features
- **Inventory** with faceted filters (type, CA roster status, manufacturer, caliber, condition,
  price, modifications): live counts, OR within a group / AND across groups, removable filter
  tags, sort, per-page and pagination, all reflected in the URL.
- **Gun detail pages**: graded condition report, photos of the actual gun with a full-screen
  viewer, and factory specs in **imperial and metric**.
- **California-aware**: CA handgun roster status (downloaded from the DOJ and matched to firearms by
  a person), "(10 round limit in CA)" notes, and new off-roster handguns flagged **"LEO Sales Only"**.
- **Compare**: pick up to 4 guns on the inventory or detail pages and see them side by side,
  with a shareable link and a "differences only" view.
- **Stock photos**: new guns without their own photos show the manufacturer image, clearly tagged.
- **Admin** (named accounts, owner / staff roles, activity log, forgot-password email): listings with bulk status changes and photo upload (resized in
  the browser), per-gun spec overrides (a used gun can differ from the factory model), firearm catalog, CA roster, tidy-up of caliber / action spellings, contact messages.
- Home-page hero cycles through the shop's own pictures (managed in the admin); owners get a Sales summary and pick-list tidying; the database is copied automatically each day.
- Contact form with CSRF protection, honeypot and rate limiting.
- Accessible: keyboard-reachable tooltips, 44px targets, ARIA tabs, focus-trapped photo dialog.

## Run it locally
```sh
php dev/build_db.php demo                 # schema.sql + demo_seed.sql -> public/data/gun_specs.db
php -S localhost:8000 dev/router.php      # then open http://localhost:8000/gun-site/
```
Needs PHP 8.1+ with `pdo_sqlite` (and `gd` for demo photos / uploads).

## Layout
| Path | |
|---|---|
| `public/` | Everything deployed to the web folder (`/gun-site/`). |
| `public/app/` | PHP includes and config (blocked from the web by `.htaccess`). |
| `public/data/` | The SQLite database (blocked from the web). |
| `schema.sql` | Full database schema. `demo_seed.sql` is sample data. |
| `public/app/migrations/` | Changes to an existing (live) database, applied automatically on admin page load. |
| `dev/` | Local tools: `build_db.php`, `router.php`, demo and stock photo scripts. |
| `deploy/` | FTPS deploy script (`deploy.ps1`: deploy, `-Backup` the live data, `-Restore` a backup) and admin password tool. |
| `design/` | Original page designs, for reference. |
