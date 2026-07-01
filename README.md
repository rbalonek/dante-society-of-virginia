# Dante Alighieri Society of Virginia — website

A custom WordPress theme (plus seed content) for the Dante Alighieri Society of
Virginia, a non-profit promoting Italian culture in the Commonwealth of Virginia.

- **Live demo:** http://146.235.210.188/
- **Local dev:** `http://dante.local` (runs in [Local](https://localwp.com))
- **Design:** deep green / gold / cream, Playfair Display headings + Lato body,
  with the Domenico di Michelino *Dante and the Divine Comedy* painting behind the
  site.

> **Read this first — the one rule that avoids all confusion:**
> **Theme = code (this repo, deploys via Git). Content = the WordPress database
> (pages, events, menus, settings — NOT in Git, and different on each site).**
> Editing theme files never changes page text; content edited on one site doesn't
> appear on another. Make content edits on the site whose visitors matter.

## What's in here

```
wp-theme/            The WordPress theme — THIS is what deploys
  functions.php      Setup, enqueues (cache-busted by file mtime), editor limits,
                     Customizer, responsive CSS, nav "Calendar" item
  header/footer/…    Templates. Footer is hardcoded; hero text has Customizer + defaults
  theme.json         Locks the editor to brand colors/sizes; friendly block list
  css/               style.css (site) + editor*.css (block-editor experience)
  js/                calendar.js, events-block.js, editor.js, checkout.js, navigation.js,
                     lib/fullcalendar.min.js (bundled)
  images/            Bundled background images (deploy with the theme)
  inc/
    events.php       "Event" post type + the dante/events block + FullCalendar + popup
    newsletter.php   Subscribers + a newsletter composer that sends via wp_mail
    seed-events.php  One-time starter-events seeder (safe to delete after it runs)
  page-events.php    "Events Page" template (renders calendar via PHP — see notes)
  page-membership.php / page-checkout.php   Membership + a Stripe-style checkout MOCKUP
images/              Source images (also symlinked to the Local webroot as /images)
wordpress-import.xml One-time seed of the 8 pages + Primary Menu (WXR)
SETUP-LOCAL.md       Stand the site up in Local
DEPLOY.md            GitHub → live server deploy details
CLAUDE.md            Deep technical guide + gotchas
*.html, server.js    The original static mockup (not used by WordPress)
```

## Features

- **Simple, locked-down block editor** for non-technical board members: brand-only
  colors, named text sizes (+ slider), a short list of friendly blocks, big/obvious
  image resize handles, labeled image alignment, and Media & Text for
  image-beside-text.
- **Events system** — add events from a form (title, date, time, location, image);
  they render automatically as a list and in a **calendar** (month view, "This
  Year's Events", "All Events"). The calendar is also a **popup** opened from the
  "Calendar" menu item. Everything updates live as events are added/edited.
- **Newsletter tool** — manage subscribers and compose/send newsletters (all
  upcoming events / a single event / a free message) with a live preview, test
  send, and a compliant unsubscribe footer. Sends via WP Mail SMTP.
- **Membership + demo checkout** — a Stripe-style checkout *mockup* (no real
  payment) previewing the online-dues flow.
- **Adjustable mobile breakpoint** and **editable background images** via the
  Customizer.

## Getting started

- **Run it locally:** see **[SETUP-LOCAL.md](SETUP-LOCAL.md)** (create the site in
  Local, activate the theme, import `wordpress-import.xml`, set the front page).
- **Deploy to the server:** see **[DEPLOY.md](DEPLOY.md)**. Pushing `wp-theme/**`
  to `main` rsyncs the theme to the live box via GitHub Actions.
- **Understand the architecture / gotchas:** see **[CLAUDE.md](CLAUDE.md)**.

## Editing the site (for board members)

- **Pages & text:** Pages → open a page → edit like a document → Update. For bigger
  changes, use the editor's **Code Editor** (⋮ menu) to paste block markup.
- **Events:** Events → Add Event. They appear on the site automatically.
- **Newsletter:** Newsletter → Compose (and Subscribers to manage the list).
- **Logo / hero / background / colors:** Appearance → Customize.
- **Do content edits on the live site** — Local is a developer sandbox and its
  content does not sync to live.

## Membership

- Individual **$35/yr**, Family **$60/yr**. Fiscal year September–September; dues
  requested by the *Viva l'Italia* event (~Sept 20). Checkout on the site is a
  demo mockup only — no real payment is processed until Stripe is connected.

## License

GPL v2 or later (same as WordPress).

---

*Note: the repo also contains the original static HTML mockup (`index.html`,
`about.html`, …, `server.js`). It was the first-pass design and is **not** used by
the WordPress site; the theme in `wp-theme/` superseded it.*
