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

## One codebase, several sites
Each store demo is a **site**: same code, its own name, address, wording, database and web folder.
Everything that differs lives in `sites/<site>/`:

| File | |
|---|---|
| `site.php` | Web folder (`base_url`) and address. Uploaded as `app/site.php`. |
| `shop.php` | Name, address, hours, phone, home page and About wording, demo banner. Uploaded as `app/shop.php`. |
| `hero/` | Home page pictures (not in git). Uploaded to `assets/hero/`. |
| `.env` | FTP login for that site's own FTP account (not in git). |
| `config.local.php` | Admin recovery password, contact email, ip_salt (not in git); `deploy.ps1 -Config`. |
| `live-initial.db` | First database (not in git); `deploy.ps1 -InitDb`. |
| `data/` | Local development database (not in git). |

Sites now: `crosshairs` (Crosshairs Gun Store, the-laurios.com/gun-site) and `glendale`
(Glendale Gun Gallery, the-laurios.com/ggg). A change to the code goes to every site: deploy each
one (`deploy.ps1 -Site <site>`). Something only one store should get belongs in its `shop.php`, or
behind a setting there.

**Adding a site:** copy `sites/glendale/site.php` + `shop.php` to `sites/<new>/` and edit them; make
an FTP account whose home is the new web folder and put its login in `sites/<new>/.env`; run
`SITE=<new> php -S 127.0.0.1:8765 deploy/password-tool.php` for the recovery password; build the
first database with `php dev/new_site_db.php backups/<latest>/gun_specs.db sites/<new>/live-initial.db`
(keeps the firearm catalog and CA roster, no listings/accounts); then
`deploy.ps1 -Site <new>` (uploads everything the first time), `-InitDb`, `-Config`, and open
`/<folder>/admin/` to create the first owner.

**Copying listings between sites** (e.g. Crosshairs' samples into Glendale): `deploy.ps1 -Site <target> -Backup`,
then `php dev/copy_listings.php <source backup folder> backups/<target>/<that backup>` (listings on the public site,
their photos and photo files; firearms matched by slug), then `deploy.ps1 -Site <target> -Restore <that backup>`.
Don't use the target's admin between the backup and the restore.

## Run it locally
```sh
php dev/build_db.php demo                 # schema.sql + demo_seed.sql -> sites/crosshairs/data/gun_specs.db
php -S localhost:8000 dev/router.php      # then open http://localhost:8000/gun-site/
SITE=glendale php -S localhost:8000 dev/router.php   # the Glendale site: http://localhost:8000/ggg/
```
Needs PHP 8.1+ with `pdo_sqlite` (and `gd` for demo photos / uploads).

## Layout
| Path | |
|---|---|
| `public/` | Everything deployed to each site's web folder (`/gun-site/`, `/ggg/`). |
| `sites/<site>/` | What differs per site (see above). |
| `public/app/` | PHP includes and config (blocked from the web by `.htaccess`). |
| `public/data/` | On the server: the SQLite database (blocked from the web). Locally each site's is in `sites/<site>/data/`. |
| `schema.sql` | Full database schema. `demo_seed.sql` is sample data. |
| `public/app/migrations/` | Changes to an existing (live) database, applied automatically on admin page load. |
| `dev/` | Local tools: `build_db.php`, `new_site_db.php`, `router.php`, demo and stock photo scripts. |
| `deploy/` | FTPS deploy script (`deploy.ps1 -Site <site>`: deploy, `-Backup` the live data, `-Restore` a backup) and admin password tool. |
| `design/` | Original page designs, for reference. |
