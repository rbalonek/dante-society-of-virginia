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
- **Live production:** **https://dantesocietyofva.org** (and `www.`) — **launched
  2026-08-11**, public, HTTPS via Let's Encrypt. Self-managed WordPress on an
  **Oracle Cloud Always-Free VM**, instance `instance-20260719-1312`, reserved IP
  **`159.54.174.73`**, Ubuntu **22.04.5**, Apache 2.4 + MariaDB + PHP 8.3, web root
  `/var/www/html`. SSH: `ssh -i ~/.ssh/dante-oracle-2026 ubuntu@159.54.174.73`
  (key-only). **⚠️ Two earlier IPs are dead and appear all over older notes:
  `146.235.210.188` (hacked July 2026) and `167.234.212.48` (the interim demo box).
  Neither answers — if a command hangs or is refused, check which IP you used.**
  Content is edited directly in its wp-admin; DNS is register.com via the Weebly
  dashboard (never touch the nameservers — see the client-config `infrastructure.md`).

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
- **Footer:** three columns. **Contact** is hardcoded (P.O. Box). **Quick Links**
  is a `wp_nav_menu` on the **"Footer Links"** location (Appearance → Menus), with
  `dante_footer_menu_fallback` until one is assigned. **About** blurb is Customizer
  → **Footer** (`dante_footer_about`). Old footer widget areas are not output.
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
- **Composer:** four types — **all upcoming events**, **a single event**, **a free
  message** (rich editor), and **finished HTML** (`custom_html`). Editable
  subject/headline/intro + footer, live preview, "send test to <address>",
  "send to all subscribers", and "download the composed HTML".
- **Saved newsletter templates** (`wp-theme/newsletter-templates/*.html`) — the
  designed-elsewhere route. Drop a complete HTML email in that folder, commit, and
  it appears in the composer's **"Start from a saved template"** dropdown (visible
  once *Newsletter type* is set to "Paste finished HTML"). Choosing one loads it
  into the textarea, still editable before sending. Because they're theme files
  they travel through Git and the normal theme deploy — **adding a design is
  "commit a file", not "log in and paste"**.
  - **Label** = the `<!-- Template Name: … -->` comment if present, else `<title>`,
    else the filename. Keep the comment in the first 2KB of the file — that's all
    the reader scans.
  - **Loaded over AJAX** (`dante_nl_load_template`, nonce `dante_nl_tpl`,
    `manage_options` only). Slug is `basename( sanitize_file_name() )` + an
    `is_file()` check, so a crafted slug can't escape the templates dir.
  - `custom_html` is sent **exactly as written** — the Dante header/footer and the
    Unsubscribe button are NOT wrapped around it. Put `{{unsubscribe_url}}`
    somewhere in the HTML and each recipient gets their own one-click link.
    Images must already be hosted `https://` URLs (upload to the Media Library
    first and paste those URLs in).
  - First one in the folder: `magic-flute-invitation.html` (Oct 2026 Opera on the
    James reception).
- **Compliance:** every email includes the mailing address + a working
  **unsubscribe** link/button (token → `/?dante_unsub=…`, handled in
  `dante_handle_unsubscribe`).
- **Delivery:** `wp_mail` alone has poor inbox placement, and Local doesn't send
  real email. **Live now uses FluentSMTP** (installed 2026-09-03 — see "Installing
  a plugin" below, since `DISALLOW_FILE_MODS` blocks the dashboard installer).
  WP Mail SMTP with the **"Other SMTP"** mailer is the equivalent alternative (NOT
  the OAuth "Google" mailer, which needs a Client ID/secret/URI). For ~10
  recipients/month a **Gmail App Password** is fine; Brevo (one API key) is the
  tidier handoff. Settings live in the DB, so **Local and live are configured
  separately**.

## Membership checkout demo (added later)
- `template-membership.php` (Template: "Membership Page", renders the page's own
  blocks) and `page-checkout.php` (Template: "Membership Checkout (Demo)") +
  `js/checkout.js`. A **Stripe-style mockup only** — no payment is processed,
  nothing is sent. Individual $35 / Family $60. Replace `checkout.js` with real
  Stripe.js / a Checkout Session when a Stripe account is connected.

## Subscription / hosting-billing portal (`inc/subscription.php`)

**This is the developer billing the *client* for hosting — NOT the Society billing
its members** (that's the membership mockup above; don't conflate them). It's a
client-facing **"Subscription"** admin menu (money icon, `manage_options`) + a
**Dashboard widget**, plus a **Settings** subpage. **Single-client, intentionally
hardcoded** — "a better solution comes after launch."

- **Everything is hardcoded** in `define()`s at the top of the file:
  `DANTE_SUB_PAYMENT_LINK` (`https://buy.stripe.com/eVq3cudL22vO8KBaZj3AY00`),
  `DANTE_SUB_PLAN_NAME` (*"Dante Society of Virginia Wordpress Site"*, Stripe product
  `prod_Ux8AwC3oZAUPlx`), `DANTE_SUB_PLAN_DESC`. Nothing about the plan is editable in
  wp-admin. No Stripe key / API / secret on the box.
- **Billing screen = the Payment Link in an `<iframe>`** (embedded checkout, same
  window). This works because the Payment Link sends **no `X-Frame-Options` and no CSP
  `frame-ancestors`** — verified via `curl -I` (the Customer *Portal* is NOT frameable,
  the Payment Link is). A "open in a new tab" fallback link sits under the iframe in
  case Stripe ever frame-busts. The **dashboard widget** doesn't embed the iframe (too
  cramped) — it links to the Billing page.
