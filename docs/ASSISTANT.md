# Dante Assistant — guide

A chat helper on the WordPress **Dashboard** that lets board members update the
website in plain English. Built into the theme (`wp-theme/inc/assistant/`), no
plugins.

---

## For board members (non-technical)

You'll find **"Dante Assistant"** at the top of the **Dashboard** (the first
screen after you log in). Type what you want in plain English — it's like
texting someone who updates the site for you.

### What it can do

- **Events** — *"Add our October wine tasting, Oct 22 5:30–7pm at St. Paul's."*
  It creates the event as a **draft** and shows it in **"Waiting for your
  approval."** Click **Publish** (or **Publish all**) to put it live.
- **Newsletters** — *"Send a newsletter about our upcoming events."* It composes
  a draft with a live **Preview**. You can **send yourself a test**, **schedule**
  it, or **send to all subscribers** — all with buttons on the card. The
  assistant never sends on its own.
- **Page wording** — *"On the cover page, change the intro to …"* or *"update
  the board members."* Page text changes go live right away and can be undone.
- **Photos** — click **📷 Add a photo**, attach a picture, then *"add this to our
  gallery"* (optionally with a caption). It appears in the photo collage.

### Adding a photo to a message or event

The **📷 Add a photo** button uploads a picture. Your next request uses it —
"add this to the gallery," "put this on the wine tasting event," or "send a
newsletter with this flyer."

### Undo

Everything you do appears in **"Recent changes"** with an **Undo** button (the
last 5 changes). Newly created events wait in **"Waiting for your approval"**
until you publish them, so nothing goes live by accident.

### If it says "not set up yet"

An administrator needs to add an API key (see below). The assistant can't work
until that's done.

---

## For administrators / developers

### One-time setup (per site)

**Settings → Dante Assistant** → paste an **Anthropic API key** → choose a model
→ Save. The key is stored in that site's database, so **Local and live are set
up separately**.

### How it works

The chat runs an **agent loop** on the server: your message goes to the AI along
with a set of **tools** (create event, compose newsletter, edit page text, add
photo…). The AI decides which tool to call; PHP runs it against WordPress and
feeds the result back until it's done. The API key never reaches the browser.

**Safety model:**
- New events are **drafts** → published only when a human approves.
- Newsletters are **composed only** — sending/scheduling is human-clicked.
- Page/photo/published-event edits apply immediately but are **logged and
  undoable** (the last 5 changes).
- Every tool checks the user's capability (`edit_posts` / `upload_files`).

### File layout (`wp-theme/inc/assistant/`)

| File | Role |
|------|------|
| `assistant.php` | Bootstrap, dashboard widget, asset loading, capability gate |
| `providers.php` | AI provider adapter (Anthropic today; swap-able) |
| `tools.php` | Tool registry + dispatch (events + shared) |
| `tools-newsletter.php` | Newsletter compose/preview/test/schedule/send |
| `tools-pages.php` | List/read pages + edit one block's wording |
| `tools-photos.php` | Add an attached photo to the gallery |
| `changelog.php` | Change sets + undo (private `dante_change` CPT) |
| `rest.php` | REST routes + the agent loop + system prompt |
| `settings.php` | Admin-only API key + model picker |
| `js/assistant.js`, `css/assistant.css` | Chat UI (no build step) |

### Adding a new capability

1. Add a tool schema + handler (in `tools.php` or a new `tools-*.php`, required
   from `assistant.php`).
2. Register it in `dante_assistant_tools()` and `dante_assistant_run_tool()`.
3. Mention it in the system prompt (`dante_assistant_system_prompt()` in
   `rest.php`).

Immediate, undoable actions call `dante_changeset_log_applied()`. Draft-then-
approve actions tag the draft with `dante_changeset_current()`.

### Changing model or provider

Model is chosen in **Settings → Dante Assistant**. To add another provider,
implement `Dante_AI_Provider` in `providers.php` and select it in
`dante_assistant_provider()`.
