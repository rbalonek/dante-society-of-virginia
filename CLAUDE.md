# Dante Alighieri Society of Virginia — project guide (for Claude / devs)

Custom WordPress theme + seed content for the Dante Society. This file is the
deep technical context; see `README.md` for the overview, `SETUP-LOCAL.md` to
stand it up, and `DEPLOY.md` for the deploy pipeline.

---

## The single most important mental model

**Theme = code (in Git). Content = database (NOT in Git).**

- **Theme** (everything in `wp-theme/`): PHP/CSS/JS. Lives in this repo, deploys
  to the live server via GitHub Actions. Changing it changes both environments
  (after deploy).
- **Content**: pages, page text, the site title, menus, Customizer settings,
  events, subscribers, uploaded media. Lives in the **WordPress database**, which
  is different on every environment and **never syncs through Git**.

Corollaries that caused real confusion during the build:
- Editing a theme file will **never** change a page's text — that's content.
- A change made on **Local** does **not** appear on **live** (separate databases).
  Make content edits on the environment whose visitors matter (usually **live**).
- `wordpress-import.xml` is a **one-time** seed, not a sync. Re-importing on a
  live site duplicates pages.

---

## Environments

- **Local dev:** [Local](https://localwp.com) by Flywheel. Site `dante`
  (`http://dante.local`). The theme is **symlinked** into Local:
  `…/Local Sites/dante/app/public/wp-content/themes/dante-society` → this repo's
  `wp-theme/`. So editing `wp-theme/` shows on `dante.local` immediately (no copy).
  `/images` is symlinked too.
- **Live demo:** `http://146.235.210.188/` — a WordPress install on an **Oracle
  Cloud VM** (test/demo box; the client moves to permanent hosting if they
  approve). Theme arrives via the deploy pipeline; content was seeded once with
  All-in-One WP Migration and is now edited directly in its wp-admin.

## Repo layout
- `wp-theme/` — the theme; this is what deploys.
  - `inc/` — `events.php`, `newsletter.php`, `photos.php`, `hero-block.php`,
    `seed-events.php`, and `assistant/` (the Dante Assistant chatbot).
  - `template-*.php` — page templates (Home/Events/Membership/Photos). **Named
    `template-*` on purpose — see the slug-collision rule below.**
- `images/` — source images; symlinked to the Local webroot (`/images/...`).
  Background images are **also** bundled inside the theme (`wp-theme/images/`) so
  they deploy — see the background note below.
- `docs/ASSISTANT.md` — guide to the Dante Assistant (board + developer).
- `wordpress-import.xml` — one-time WXR seed of the 8 pages + Primary Menu.
- `SETUP-LOCAL.md`, `DEPLOY.md` — setup + deploy docs.
- Static `*.html` / `server.js` — the original static mockup (not used by WP).

---

## Theme architecture

Classic PHP theme **plus `theme.json`** (settings only).

- **`theme.json`** locks the editor to the brand palette, named font sizes (with
  a custom slider), and hides clutter. `dante_allowed_blocks` limits the inserter
  to a friendly set (paragraph, heading, image, media-text, gallery, list, button,
  quote, separator, spacer, shortcode, and the custom blocks `dante/events`,
  `dante/hero`, `dante/photos`).
- **Editor UX for non-technical board members:** `css/editor.css` (WYSIWYG canvas,
  large resize handles, visible Spacer, labeled image alignment styles
  `is-style-align-*`), `css/editor-chrome.css` (bigger toolbar), `js/editor.js`
  (fixed top toolbar default; a notice stating the mobile breakpoint).
- **Assets are cache-busted by file mtime** via `dante_ver('css/style.css')` etc.
  (NOT a static version). This matters on live — a static `ver=1.0.0` once caused
  updated CSS to keep serving stale from cache.
- **Responsive:** mobile CSS is generated in PHP (`dante_responsive_css`) at an
  admin-set breakpoint — Customizer → **Layout & Mobile** (default 900px). It is
  intentionally NOT in `style.css` media queries. The calendar's own small-screen
  tweaks are a static media query in `style.css`.
- **Nav:** `header.php` uses `wp_nav_menu` with `dante_primary_menu_fallback`
  (works before a menu is assigned). A **"Calendar"** item is auto-injected into
  the primary menu via `dante_add_calendar_menu_item` (a filter) — it opens the
  calendar popup (see Events). Hero title/tagline **and an optional "Opening
  Message" box** come from Customizer (**Hero Section**) with defaults in
  `header.php` (the message is `dante_hero_message`). The message box is **hidden
  until it's filled in** — no placeholder shows when empty. The hero uses smaller
  type and a lighter image overlay so the background painting reads as a feature.
- **Logo:** uploaded via Appearance → Customize → Site Identity. `.custom-logo`
  is constrained to `height: 64px` in `style.css` (without that rule the emblem
  renders huge).
- **Footer:** hardcoded columns (Contact, Quick Links, About). The footer widget
  areas are no longer output.
- **Backgrounds:** default page + hero backgrounds are bundled in
  `wp-theme/images/` and referenced **relative** (`../images/...`) so they deploy
  with the theme. Overridable via Customizer → **Background Images**
  (`dante_bg_image`, `dante_hero_image`) which output inline CSS. The default page
  background is the Domenico di Michelino "Dante and the Divine Comedy" painting.

---

## Page templates & the slug-collision rule ⚠️

WordPress's template hierarchy **auto-applies a theme file named
`page-{slug}.php` to the page with that slug — outranking the Template dropdown.**
So a `page-events.php` file rendered the "events"-slug page even when it was set
to "Default template," injecting content nobody could find/edit. This bit us on
Home, Events, and Membership.

**Rule: name custom page templates `template-*.php`, never `page-{slug}.php`.**
The `Template Name:` header still makes them appear in the Template dropdown; the
filename just avoids the automatic slug match. Current templates:
- `template-home.php` — **Home Page** (full-screen splash, below).
- `template-events.php` — **Events Page** (PHP calendar+list; legacy — prefer the
  `dante/events` block on a Default-template page).
- `template-membership.php` — **Membership Page** (renders the page's blocks).
- `template-photos.php` — **Photos Page** (dark gallery, below).
- `page-checkout.php` — the **only** intentional `page-*` file left; it's a pure
  PHP mockup with no block fallback and is referenced by name in `functions.php`
  to enqueue `js/checkout.js`. Its page slug isn't "checkout," so no collision.

## Home page (splash) + Full Screen Hero block

- **`template-home.php`** — a full-bleed canvas: the header is overlaid
  (transparent) via the `page-template-template-home` body class, and it renders
  the page's blocks edge-to-edge. If the page has no hero block it falls back to
  `dante_render_hero_block()` so it's never empty.
- **Block `dante/hero`** (`inc/hero-block.php`, `js/hero-block.js`) — the
  configurable splash hero: show/hide title, line, subtitle, button; edit the
  button text/link and button/line colors. Server-rendered, no build step. The
  **background image** comes from Customizer → Background Images → "Homepage hero
  background" (falling back to the bundled portrait), so the picture lives in one
  obvious place and everything else is on the block.

## Photos system (`inc/photos.php`)

- **`dante_photo` CPT** — one picture per entry (Featured Image = the photo, title
  = optional caption). Managed under the **Photos** admin menu.
- **Bulk Add** (Photos → Bulk Add) — a `wp.media` multi-select that creates a
  Photo per image via AJAX (`dante_bulk_add_photos`), with dedup + image-only
  guards.
- **Block `dante/photos`** ("Photo Collage") — server-rendered masonry (CSS
  columns) of all photos; a `size` attribute sets the column width. The assistant
  can also add photos (`add_photo` tool).
- **`template-photos.php`** — the "Photos Page" gallery design: solid dark-green
  background, a centered `GALLERY / <title> / rule / intro` header (the page's own
  content is the intro line), and the collage restyled with no cards (clean images
  + italic serif captions over a gold rule). Scoped via `page-template-template-photos`.

## Dante Assistant (`inc/assistant/`)

Chat-based site editing on the **Dashboard** (a widget). Board members type plain
English to add events, compose/schedule newsletters, edit page wording, and add
photos — draft-and-approve or one-click-undo throughout. Server-side **agent
loop** with tools; the AI API key is set per-site in **Settings → Dante
Assistant** (stored in the DB, so Local and live are configured separately).
**Full guide: `docs/ASSISTANT.md`.**

---

## Events system (`inc/events.php`)

- `event` custom post type: title, editor (description), featured image; a side
  meta box stores `_event_date`, `_event_time`, `_event_location`.
- **Block `dante/events`** (server-rendered, no build step). Editor UI is a
  **static placeholder** — do NOT use `ServerSideRender`, it crashed the editor
  (floating-ui error). Attributes:
  - `display`: `both` / `list` / `calendar`
  - `scope`: `all` / `year` (this calendar year) / `upcoming` — **default is
    `upcoming`**, so past events drop off the list automatically.
  - `listStyle`: `cards` (image beside text) / `simple` (Programs-style date+title)
  - `clickBehavior`: `scroll` (jump to the event on the page) / `popup` (detail modal)
  The list is dynamic — it queries events live, so adding/editing events updates
  every page that shows the block automatically. It is ordered **earliest date
  first** (`dante_event_list_query`, `order => ASC`) for every scope. The
  **calendar** still shows all events (past + future); only the *list* is filtered.
- **Calendar:** FullCalendar bundled at `js/lib/fullcalendar.min.js`; `js/calendar.js`
  drives both the **inline** block calendar (`#dante-calendar`) and the **site-wide
  popup** (`#dante-calendar-popup`) opened by any nav link to `#calendar`.
  - The **inline** calendar keeps the view switcher: month + "This Year's Events"
    (listYear) + "All Events" (list).
  - The **popup** is a redesigned cream modal (`dante_calendar_popup_markup` in
    `wp_footer`): a title + society subtitle + round close, a **"This month" list
    above the toolbar** (green date badge + title + time/location, or "No events
    scheduled for {Month Year}"), then the month grid. Toolbar is
    `prev,next today nextEvent` — **"Next Event"** jumps to the next upcoming
    event's month. Clicking a **month-list card OR a calendar event** opens the
    event-detail modal; its image scales to fit (max-height 60vh, no crop) so tall
    posters aren't cut off.
  - Opens on the next upcoming event. Data via `dante_get_calendar_events()` (HTML
    entities decoded). Assets load site-wide (`dante_events_assets`).
- `inc/seed-events.php` — one-time seeder that creates the 5 starter events from
  `/images/`. Guarded by the `dante_events_seeded` option. **Safe to delete** (the
  file + its `require` in `functions.php`) once it has run. Note: it runs once on
  every environment (incl. live) unless removed.
- `template-events.php` — a legacy "Events Page" template that prints
  `dante_events_markup()` (calendar+list) via **PHP, ignoring the page's blocks**.
  The **`dante/events` block on a Default-template page is the recommended way**;
  the template predates it. (Renamed from `page-events.php` — see the
  slug-collision rule above.)

## Newsletter system (`inc/newsletter.php`)

Custom, sends via `wp_mail`. Admin menu **Newsletter** → Compose + Subscribers.
- **Subscribers:** `dante_subscriber` CPT (email stored as the post title, plus
  `_nl_name`, `_nl_status`, `_nl_token`). Add/manage in Newsletter → Subscribers.
  Front-end signup via the `[dante_subscribe]` shortcode.
- **Composer:** three templates — **all upcoming events**, **a single event**, and
  **a free message** (rich editor). Editable subject/headline/intro + footer, live
  preview, "send test to <address>", and "send to all subscribers".
- **Compliance:** every email includes the mailing address + a working
  **unsubscribe** link/button (token → `/?dante_unsub=…`, handled in
  `dante_handle_unsubscribe`).
- **Delivery:** `wp_mail` alone has poor inbox placement, and Local doesn't send
  real email. Install **WP Mail SMTP** and use the **"Other SMTP"** mailer (NOT the
  OAuth "Google" mailer, which needs a Client ID/secret/URI). For ~10 recipients/
  month a **Gmail App Password** is fine; Brevo (one API key) is the tidier
  handoff. Settings live in the DB and are changeable per environment.

## Membership checkout demo (added later)
- `template-membership.php` (Template: "Membership Page", renders the page's own
  blocks) and `page-checkout.php` (Template: "Membership Checkout (Demo)") +
  `js/checkout.js`. A **Stripe-style mockup only** — no payment is processed,
  nothing is sent. Individual $35 / Family $60. Replace `checkout.js` with real
  Stripe.js / a Checkout Session when a Stripe account is connected.

---

## Deploy & Git

- Pushing `wp-theme/**` (or the workflow file) to `main` triggers
  `.github/workflows/deploy-theme.yml` — **rsync over SSH** of `wp-theme/` to the
  live server's theme dir (`--delete` mirror). Manual run available
  (Actions → "Deploy theme to WordPress" → Run workflow). Host details are GitHub
  **secrets** (`SSH_HOST`, `SSH_USER`, `SSH_PRIVATE_KEY`, `REMOTE_THEME_PATH`) — not
  in the repo.
- **Pushing needs the keyring `gh` token** (repo + workflow scopes); the default
  stored credential 403s. One-off:
  `GITHUB_TOKEN= git -c credential.helper= -c credential.helper='!gh auth git-credential' push origin main`.
  Permanent fix: `gh auth setup-git`.
- **All-in-One WP Migration caveat:** AIO carries the whole DB **and** theme files,
  but it can't follow Local's **symlinked** theme cleanly — importing a `.wpress`
  on live can overwrite the good git-deployed theme with a stale copy. After any
  AIO content migration, **re-run the deploy** to restore the correct theme.

## Editing Local content directly (technique)

Local's MySQL isn't reachable by the host `wp` (socket mismatch), but the bundled
client works. Pattern used to edit page content programmatically on Local:
- Client: `…/Local/lightning-services/mysql-*/bin/*/bin/mysql`
- Socket: `~/Library/Application Support/Local/run/<id>/mysql/mysqld.sock`
- Creds: `-u root -proot`, DB `local`, prefix `wp_`.
- e.g. `UPDATE wp_posts SET post_content='…' WHERE ID=10;` (Home is page ID 10 and
  the front page). Back up first; content has no single quotes so single-quoting
  is safe. This is Local-only — the same change must be made on live separately
  (its wp-admin, or its own DB).

---

## Gotchas (quick list)
- **No JS build step.** Editor/block/calendar scripts use global `wp.*`.
- **Slug-collision rule:** a `page-{slug}.php` file auto-renders that page
  regardless of the Template dropdown. Name custom templates `template-*.php`. See
  the dedicated section above — this caused several "can't edit / can't remove
  this content" bugs.
- **`theme.json` is cached** unless `WP_DEBUG` is on — toggle it in Local to see
  editor-control changes.
- **Content vs code / Local vs live** — see the top of this file. This is the
  source of ~every "why didn't my change show up" question.
- **Meta stored as JSON** (assistant change-log `_ops`, newsletter `_nl_data`)
  must be `wp_slash()`ed before `update_post_meta` (it `wp_unslash`es internally),
  or `\uXXXX` escapes get corrupted (dashes/accents turn into `u2013` etc.).
- **`background-attachment: fixed`** is switched to `scroll` on mobile in
  `dante_responsive_css` — iOS renders fixed backgrounds blurry/upscaled.
- **Custom logo** needs the `.custom-logo { height: 64px }` rule or it's huge.
- **Email** needs WP Mail SMTP (Other SMTP + Gmail app password / Brevo).
- **Dante Assistant** needs an API key per site (**Settings → Dante Assistant**);
  without it the widget shows "not set up yet."
- **Seeder** (`inc/seed-events.php`) runs once per environment; delete it when done.