- **Subscribed vs Unsubscribed is a MANUAL toggle** — the ONLY setting, stored in the
  `dante_subscription_status` option (radio in Settings). Unsubscribed → the embedded
  checkout; Subscribed → a green "✓ Subscription active / Current Subscription"
  confirmation. Client checks out once, owner flips the toggle. No live Stripe check
  (if they cancel, the site won't know until you flip it back) — fine for a one-time
  subscription; automatic status would need the API + a key.

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
- **⚠️ The Action is currently failing — the deploy secrets still point at the dead
  `167.234.212.48` box.** Until `SSH_HOST` (→ `159.54.174.73`) and
  `SSH_PRIVATE_KEY` (→ `~/.ssh/dante-oracle-2026`) are updated in
  Settings → Secrets and variables → Actions, **a push does not reach the live
  site**. Manual deploy in the meantime:
  ```bash
  rsync -avz --delete -e "ssh -i ~/.ssh/dante-oracle-2026" \
    wp-theme/ ubuntu@159.54.174.73:/var/www/html/wp-content/themes/dante-society/
  ```
  (`REMOTE_THEME_PATH` = `/var/www/html/wp-content/themes/dante-society`,
  `SSH_USER` = `ubuntu`.) Note `ubuntu` may not own the theme dir — if rsync gets
  permission errors, sync to `~` and `sudo rsync` into place, or `sudo chown -R`
  the theme dir once.
- **All-in-One WP Migration caveat:** AIO carries the whole DB **and** theme files,
  but it can't follow Local's **symlinked** theme cleanly — importing a `.wpress`
  on live can overwrite the good git-deployed theme with a stale copy. After any
  AIO content migration, **re-run the deploy** to restore the correct theme.

## Installing a plugin on live (with `DISALLOW_FILE_MODS` on)

`wp-config.php` sets `DISALLOW_FILE_MODS = true`, so **Plugins → Add New is gone**
from the dashboard. That's deliberate: board members are WordPress *administrators*,
and without the flag an admin can upload a plugin containing one line of PHP and read
`/var/www/dante-secrets.php` (the Anthropic key). Uploading a fake plugin is exactly
how the July 2026 attacker landed their webshells. **Keep the flag on.**

The line is guarded, which matters if you go looking for it:

```php
if ( ! defined( 'DISALLOW_FILE_MODS' ) ) {
        define( 'DISALLOW_FILE_MODS', true );
}
```

**Preferred — install over SSH, flag untouched:**
```bash
ssh dante 'set -e
cd /tmp
curl -sLO https://downloads.wordpress.org/plugin/<slug>.zip
sudo unzip -oq <slug>.zip -d /var/www/html/wp-content/plugins/
sudo chown -R www-data:www-data /var/www/html/wp-content/plugins/<slug>
rm <slug>.zip'
ssh dante 'sudo -u www-data wp plugin activate <slug> --path=/var/www/html'
```
`<slug>` is the plugin's wordpress.org URL slug. **Activation is not blocked** by
the flag — only install/update and the code editors are, so the dashboard's
Activate link works too.

**Fallback — temporarily lift the flag** (used 2026-09-03 for FluentSMTP): back up
`wp-config.php` **outside the web root**, comment out *only* the `define(` line
(an empty `if` block is valid PHP), install from the dashboard, then uncomment it
**in the same sitting**. Verify with the function WordPress itself calls, not by
reading the constant — the value passes through a `file_mod_allowed` filter that a
constant check would miss:
```bash
sudo -u www-data wp eval 'var_dump( wp_is_file_mod_allowed("install_plugin") );' --path=/var/www/html
```
`bool(true)` = installs allowed (the open state), `bool(false)` = locked.

An `ssh dante` shortcut is worth having in `~/.ssh/config` so nobody has to remember
which of the three historical IPs is current:
```
Host dante
    HostName 159.54.174.73
    User ubuntu
    IdentityFile ~/.ssh/dante-oracle-2026
    IdentitiesOnly yes
```

## Security incident & server rebuild (July 2026)

The **old live box (`146.235.210.188`, "dante-demo", Ubuntu) was compromised** on
2026-07-12. We contained it, did offline forensics, then **rebuilt on a clean VM**
rather than disinfect. The Git repo and deploy pipeline were **never** affected —
the compromise lived only on that server's DB + filesystem.

### What happened (fully scoped from forensics)
- **Entry:** attacker (`64.227.190.95`) enumerated the admin username via
  `/wp-json/wp/v2/users` + the user sitemap, brute-forced `xmlrpc.php`/`wp-login.php`,
  and logged into wp-admin with a **weak/default "admin" password**.
- **Payload:** uploaded a fake `chajian` plugin ("My Custom Plugin / Does amazing
  things") containing China-Chopper webshells (`@eval` of an XOR+base64 POST body),
  dropped a second webshell at `wp-content/languages/luest.php`, created 2 rogue
  admin users, and installed a **UPX-packed ELF miner/bot** at
  `/var/cache/apache2/mod_cache_disk/d20PU1` run every minute via a `www-data` cron.
- **Scope:** **web-layer only** — no attacker SSH keys, no root cron, core files +
  the git-deployed theme were clean. (The earlier "my SSH key is rejected" was NOT
  tampering — the box used a different RSA key that lived in the GitHub deploy secret.)

### The new box
- **`167.234.212.48`** — Oracle **Ubuntu 22.04**, user `ubuntu`.
- SSH key: **`~/.ssh/dante-oracle-2026`** (passphraseless; also the intended deploy key).
- Stack: **Apache + PHP 8.1 + MariaDB 10.11**, WordPress at **`/var/www/html`**.
- DB `dante` / user `dante` — password in **`/root/.dante-db-pass`** (root-only).
- WP admin user **`bbalonek`** — password in **`/root/.dante-admin-pass`** (root-only).
  Do NOT use "admin" and do NOT commit these anywhere.

### OCI/server-specific config (gotchas that bit us — keep them)
- **OCI Ubuntu ships iptables allowing only SSH.** Opening a port in the OCI
  Security List is **not enough** — you must also `iptables -I INPUT ... --dport 80
  -j ACCEPT` on the box and `netfilter-persistent save`. (Same for 443 at launch.)
- **Loopback DNAT:** the box can't reach its own public IP (Security List only
  allows the admin IP), which made every wp-admin request that does an internal
  HTTP call hang ~15s. Fixed with nat `OUTPUT` DNAT of `167.234.212.48:80/443 →
  127.0.0.1` (persisted). Also `DISABLE_WP_CRON=true` + a system cron running
  `wp cron event run` (keeps cron off the loopback).
- **AllowOverride:** Apache's default `AllowOverride None` makes WP ignore
  `.htaccess`, which **404s the REST API (`/wp-json/`) and all pretty permalinks**
  (symptom: editor says "Could not retrieve the featured image data"). Fixed via
  `/etc/apache2/conf-available/wp-htaccess.conf` (`AllowOverride All` for
  `/var/www/html`) + `wp rewrite flush --hard`.

### Hardening applied (closes the exact holes used)
- **Limit Login Attempts Reloaded** plugin (brute-force lockout) — keep it active.
- Server-side must-use plugin **`wp-content/mu-plugins/dante-hardening.php`**:
  disables XML-RPC, blocks `?author=N` + REST `/users` enumeration.
  **⚠️ This file lives on the server, NOT in Git** — a fresh rebuild/redeploy won't
  include it; recreate it (or promote it into the theme) if the box is rebuilt again.
- `DISALLOW_FILE_EDIT` = true (no dashboard code editor).
- **`DISALLOW_FILE_MODS` = true** (added 2026-07-25, in `wp-config.php`) — blocks
  plugin/theme **install + updates** and the code editors from the dashboard. This is
  what makes on-server secrets genuinely unreadable by board admins: they can't run
  arbitrary PHP through the UI anymore. **Trade-off: do plugin/core updates via
  `wp-cli`/SSH**, not the dashboard.

### Server-side secrets (added 2026-07-25, NOT in Git)
The board members are WP **admins**, so anything the WP process can read, a determined
admin could too — *unless* they can't run code (hence `DISALLOW_FILE_MODS` above). Keys
live in **`/var/www/dante-secrets.php`** — **outside** the web root (can't be fetched
over HTTP), perms **`640 root:www-data`**, `require`d from `wp-config.php` (in the custom
block, before `ABSPATH` — so **no `if(!defined('ABSPATH'))exit;` guard**, that would kill
WP). It defines constants like `DANTE_ANTHROPIC_KEY` (and could hold `DANTE_STRIPE_SECRET`
if Stripe ever moves to the API route). **Like the mu-plugin, this file is server-only —
a rebuild won't recreate it.**
- The **Dante Assistant** now resolves its key via `dante_assistant_api_key()`
  (`inc/assistant/providers.php`): **`DANTE_ANTHROPIC_KEY` constant/env first, DB option
  as fallback.** When the constant is set, Settings → Dante Assistant shows "Managed on
  the server" (field disabled). Until then it still reads the DB key (currently present).

### HTTPS (Cloudflare Tunnel — interim demo TLS, added 2026-07-23)
The box has **no TLS cert and 443 isn't public** (firewalled to admin IP), so phones
that auto-upgrade to `https://167.234.212.48` **hang**. Fixed for demos with a
**Cloudflare quick tunnel** — instant HTTPS, no domain, no DNS/GoDaddy work.
- **`cloudflared`** (installed via the amd64 `.deb`) runs as **systemd service
  `dante-tunnel.service`** (`cloudflared tunnel --no-autoupdate --url http://localhost:80`;
  enabled, `Restart=on-failure`, survives reboot). **Lives on the server, NOT in Git**
  — like `dante-hardening.php`, a rebuild won't recreate it.
- **The public URL is random** (`https://<words>.trycloudflare.com`) and **changes on
  every service restart / reboot** — quick tunnels aren't stable. Get the current one:
  `ssh -i ~/.ssh/dante-oracle-2026 ubuntu@167.234.212.48 'dante-tunnel-url'`
  (helper script `/usr/local/bin/dante-tunnel-url` greps it from the journal).
- **WordPress gotcha (the actual fix):** stored siteurl/home is `http://167.234.212.48`,
  so WP was **301-redirecting every tunnel visitor back to the raw http IP** (= the phone
  hang) and emitting mixed content. A block in **`wp-config.php`** (backed up to
  `wp-config.php.bak-*`) makes WP protocol/host-aware: trusts `X-Forwarded-Proto: https`
  and defines `WP_HOME`/`WP_SITEURL` dynamically from `$_SERVER['HTTP_HOST']`. It's
  host-agnostic **on purpose** so it keeps working as the tunnel URL changes; the raw IP
  still resolves to `http://167.234.212.48` for admin. cloudflared forwards `HTTP_HOST` =
  the tunnel hostname (verified). **Also server-side, NOT in Git.**
- Manage: `sudo systemctl {status,restart,stop} dante-tunnel.service`.
- **Upgrade path for a *stable* URL:** a **named tunnel** on the GoDaddy domain once it's
  on Cloudflare (permanent `demo.<domain>` hostname), or the Let's Encrypt route below.
  Waiting on the client granting domain access before switching over.

### Content (rebuilt by hand — zero bytes imported from the compromised DB)
The DB was never restored. Page text + event data were **extracted from a read-only
forensic clone**, scanned for injection, and recreated on the clean box via wp-cli:
9 pages + 13 events (10 with posters reattached as featured images), Primary + Footer
menus, front page = Home. Media was re-imported clean (46 MB, 26 attachments; the
`.pages` source file was skipped — the PDF is the usable one). There were **no real
newsletter subscribers** to preserve.

### Still outstanding (updated 2026-09-03)
- **Update GitHub deploy secrets** — `SSH_HOST` → **`159.54.174.73`**,
  `SSH_PRIVATE_KEY` → `~/.ssh/dante-oracle-2026`, `REMOTE_THEME_PATH` →
  `/var/www/html/wp-content/themes/dante-society`, `SSH_USER` → `ubuntu`. They still
  point at a dead box, so **the deploy Action fails and pushes do not reach live**
  (ClickUp 868kgagf5). Manual rsync in the meantime — see "Deploy & Git".
- **Finish the Anthropic-key migration:** it currently still sits in the DB (readable
  by admins). Rotate it, put the NEW key in `DANTE_ANTHROPIC_KEY` in
  `/var/www/dante-secrets.php`, then clear the DB copy
  (`wp option patch update dante_assistant_settings anthropic_key ''`) and revoke the old.
- **Stripe billing:** the Payment Link + product are hardcoded in `inc/subscription.php`
  (single-client). Just set a **statement descriptor** (e.g. `DANTE SITE HOSTING`) so the
  card statement reads as yours, not "WordPress." Post-launch, replace the hardcoded/
  manual-toggle approach with a real per-client + live-status solution.
- ~~HTTPS via Cloudflare quick tunnel~~ — **DONE and superseded.** The site launched
  2026-08-11 on **https://dantesocietyofva.org** with a real Let's Encrypt cert
  (certbot `--apache`, auto-renew installed, expires 2026-11-04) and 80/443 open to
  the world. The "HTTPS (Cloudflare Tunnel)" section below is **historical only** —
  it describes the retired `167.234.212.48` demo box.
- **Server maintenance is overdue** (noticed 2026-09-03 at login): `*** System
  restart required ***` has been pending ~27 days for a kernel update, plus 29
  package updates, 3 of them security. Also note the OS is **Ubuntu 22.04.5**, not
  the 24.04 that the client-config `infrastructure.md` claims — a `do-release-upgrade`
  is a real unperformed migration, not a doc typo.
- **Rotate the DB password + salts** if `wp-config.php.bak-*` was ever publicly
  fetchable (see the Gotchas entry) — `wp config shuffle-salts` handles the salts and
  just logs everyone out.
- Terminate the old compromised instance (keep the forensic boot-volume clone a
  while as evidence).

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
- **Three historical IPs.** Live is **`159.54.174.73`** (dantesocietyofva.org).
  `146.235.210.188` (hacked) and `167.234.212.48` (interim demo) are **dead** but
  still litter older notes — a refused/hanging SSH is usually the wrong IP, not a
  down server.
- **Never back up `wp-config.php` inside the web root.** A `wp-config.php.bak-*`
  has a non-PHP extension, so Apache serves it as **plain text** — DB password and
  auth salts to anyone who guesses the name. Two such files sat in `/var/www/html`
  from July to Sept 2026. Back up to `/root/wp-config-backups/` (`chmod 600`).
- **Plugin installs need SSH** — `DISALLOW_FILE_MODS` removes Plugins → Add New.
  See "Installing a plugin on live" above. Activation still works from the dashboard.
- **Newsletter templates are theme files** — drop an `.html` in
  `wp-theme/newsletter-templates/`, commit, deploy, and it shows in the composer
  dropdown. No DB involved, so it works the same on Local and live.
- **No JS build step.** Editor/block/calendar scripts use global `wp.*`.
- **Slug-collision rule:** a `page-{slug}.php` file auto-renders that page
  regardless of the Template dropdown. Name custom templates `template-*.php`. See
  the dedicated section above — this caused several "can't edit / can't remove
  this content" bugs.
- **`theme.json` is cached** unless `WP_DEBUG` is on — toggle it in Local to see
  editor-control changes.
- **Content vs code / Local vs live** — see the top of this file. This is the
  source of ~every "why didn't my change show up" question.
- **Live box was rebuilt (July 2026)** after a hack — new IP `167.234.212.48`, new
  SSH key, OCI-specific firewall/DNAT/AllowOverride config, and a server-side
  hardening mu-plugin that is **not in Git**. Deploy secrets still point at the dead
  old box until updated. See "Security incident & server rebuild" above.
- **Meta stored as JSON** (assistant change-log `_ops`, newsletter `_nl_data`)
  must be `wp_slash()`ed before `update_post_meta` (it `wp_unslash`es internally),
  or `\uXXXX` escapes get corrupted (dashes/accents turn into `u2013` etc.).
- **`background-attachment: fixed`** is switched to `scroll` on mobile in
  `dante_responsive_css` — iOS renders fixed backgrounds blurry/upscaled.
- **Custom logo** needs the `.custom-logo { height: 64px }` rule or it's huge.
- **Email** needs WP Mail SMTP (Other SMTP + Gmail app password / Brevo).
- **Dante Assistant** needs an API key per site. Preferred: the
  **`DANTE_ANTHROPIC_KEY`** constant in `/var/www/dante-secrets.php` (server-side, hidden
  from admins); fallback: the DB option via **Settings → Dante Assistant**. Without
  either, the widget shows "not set up yet." Resolver: `dante_assistant_api_key()`.
- **Seeder** (`inc/seed-events.php`) runs once per environment; delete it when done.
