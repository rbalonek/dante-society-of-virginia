# Running the Dante Society site in Local (WordPress)

This turns the repo into a working WordPress site you can demo and hand to the
board. The whole thing takes ~10 minutes.

> **What's in the repo**
> - `wp-theme/` — the WordPress theme (copy into WordPress)
> - `wordpress-import.xml` — all 8 pages + the navigation menu, ready to import
> - `images/` — photos and backgrounds used by the site
> - `css/`, `*.html`, `server.js` — the original static preview (not used by WordPress)

---

## One-time setup

### 1. Create the site in Local
1. Open **Local** → **Create a new site**.
2. Name it something like `dante` (this gives you the address `http://dante.local`).
3. Use the default PHP / web server / database options. Finish, then **Start site**.

### 2. Install the theme
1. In Local, click **Go to site folder** → open `app/public/wp-content/themes/`.
2. Copy the repo's **`wp-theme/`** folder in there and **rename it to `dante-society`**.
   (Final path: `app/public/wp-content/themes/dante-society/`)
3. In WP Admin (**WP Admin** button in Local) → **Appearance → Themes** →
   activate **Dante Society of Virginia**.

### 3. Add the images
1. Copy the repo's **`images/`** folder into `app/public/` (the site root).
   (Final path: `app/public/images/`)

   *Why here:* the theme's backgrounds and the imported page photos all point to
   `/images/...`, matching this repo exactly. Keeping them at the site root means
   the same image paths work in both the static preview and WordPress.

### 4. Import the content
1. WP Admin → **Tools → Import**.
2. Under **WordPress**, click **Install Now**, then **Run Importer**.
3. Choose **`wordpress-import.xml`** from the repo and upload.
4. On the next screen, assign posts to your admin user and click **Submit**.
   *(Leave "Download and import file attachments" unchecked — images are served
   from `/images/` already.)*

This creates 8 Pages (Home, About, Board, Programs, Membership, Italian Culture,
Contact, Newsletter) and a **Primary Menu**.

### 5. Set the front page and permalinks
1. **Settings → Reading** → "Your homepage displays" → **A static page** →
   Homepage = **Home**. Save.
2. **Settings → Permalinks** → choose **Post name** → Save.
   *(This makes addresses like `/about` work and matches the menu links.)*

### 6. (Optional) Turn on the pre-built menu
The navigation already works out of the box. To let editors reorder it from the
admin instead of code:
- **Appearance → Menus** → select **Primary Menu** → check **Primary Menu** under
  "Display location" → Save.

**Done.** Visit `http://dante.local` — you should see the styled site with the
green/gold/cream design, hero, events, and working navigation.

---

## How board members edit the site (the simple version)

This is the part to show them. Every page is plain text you edit in place:

1. Log in at `http://dante.local/wp-admin` (or `yourdomain.com/wp-admin` once live).
2. Click **Pages** in the left menu.
3. Click the page you want to change (e.g. **Home** to update events).
4. Click any text and type — just like a Word document.
   - To add a new event: click the **+** button, choose **Image** or **Heading**
     or **Paragraph**, and add your content.
5. Click the blue **Update** button (top right) to save.

No code, no HTML. That's the whole workflow.

---

## Before it goes live (production checklist)

These are the few things that need a plugin or a real host — none are required
for the demo:

| Need | How |
|------|-----|
| **Contact form** | Install **Contact Form 7**, create a form, paste its shortcode into the Contact page where the note is. |
| **Newsletter / mailer** | Install **MailPoet** (sends emails + signup form). Add a signup form to the Newsletter and Home pages. |
| **Membership application file** | The download buttons currently point to the files on the old Weebly site so they work in the demo. Once live, upload the DOCX/PDF to **Media** and update the button links. |
| **Event/page images** | For the demo they load from `/images/`. For long-term editing, re-add key images through **Media** so board members can swap them via the editor. |
| **Backups & security** | On the production host, add **UpdraftPlus** (backups) and a security plugin (**Wordfence**). |
| **Logo** | **Appearance → Customize → custom logo** to replace the "D" placeholder. |

---

## Updating from this repo later

Since the repo stays the source of truth for major/structural changes:
- **Theme/design changes** → edit files in `wp-theme/`, then copy the folder back
  into `wp-content/themes/dante-society/` (or symlink it during development).
- **Re-importing content** → re-running `wordpress-import.xml` will create
  duplicate pages, so only do that on a fresh site. Day-to-day content edits
  should happen in WP Admin, not by re-importing.
