# Dante Alighieri Society of Virginia — project guide

Custom WordPress theme + seed content for the Dante Society. Runs locally in
**Local** (by Flywheel). GitHub is the source of truth for the **theme (code)**;
**pages and events are content in the WordPress database**, not in Git.

## Repo layout
- `wp-theme/` — the theme; this is what deploys. Symlinked into Local at
  `wp-content/themes/dante-society`.
- `images/` — site images; symlinked to the Local webroot so URLs are `/images/...`.
- `wordpress-import.xml` — one-time WXR seed of the 8 pages + Primary Menu.
- `SETUP-LOCAL.md` — stand it up in Local. `DEPLOY.md` — GitHub → host deploy.
- Static `*.html` / `server.js` — the original static mockup (not used by WordPress).

## Theme architecture
- Classic PHP theme **plus `theme.json`** (settings only): locks the editor to the
  brand palette, named font sizes (with a custom slider), and hides clutter.
  `dante_allowed_blocks` limits the inserter to a friendly set (incl. `dante/events`).
- **Editor UX:** `css/editor.css` (WYSIWYG canvas, large resize handles, visible
  Spacer, image alignment + `is-style-align-*`), `css/editor-chrome.css` (bigger
  toolbar), `js/editor.js` (fixed top toolbar default; breakpoint notice).
- **Responsive:** mobile CSS is generated in PHP (`dante_responsive_css`) at an
  admin-set breakpoint — Customizer → **Layout & Mobile** (default 900px). It is
  intentionally NOT in `style.css` media queries (so the breakpoint is adjustable).
  Component-level calendar responsiveness is a static media query in `style.css`.
- **Nav:** `header.php` uses `wp_nav_menu` with `dante_primary_menu_fallback`, so
  navigation works even before a menu is assigned. No breadcrumb in the page hero.
- **Footer:** hardcoded columns (Contact, Quick Links, About) — the footer widget
  areas are no longer output.

## Events system (`inc/events.php`)
- `event` custom post type: title, editor (description), featured image; a side
  meta box stores `_event_date`, `_event_time`, `_event_location`.
- **Block `dante/events`** (server-rendered, no build step). Options:
  `display` (both/list/calendar), `scope` (all/year/upcoming),
  `listStyle` (cards = image beside text / simple = Programs-style date+title),
  `clickBehavior` (scroll/popup). Insert this block on a page to show events.
  The editor preview is a **static placeholder** — do NOT use `ServerSideRender`
  here, it crashed the editor (floating-ui error).
- **Calendar:** FullCalendar bundled at `js/lib/fullcalendar.min.js`; `js/calendar.js`
  renders month + "This Year's Events" (listYear) + "All Events" (list) views.
  Opens on the next upcoming event. Clicking an event scrolls to it on the page
  (default) or shows a popup. Event data via `dante_get_calendar_events()`
  (HTML entities decoded for the JS text rendering).
- `inc/seed-events.php` — one-time seeder that creates the 5 starter events from
  `/images/`. Guarded by the `dante_events_seeded` option. **Safe to delete**
  (the file + its `require` in `functions.php`) once it has run.
- `page-events.php` template also exists, but the block is the recommended way.

## Gotchas
- **No JS build step.** Editor/block/calendar scripts use the global `wp.*` and
  are enqueued directly.
- **`theme.json` is cached** unless `WP_DEBUG` is on — toggle it in Local to see
  editor control changes. Other JS/CSS changes show on a normal refresh.
- **Pushing to GitHub:** the default stored credential 403s; pushes need the
  keyring `gh` token (has `repo` + `workflow` scopes). One-off:
  `GITHUB_TOKEN= git -c credential.helper= -c credential.helper='!gh auth git-credential' push origin main`.
  Permanent fix: run `gh auth setup-git` in a normal terminal.
- **Deploy:** pushing `wp-theme/**` to `main` triggers
  `.github/workflows/deploy-theme.yml` (rsync over SSH) once host secrets are set
  (see `DEPLOY.md`).
- **Content vs code:** pages/events live in the DB. `wordpress-import.xml` is a
  one-time seed — re-importing on a live site duplicates pages.
